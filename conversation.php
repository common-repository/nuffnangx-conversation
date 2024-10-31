<?php
/*
Plugin Name: Imotiv Conversation
Plugin URI: http://wordpress.org/extend/plugins/nuffnangx-conversation
Description: Imotiv Conversation replaces your WordPress comment system with your comments and conversations on Imotiv.
Author: Imotiv <help@imotiv.ly>
Version: 1.0.7
Author URI: http://www.imotiv.ly
*/

define('NNX_DOMAIN','http://www.imotiv.ly');
define('NNX_VERIFY_KEY', NNX_DOMAIN.'/pluginapi/verify.json');
define('NNX_COMMENT_COUNT', NNX_DOMAIN.'/pluginapi/comments_count_single.json');
define('NNX_EXPORT_COMMENT', NNX_DOMAIN.'/pluginapi/push.json');
define('NNX_IMPORT_COMMENT', NNX_DOMAIN.'/pluginapi/updates.json');
define('NNX_IMPORT_TOTAL', NNX_DOMAIN.'/pluginapi/update_total.json');
define('NNX_VERSION','1.0.7');

define('NNX_PLUGIN_SLUG','imotiv-conversation');

register_activation_hook( __FILE__, 'nnx_install' );
register_deactivation_hook( __FILE__, 'nnx_uninstall' );

/* Installation */
function nnx_install() {

    add_option( 'nnx_version', NNX_VERSION );
    add_option( 'nnx_api_key' );
    add_option( 'nnx_ver_token' );
    add_option( 'nnx_debug' );
    add_option( 'nnx_enable_convo', 0 );
    add_option( 'nnx_last_up_id', 0 );          // WP Comment ID
    add_option( 'nnx_last_down_time', '2012-09-18 00:00:01' );
    add_option( 'nnx_last_comment_id', 0 );    // NNX Update ID
    
    // Last WP comment ID before switch to NNX
    global $wpdb;
    $last_wp_comment_id = $wpdb->get_var("
                                    SELECT comments.comment_ID
                                    FROM {$wpdb->comments} AS comments
                                        LEFT JOIN {$wpdb->commentmeta} AS commentmeta ON (comments.comment_ID = commentmeta.comment_id)
                                        LEFT JOIN {$wpdb->posts} AS post ON (posts.ID=comments.comment_post_ID)
                                    WHERE comments.comment_approved = 1 AND comments.comment_type != 'pingback'
                                        AND (commentmeta.meta_key !=  'nnx' OR commentmeta.meta_id IS NULL)
                                        AND posts.post_status = 'publish'
                                    ORDER BY comments.comment_ID DESC LIMIT 1");
    if (is_null($last_wp_comment_id)){
        // No WP comments
        $last_wp_comment_id = 0;
    }
    add_option( 'nnx_last_wp_comment_id', $last_wp_comment_id);
}

function nnx_uninstall() {
    delete_option( 'nnx_api_key' );
    delete_option( 'nnx_ver_token' );
    delete_option( 'nnx_debug' );
    delete_option( 'nnx_enable_convo' );
    delete_option( 'nnx_last_up_id' );
    delete_option( 'nnx_last_down_time' );
    delete_option( 'nnx_last_comment_id' );
    
    if ( wp_next_scheduled('nnx_import_cron') ){
        wp_clear_scheduled_hook('nnx_import_cron');
    }
}
function nnx_plugin_action_links($links, $file) {
    $plugin_file = basename(__FILE__);
    if (basename($file) == $plugin_file) {
        $settings_link = "<a href=\"edit-comments.php?page=".NNX_PLUGIN_SLUG."\">Settings</a>";
        array_unshift($links, $settings_link);
    }

    return $links;
}
// Add Settings link on plugin
add_filter('plugin_action_links', 'nnx_plugin_action_links', 10, 2);

/* Run scheduled import comment cron */
require_once( dirname(__FILE__).'/import.php' );
add_action( 'nnx_import_cron', 'nnx_import_comment' );

/* Run scheduled export comment cron */
require_once( dirname(__FILE__).'/export.php' );
add_action( 'nnx_export_cron', 'nnx_export_wp_comment' );

/* Run export comment */
function nnx_handler(){
    switch ($_POST['nnx_action']){
        case 'export':
            require_once( dirname(__FILE__).'/export.php' );

            $export_status = nnx_export_wp_comment();

            header('Content-type: text/javascript');
            echo json_encode($export_status);die();
            break;
        case 'import':
            $import_status = nnx_import_comment();

            header('Content-type: text/javascript');
            echo json_encode($import_status);die();
            break;
        case 'import-total':
            $import_total = nnx_import_total();

            header('Content-type: text/javascript');
            echo json_encode($import_total);die();
            break;
    }

    // Version Check
    if ( get_option('nnx_version') === false){
        // Older version without nnx_version
        add_option('nnx_version', NNX_VERSION);
        update_option('nnx_last_up_id', 0); // Reset export to reupload with post title
        
        do_upgrade();
    }else{
        $db_version = str_replace(".", "", get_option('nnx_version'));
        $this_version = str_replace(".", "", NNX_VERSION);

        if ( $db_version < $this_version ){
            do_upgrade();
        }
    }
}
add_action('admin_init', 'nnx_handler');

function do_upgrade(){
    if (NNX_VERSION == '1.0.6'){
        // Resync (export) WP comments to NNX with post_title
        if ( wp_next_scheduled('nnx_export_cron') === false ){
            // Activate auto-sync (export)
            wp_schedule_event( time(), '5mins', 'nnx_export_cron' );
        }
    }
}
 
function cron_add_5mins( $schedules ) {
    // Adds once weekly to the existing schedules.
    $schedules['5mins'] = array(
            'interval' => 300,
            'display' => __( '5 Minutes' )
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'cron_add_5mins' );

/* Create Menu on Admin Comments */
function imotiv_admin_menu() {
    add_comments_page( 
                'Imotiv Conversations', 
                'Imotiv', 
                'manage_options', 
                'imotiv-conversation', 
                'imotiv_admin_setting'
    );
}
add_action( 'admin_menu', 'imotiv_admin_menu' );

/* NNX Admin Setting Page */
function imotiv_admin_setting() {
    include_once( dirname(__FILE__).'/admin.php' );
}

function nnx_admin_init_setting() {
    add_settings_section('nnx_settings_main', __('Imotiv Settings'), 'nnx_settings_section', 'nnx_settings');
    
    register_setting( 'nnx_settings_group', 'nnx_api_key', 'nnx_settings_validate' );
    add_settings_field('nnx_api_key', __('API Key'), 'nnx_api_input', 'nnx_settings', 'nnx_settings_main');

    if ( strlen(get_option('nnx_api_key')) > 0 ){
        register_setting( 'nnx_settings_group', 'nnx_enable_convo' );
        add_settings_field('nnx_enable_convo', __('Enable Conversations'), 'nnx_convo_input', 'nnx_settings', 'nnx_settings_main');
    }
}
add_action( 'admin_init', 'nnx_admin_init_setting' );

function nnx_settings_section() {
    echo "<p>".__("You'll need a Imotiv API Key to use the Imotiv Conversations WP Plugin")."</p>";
    echo "<p><a id='nnx_help_toggle' href='javascript:void(0)'>".__("Where do I get my Imotiv API Key?")."</a>";
    echo "<br/><span id='nnx_help' style='display:none;'>";
    echo "<br/><em>".__("* You'll need to login to your Imotiv account")."</em>";
    echo "<br/><em>".__("* In <strong><a href='http://www.imotiv.ly/blogmanager/settings'>My blogs</a></strong>, click on 'View Code' and expand the Wordpress Plugin method.")."</em>";
    echo "<br/><em>".__("* The API Key is listed in step 3.")."</em>";
    echo "</span>";
    echo "</p>";
}
function nnx_api_input() {
    $nnx_api_key = get_option('nnx_api_key');
    echo "<input id='nnx_api_key' name='nnx_api_key' size='40' type='text' value='{$nnx_api_key}' />";
}
function nnx_convo_input(){
    $nnx_enable_convo = "";
    if (get_option('nnx_enable_convo')){
        $nnx_enable_convo = "checked='checked'";
    }
    echo "<input id='nnx_enable_convo' name='nnx_enable_convo' type='checkbox' value='1' {$nnx_enable_convo} />";
}
function nnx_settings_validate($input) {
    $valid = "";
    
    // Tidy input
    $input = trim($input);
    
    // Verify API Key
    $http = new WP_Http();
    $response = $http->request( NNX_VERIFY_KEY,
                    array(
                        'method' => 'POST',
                        'body' => array(
                            'api_key' => urlencode($input),
                            'domain' => urlencode( base64_encode( home_url() ) )
                        )
                    )
                );
    
    if (is_wp_error($response)){
        update_option('nnx_debug', "Verify : [".gmdate("Y-m-d H:i:s")."] ".$response->get_error_message());
    }else{
        $reply = json_decode($response['body']);
        
        $status = (bool)$reply->success;
        if ($status){
            add_settings_error('nnx_admin_notices', 'nnx_api_key', __("Imotiv API Key Verified."), 'updated');
            update_option('nnx_ver_token', $reply->verification_token);
            update_option('nnx_enable_convo', 1);
            update_option('nnx_debug', "Verify : [".gmdate("Y-m-d H:i:s")."] Success");

            $valid = $input;
            
            // Activate auto-sync (import)
            wp_schedule_event( time(), 'hourly', 'nnx_import_cron' );
        }else{
            add_settings_error('nnx_admin_notices', 'nnx_api_key', __("Error validating Imotiv API Key."), 'error');
            update_option('nnx_ver_token', '');
            update_option('nnx_enable_convo', 0);
            update_option('nnx_debug', "Verify : [".gmdate("Y-m-d H:i:s")."] ".$response['body']);
        }
    }
    
    return $valid;
}

/* Admin Message Prompt */
function nnx_admin_warnings() {
    if ( !get_option('nnx_api_key') ) {
        function nnx_warning() {
            echo "<div id='nnx-warning' class='updated fade'><p><strong>".__('Imotiv Conversations is almost ready.')."</strong> ".sprintf(__('You must <a href="%1$s">enter your Imotiv API key</a> to complete the setup.'), "edit-comments.php?page=imotiv-conversation")."</p></div>";
        }
        add_action('admin_notices', 'nnx_warning');
    }
}
nnx_admin_warnings();

function nnx_admin_notices() {
     settings_errors( 'nnx_admin_notices' );
}
add_action( 'admin_notices', 'nnx_admin_notices' );

/* Overwrite WP Comment Template */
function nnx_conversations($content){
    if (get_option('nnx_enable_convo')){
        // By-pass comment_open() that automatically close comments after x days
        global $wpdb;
        $comment_status = $wpdb->get_var( "SELECT comment_status FROM {$wpdb->posts} WHERE ID=".get_the_ID() ); 
        if( $comment_status == 'open' && is_singular() ) {
            return dirname(__FILE__) . '/conversation_template.php';
        }
    }
}
add_filter('comments_template', 'nnx_conversations');

function nnx_conversation_count(){
    $permalink = get_permalink(get_the_ID());

    $http = new WP_Http();
    $response = $http->request( NNX_COMMENT_COUNT,
                    array(
                        'timeout' => 20,
                        'method' => 'POST',
                        'body' => array(
                            'url' => urlencode( base64_encode( $permalink ) )
                        )
                    )
                );
    
    if (is_wp_error($response)){
        update_option('nnx_debug', "Count : [".gmdate("Y-m-d H:i:s")."] ".$response->get_error_message());
        
        return get_comments_number(get_the_ID())." ".__("Comment(s)");
    }else{
        $comments = json_decode($response['body']);

        if ($comments->count == 0){
            return _("Add A Comment");
        }elseif ($comments->count == 1){
            return "{$comments->count} "._("Comment");
        }else{
            return "{$comments->count} "._("Comments");
        }
    }

}
add_filter('comments_number', 'nnx_conversation_count');

/* Load NNX JS */
function nnx_load_ver_script(){
    $nnx_ver_token = get_option('nnx_ver_token');
    if (!empty($nnx_ver_token)){
        echo "<script type=\"text/javascript\">verification_token = \"{$nnx_ver_token}\";</script>";
    }
}
add_action( 'wp_head', 'nnx_load_ver_script' );


?>
