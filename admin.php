<?php
global $wp_version;

$date_timezone = get_option('gmt_offset');
if (strpos($date_timezone, "-") !== 0){
    $date_timezone = "+".$date_timezone;
}

$show_sync = false;
$nnx_api_key = get_option('nnx_api_key');
if (strlen($nnx_api_key) > 0){
    $show_sync = true;
}

// Export Comments
$export_do = true;
$export_btn = "Export Comments";
$export_status = "";
$export_curr = get_option('nnx_last_up_id');
$export_total = get_option('nnx_last_wp_comment_id');

if ($export_curr == $export_total){
    $export_do = false;
}elseif ($export_curr < $export_total && $export_curr != 0){
    $export_btn = "Resume Export";
    $export_status = "{$export_curr} / {$export_total}";
}

// Auto Import Comments
if ( isset($_POST['btnImport']) ){
    $import_schedule = wp_next_scheduled( 'nnx_import_cron' );
    if ( $import_schedule ){
        wp_unschedule_event($import_schedule, 'nnx_import_cron');
    }else{
        wp_schedule_event( time(), 'hourly', 'nnx_import_cron' );
    }
}

$import_auto = false;
$import_class = "red";
if ( wp_next_scheduled( 'nnx_import_cron') ){
    $import_auto = true;
    $import_class = "green";
}

$import_do = true;
$import_btn = "Import Comments";
$import_status = "";
$import_last_time = strtotime(get_option('nnx_last_down_time'));
$import_now_time = current_time("timestamp") - 60;

if ($import_last_time >= $import_now_time){
    $import_do = false;
}else{
    $last_updated = get_option('nnx_last_down_time');
    $import_status = "Last Updated @ {$last_updated}";
}

?>
<style type="text/css">
.nnx_loading{
    background: url(<?php echo admin_url('images/loading.gif'); ?>) left center no-repeat;
    line-height: 16px;
    padding-left: 20px;
}
</</style>
<div class="wrap">
    <?php screen_icon(); ?>
    <h2><?php echo __("Imotiv Conversations") ?></h2>

    <form method="POST" action="options.php">
        <?php settings_fields( 'nnx_settings_group' ); ?>
        <?php do_settings_sections( 'nnx_settings' ); ?>

        <input name="submit" type="submit" value="Save" class="button-primary button" />
    </form>
    
    <?php if ($show_sync){ ?>
    <h3><?php echo __("Sync Conversations") ?></h3>
    <form method="POST" action="">
        <p>
            <?php echo __("Auto Synchronize Imotiv Conversations to WordPress : ") ?>
            <span style="color:<?php echo $import_class ?>"><strong><?php if ($import_auto){ echo __("Enabled"); }else{ echo __("Disabled"); }?></strong></span>
            <input name="btnImport" type="submit" value="<?php if (!$import_auto){ echo __("Enabled"); }else{ echo __("Disabled"); }?>" class="button" /> 
            &nbsp;<span id="nnx_import"><a id="nnx_import_btn" class="button"><?php echo __("Sync Now") ?></a></span><br/>
            <span id="nnx_import_note"></span>
            <br/><em>* This will import your Imotiv comments into Wordpress. Note that this is for archiving purposes only.</em>
            <br/><em>* Deleting comments from Wordpress will not delete them from Imotiv. To do so, log into your Imotiv account</em>
        </p>
    </form>
    <p>
        <?php echo __("Export your WordPress comments to Imotiv Conversations") ?>
        <span id="nnx_export">            
        <?php if ($export_do){ ?>
            <a id="nnx_export_btn" class="button"><?php echo __($export_btn) ?></a>&nbsp;<?php echo $export_status ?>
        <?php }else{ ?>
            :&nbsp;<span style="color:green"><strong><?php echo __("Export Completed") ?></strong></span>
        <?php } ?>
        </span><br/>
        <span id="nnx_export_note"></span>
    </p>
    <?php } ?>
    <p>
        
    </p>
    <!--
    <h3><?php echo __("Debug") ?></h3>
    <textarea style="width:650px; height:240px;">
URL: <?php echo home_url()."\n"; ?>
PHP Version: <?php echo phpversion()."\n"; ?>
WP Version: <?php echo $wp_version."\n"; ?>
Active Theme: <?php $theme = get_theme(get_current_theme()); echo $theme['Name'].' '.$theme['Version']."\n"; ?>
Local Time: <?php echo current_time("mysql")." GMT{$date_timezone}\n" ?>

Plugin Version: <?php echo NNX_VERSION."\n"; ?>

Settings
--------
API Key: <?php echo get_option('nnx_api_key')."\n"; ?>
Verification Token: <?php echo get_option('nnx_ver_token')."\n"; ?>
Enable Convo: <?php echo get_option('nnx_enable_convo')."\n"; ?>

Import Status: 
<?php 
if ( wp_next_scheduled('nnx_import_cron') ){ 
    echo "Enabled\n";
    echo date("Y-m-d H:i:s", wp_next_scheduled('nnx_import_cron'))." (Scheduled)\n"; 
}else{ 
    echo "Disabled\n"; 
}
echo get_option('nnx_last_comment_id')." (Last Comment ID)\n";
echo get_option('nnx_last_down_time')." (Last Import)\n";
?>

Export Status: 
<?php echo $export_curr." / ".$export_total."\n"; ?>
<?php echo date("Y-m-d H:i:s", wp_next_scheduled('nnx_export_cron'))." (Scheduled)\n"; ?>
    
Plugins
-------
<?php
foreach (get_plugins() as $key => $plugin) {
    $isactive = "";
    if (is_plugin_active($key)) {
        $isactive = "(active)";
    }
    echo $plugin['Name'].' '.$plugin['Version'].' '.$isactive."\n";
}
?>

Debug
-----
<?php echo get_option('nnx_debug')."\n"; ?>
</textarea>
    -->
</div>

<script type="text/javascript">
jQuery(function($) {
    nnx_import();
    nnx_export();
    
    $('#nnx_help_toggle').click(function(){
        $('#nnx_help').toggle();
    });
});

var import_curr = 0;
var import_total = 0;
function nnx_import(){
    var $ = jQuery;
    $('#nnx_import_btn').click(function(){
        $('#nnx_import').html('<span class="status"></span>');
        $('#nnx_import .status').addClass('nnx_loading').html('Import In Progress');
        $('#nnx_import_note').html("<strong><em>* Kindly do not leave this page until import is completed. This may take a while. *</em></strong>");
        nnx_import_total();
    });    
}
function nnx_import_total(){
    var $ = jQuery;
    var status = $('#nnx_import .status');
    
    import_total = 0;
    $.post('<?php echo admin_url('index.php'); ?>', 
            {
                nnx_action: 'import-total',
                timestamp: new Date()
            }, 
            function(response){
                if (response.total > 0){
                    import_total = response.total;
                    
                    status.html("Importing... 0 / " + import_total);
                    nnx_import_comment();
                }else{
                    status.removeClass('nnx_loading').html("No New Updates");
                    $('#nnx_import_note').html("");
                }
            },
            'json'
    );  
} 
function nnx_import_comment(){
    var $ = jQuery;
    var status = $('#nnx_import .status');
    $.post('<?php echo admin_url('index.php'); ?>', 
            {
                nnx_action: 'import',
                timestamp: new Date()
            }, 
            function(response){
                if (response.count >= 0){
                    import_curr = import_curr + response.count;
                    status.html("Importing... " + import_curr + " / " + import_total);
                    if (import_curr >= import_total){
                        status.removeClass('nnx_loading').html("<strong>Sync Complete</strong>");
                        $('#nnx_import_note').html("");
                    }else{
                        nnx_import_comment();
                    }
                }else{
                    status.html("Import Failed... Retrying");
                    setTimeout(nnx_import_comment, 2000);
                }
            },
            'json'
    );    
}

function nnx_export(){
    var $ = jQuery;
    $('#nnx_export_btn').click(function(){
        $('#nnx_export').html('<span class="status"></span>');
        $('#nnx_export .status').addClass('nnx_loading').html('Export In Progress');
        $('#nnx_export_note').html("<strong><em>* Kindly do not leave this page until export is completed. This may take a while. *</em></strong>");
        nnx_export_comment();
    });    
}
 
function nnx_export_comment(){
    var $ = jQuery;
    var status = $('#nnx_export .status');
    $.post('<?php echo admin_url('index.php'); ?>', 
            {
                nnx_action: 'export',
                timestamp: new Date()
            }, 
            function(response){
                if (response.success){
                    status.html("Exporting... " + response.id_last + " / " + response.total);
                    if (response.id_last == response.total){
                        status.removeClass('nnx_loading').html("&nbsp;:&nbsp;<span style=\"color:green\"><strong>Export Complete</strong></span>");
                        $('#nnx_export_note').html("");
                    }else{
                        nnx_export_comment();
                    }
                }else{
                    status.html("Export Failed... Retrying");
                    setTimeout(nnx_export_comment, 2000);
                }
            },
            'json'
    );    
}

</script>