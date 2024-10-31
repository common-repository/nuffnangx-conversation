<?php

function nnx_encode_comment_id($post_id, $nnx_comment_id){
    return $post_id.":".$nnx_comment_id;
}

function nnx_to_wp_comment_id($nnx_comment_id) {
    global $wpdb;

    if (strpos($nnx_comment_id, ":") === false){
        return false;
    }else{
        $comment_id = $wpdb->get_var( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = 'nnx' AND meta_value = '{$nnx_comment_id}'" );
        return $comment_id;
    }
}

function nnx_import_total(){
    global $wpdb;
    $nnx_api_key = get_option('nnx_api_key');
    $url_encoded = urlencode(base64_encode( home_url() ));
    
    $batch_last_id = get_option('nnx_last_comment_id');

    // Get comment updates total
    $http = new WP_Http();
    $response = $http->request( NNX_IMPORT_TOTAL,
                    array(
                        'timeout' => 20,
                        'method' => 'POST',
                        'body' => array(
                            'api_key' => $nnx_api_key,
                            'domain' => $url_encoded,
                            'last_id_conversation_update' => $batch_last_id
                        )
                    )
                );
    if (is_wp_error($response)){
        update_option('nnx_debug', "Import Count : [".gmdate("Y-m-d H:i:s")."] ".$response->get_error_message() );
        
        return array("total"=>0);
    }else{
        $comments = json_decode($response['body']);
        update_option('nnx_debug', "Import Count : [".gmdate("Y-m-d H:i:s")."] ".$response['body'] );

        return array("total"=>$comments->total);
    }
}

function nnx_import_comment(){
    global $wpdb;
    $nnx_api_key = get_option('nnx_api_key');
    $url_encoded = urlencode(base64_encode( home_url() ));

    $date_timezone = get_option('gmt_offset');
    if (strpos($date_timezone, "-") !== 0){
        $date_timezone = "+".$date_timezone;
    }
    
    $time_import = current_time('mysql');
    
    $batch_count = 0;
    $batch_last_id = get_option('nnx_last_comment_id');

    // Get comment updates
    $http = new WP_Http();
    $response = $http->request( NNX_IMPORT_COMMENT,
                    array(
                        'timeout' => 20,
                        'method' => 'POST',
                        'body' => array(
                            'api_key' => $nnx_api_key,
                            'domain' => $url_encoded,
                            'last_id_conversation_update' => $batch_last_id,
                            'limit' => 100
                        )
                    )
                );
    
    if (is_wp_error($response)){
        update_option('nnx_debug', "Import : [".gmdate("Y-m-d H:i:s")."] ".$response->get_error_message() );
        
        $batch_count = -1;
    }else{
        $comments = json_decode($response['body']);
        update_option('nnx_debug', "Import : [".gmdate("Y-m-d H:i:s")."] ".$response['body'] );
        
        $batch_last_id = $comments->last_id_conversation_update;

        foreach($comments->comments as $post_encoded_url => $status) {
            $post_url = base64_decode(urldecode($post_encoded_url));
            $comment_post_id = url_to_postid( $post_url );

            if ($comment_post_id > 0){
                // Insert new comments
                if (isset($status->new)){
                    foreach($status->new as $new_comment) {
                        // Adjust to local timezone (comment timezone in GMT)
                        $date_local = strtotime("{$date_timezone} hour", $new_comment->created);

                        $data = array( 
                            'comment_post_ID' => $comment_post_id, 
                            'comment_author' => mysql_real_escape_string($new_comment->author_name), 
                            'comment_author_email' => (isset($new_comment->author_email))? mysql_real_escape_string($new_comment->author_email):'', 
                            'comment_author_url' => '', 
                            'comment_content' => mysql_real_escape_string($new_comment->body), 
                            'comment_type' => '', 
                            'comment_parent' => 0, 
                            'user_id' => 0, 
                            'comment_author_IP' => '127.0.0.1', 
                            'comment_agent' => 'NNX', 
                            'comment_date' => date( 'Y-m-d H:i:s', $date_local), 
                            'comment_approved' => (bool)$new_comment->approved? 1 : 0,
                        ); 

                        $batch_count++;
                        $nnx_new_id = nnx_encode_comment_id($comment_post_id, $new_comment->id);
                        nnx_insert_comment($nnx_new_id, $data);
                    }
                }

                if (isset($status->deleted)){
                    foreach($status->deleted as $deleted_id) {
                        $nnx_deleted_id = nnx_encode_comment_id($comment_post_id, $deleted_id);
                        nnx_delete_comment($nnx_deleted_id);
                        
                        $batch_count++;
                    }
                }

                if (isset($status->approved)){
                    foreach($status->approved as $approved_id) {
                        $nnx_approved_id = nnx_encode_comment_id($comment_post_id, $approved_id);
                        nnx_approve_unapprove_comment($nnx_approved_id, true);
                        
                        $batch_count++;
                    }
                }

                if (isset($status->unapproved)){
                    foreach($status->unapproved as $unapproved_id) {
                        $nnx_unapproved_id = nnx_encode_comment_id($comment_post_id, $unapproved_id);
                        nnx_approve_unapprove_comment($nnx_unapproved_id, false);
                        
                        $batch_count++;
                    }
                }
            }
        }

        // Update last import time
        if ( !empty($batch_last_id) ){
            update_option( 'nnx_last_comment_id', $batch_last_id );
        }
        update_option( 'nnx_last_down_time', $time_import );
    }
    
    return array("count"=>$batch_count, "last_id"=>$batch_last_id);
}

function nnx_insert_comment($nnx_comment_id, $wp_comment_data) {
    global $wpdb;
    
    // Prevent duplicate insertion
    if ( nnx_comment_is_duplicate($nnx_comment_id, $wp_comment_data) == false ){
        
        $new_comment_id = wp_insert_comment($wp_comment_data);
        if ($new_comment_id > 0) {
            $new_commentmeta_sql = "INSERT INTO {$wpdb->commentmeta} ( comment_id, meta_key, meta_value ) VALUES ( '$new_comment_id', 'nnx', '$nnx_comment_id' )";
            if( $wpdb->query($new_commentmeta_sql) !== NULL ) {
                return true;
            }
        }
    }  
    return false;
}

function nnx_delete_comment($nnx_comment_id) {
    global $wpdb;

    $comment_id = nnx_to_wp_comment_id($nnx_comment_id);
    if (!is_numeric($comment_id)) {
        return false;
    }

    $delete_comment_sql = "DELETE FROM {$wpdb->comments} WHERE comment_ID = {$comment_id}";
    $delete_commentmeta_sql = "DELETE FROM {$wpdb->commentmeta} WHERE comment_id = {$comment_id} AND meta_key = 'nnx' AND meta_value = '{$nnx_comment_id}'";

    if( $wpdb->query($delete_comment_sql) !== NULL && $wpdb->query($delete_commentmeta_sql) !== NULL ) {
        return true;
    }
    return false;
}

function nnx_approve_unapprove_comment($nnx_comment_id, $approve_status) {
    global $wpdb;

    $comment_id = nnx_to_wp_comment_id($nnx_comment_id);
    if (!is_numeric($comment_id)) {
        return false;
    }

    $approve_value = ($approve_status)? 1: 0;
    $update_comment_sql = "UPDATE {$wpdb->comments} SET comment_approved = $approve_value WHERE comment_ID = $comment_id";
    
    if ($wpdb->query($update_comment_sql) !== NULL) {
        return true;
    }

    return false;
}

function nnx_comment_is_duplicate($nnx_comment_id, $wp_comment_data){
    global $wpdb;
    $is_duplicate = false;
    
    if ( nnx_to_wp_comment_id($nnx_comment_id) > 0 ){
        $is_duplicate = true;
    }else{
        // Check existing wp comment
        $comment_time = strtotime($wp_comment_data['comment_date_gmt']);

        // Anonymous between Sept 18 to Oct 18 might be imported from WP
        if ( $wp_comment_data['comment_author'] == 'Anonymous' && $comment_time > 1347897601 && $comment_time < 1350575999 ){
            
            $where = "comment_approved = 1";
            $where .= $wpdb->prepare( ' AND comment_post_ID = %d', $wp_comment_data['comment_post_ID'] );
            $where .= $wpdb->prepare( ' AND comment_content = %s', $wp_comment_data['comment_content'] );
            $where .= $wpdb->prepare( ' AND comment_date_gmt = %s', $wp_comment_data['comment_date_gmt'] );

            $query = "SELECT COUNT(*) FROM {$wpdb->comments} WHERE {$where}";
            $comment = $wpdb->get_var( $query );
            if ($comment > 0){
                $is_duplicate = true;
            }
        }
    }
    
    return $is_duplicate;
}
?>
