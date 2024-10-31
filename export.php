<?php

function nnx_export_wp_comment() {

    global $wpdb;
    $status = false;
    $output = array();
    
    $last_comment_id = 0;
    $last_up_id = get_option('nnx_last_up_id');
    $total_comment = get_option('nnx_last_wp_comment_id');
    
    if ($last_up_id < $total_comment){
        
        $comments = $wpdb->get_results("
                                    SELECT comment_ID, comment_post_ID, UNIX_TIMESTAMP(comment_date) AS comment_date, 
                                        comment_author, comment_author_email, comment_content, post_title
                                    FROM {$wpdb->comments} AS comments, {$wpdb->posts} AS posts
                                    WHERE comment_approved = 1 AND comment_ID > {$last_up_id} 
                                        AND comment_ID <= {$total_comment} AND comment_type != 'pingback'
                                        AND posts.post_status = 'publish' AND posts.ID=comments.comment_post_ID
                                    ORDER BY comment_ID ASC
                                    LIMIT 50");
        
        foreach ($comments as $comment){
            $permalink = get_permalink($comment->comment_post_ID);
            $permalink_encoded = urlencode(base64_encode($permalink));
            
            $output[$permalink_encoded][] = array(
                                                "body" => urlencode($comment->comment_content),
                                                "author" => urlencode($comment->comment_author),
                                                "email" => urlencode($comment->comment_author_email),
                                                "time_stamp" => $comment->comment_date,
                                                "wp_comment_id" => $comment->comment_ID,
                                                "post_title" => $comment->post_title
                                            );
            $last_comment_id = $comment->comment_ID;
        }
        
        $output = json_encode($output);
        
        $data = array( 'api_key' => get_option('nnx_api_key'),
                       'comments' => $output );

        // Upload comment
        $http = new WP_Http();
        $response = $http->request( NNX_EXPORT_COMMENT,
                        array(
                            'timeout' => 30,
                            'method' => 'POST',
                            'body' => array(
                                'api_key' => get_option('nnx_api_key'),
                                'comments' => $output
                            )
                        )
                    );
        
        if (is_wp_error($response)){
            update_option('nnx_debug', "Export : [".current_time("mysql", true)."] ".$response->get_error_message());
            
            $last_comment_id = $last_up_id;
        }else{
            $reply = json_decode($response['body']);
            $status = (bool)$reply->success;
            update_option('nnx_debug', "Export : [".current_time("mysql", true)."] ".$response['body']);

            if ($status){
                // Update nnx_last_up_id
                update_option('nnx_last_up_id', $reply->id_last);
                $last_comment_id = $reply->id_last;
            }
        }

    }else{
        $last_comment_id = $last_up_id;
        
        if ( wp_next_scheduled('nnx_export_cron') ){
            wp_clear_scheduled_hook('nnx_export_cron');
        }
    }

    return array("success"=>$status, "id_last"=>$last_comment_id, "total"=>$total_comment);
}

    
?>
