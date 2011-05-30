<?php

function wppo_tpl_parser($view, $vars){
    extract($vars);
    ob_start();
    ob_implicit_flush(false);
    require($view.".php");
    return ob_get_clean();
};

function wppo_get_previous_revision($id) {
    
    global $wpdb;
    
    $post_parent = $wpdb->get_var("SELECT {$wpdb->posts}.post_parent FROM {$wpdb->posts} ".
                                  "WHERE {$wpdb->posts}.ID = ".mysql_real_escape_string($id));
                                  
    if (empty($post_parent)) {
        
        $previous_revision =  $wpdb->get_var("SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} ".
                                             "WHERE post_type = 'revision' ".
                                             "AND {$wpdb->posts}.post_parent = ".mysql_real_escape_string($id)." ".
                                             "ORDER BY {$wpdb->posts}.post_modified_gmt DESC LIMIT 1");
         
    } else {
    
        $previous_revision =  $wpdb->get_var("SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} ".
                                             "WHERE post_type = 'revision' ".
                                             "AND {$wpdb->posts}.ID < ".mysql_real_escape_string($id)." ".
                                             "AND {$wpdb->posts}.post_parent = ".mysql_real_escape_string($post_parent)." ".
                                             "ORDER BY {$wpdb->posts}.post_modified_gmt DESC LIMIT 1");
    
    }
    
    if($previous_revision) {
        return $previous_revision;
    }
    
    return $post_parent;
    
}

function wppo_is_first_revision($id) {
    
    global $wpdb;
    
    $post_parent = $wpdb->get_var("SELECT {$wpdb->posts}.post_parent FROM {$wpdb->posts} ".
                                  "WHERE {$wpdb->posts}.ID = ".mysql_real_escape_string($id));
    
    if (empty($post_parent)) {
        return false;
    }
    
    $first_revision =  $wpdb->get_var("SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} ".
                                         "WHERE post_type = 'revision' ".
                                         "AND {$wpdb->posts}.post_parent = ".mysql_real_escape_string($post_parent)." ".
                                         "ORDER BY {$wpdb->posts}.post_modified_gmt ASC LIMIT 1");
    
    if ($id == $first_revision) {
        return true;
    }
    
    return false;
}


add_action('admin_menu', function() {
    
    add_menu_page('Translations', 'Translations', 'manage_options', 'wppo', function() {
        
        global $wpdb;
                        
        /*
         * We'll only get the results from post_types that
         * support revisions
         */
        $supported_post_types = get_post_types();
        foreach ($supported_post_types as $i => $post_type) {
            if (!post_type_supports($i, 'revisions')) {
                unset($supported_post_types[$i]);
            }
        }                        
        $supported_post_types['revision'] = 'revision';

        
        $posts = $wpdb->get_results("SELECT {$wpdb->posts}.ID, {$wpdb->posts}.post_parent, {$wpdb->posts}.post_modified_gmt, display_name as author FROM {$wpdb->posts} ".
                                    "LEFT JOIN {$wpdb->users} ON {$wpdb->posts}.post_author = {$wpdb->users}.ID ".
                                    "WHERE post_type IN ('".implode("','", $supported_post_types)."') ".
                                    "ORDER BY post_modified_gmt DESC LIMIT 0,30");
        
        
        $grouped_posts = array();
        
        $last_post = array();
        
        foreach ($posts as $post) {
            
            if (empty($post->post_parent)) {
                $post->post_parent = $post->ID;
            }
            
            if (isset($last_post->post_parent) && $last_post->post_parent == $post->post_parent) {
                $grouped_posts[ count($grouped_posts)-1 ][] = $post;
            } else {
                $grouped_posts[][] = $post;
            }
            
            $last_post = $post;
        }
        
        foreach ($grouped_posts as $group) {
            
            if (count($group) === 1) {
                if (empty($group[0]->post_parent)) {
                    $group[0]->post_parent = $group[0]->ID;
                }
                
                $first_revision = wppo_is_first_revision($group[0]->ID);
                
                if ($first_revision) {
                    $group[0]->link = 'revision.php?action=edit&revision='.$group[0]->ID;
                    $group[0]->type = 'created ';
                } else {
                    $previous_revision = wppo_get_previous_revision($group[0]->ID);
                    $group[0]->link = 'revision.php?action=diff&post_type='.get_post_type($group[0]->post_parent).'&right='.$previous_revision.'&left='.$group[0]->ID;
                    $group[0]->type = 'edited ';
                }
                $group[0]->by = ' by '.$group[0]->author;
            } else {
                
                $number_of_edits = count($group);
                
                $temp_max_id = 0;
                $temp_min_id = 0;
                
                foreach($group as $post) {
                    $temp_post_ids[] = $post->ID;
                    $temp_post_authors[] = $post->author;
                }
                
                foreach($group as $post) {
                    if($post->ID != $post->post_parent) {
                        if($temp_max_id < $post->ID) {
                            $temp_max_id = $post->ID;
                        }
                    } else {
                        $temp_max_id = $post->ID;
                        break;
                    }
                }
                foreach($group as $post) {
                    if($post->ID != $post->post_parent) {
                        if($temp_min_id > $post->ID || $temp_min_id == 0) {
                            $temp_min_id = $post->ID;
                        }
                    }
                }
                
                $group[0]->link = 'revision.php?action=diff&post_type='.get_post_type($group[0]->post_parent).'&right='.$temp_min_id.'&left='.$temp_max_id;
                $group[0]->type = 'edited ';
                
                if ($number_of_edits === 1) {
                    $times = 'once';
                } elseif ($number_of_edits == 2) {
                    $times = 'twice';
                } else {
                    $times = $number_of_edits . ' times';
                }
                
                $group[0]->by = $times.' by '.implode(', ', array_unique($temp_post_authors));
            }
            
        }
        
        if(isset($_GET['generate']) && $_GET['generate'])
        {
            wppo_update_pot();
            update_option('wppo_last_pot_generate', time()); 
        }
        
        /*
         * Enables or disables the language
         */
         
        if(isset($_GET['lang_code']) && isset($_GET['lang_status']))
        {
            $wpdb->update('wppo_languages', array( 'lang_status' => ($_GET['lang_status'] == 1) ? 'visible' : 'hidden'), array( 'lang_code' => $_GET['lang_code'] ));
        }
        
        /*
         * Load the languages ​​and the percentage translated of each
         */
        $languages = $wpdb->get_results("SELECT langs.*, ".
                                        "(SELECT ROUND(((log1.translated+log2.translated)/(log1.translated+log2.translated+log1.untranslated+log2.untranslated+log1.fuzzy+log2.fuzzy))*100) ".
                                        "FROM wppo_translation_log log1, wppo_translation_log log2 ".
                                        "WHERE log1.lang = langs.lang_code AND log2.lang = langs.lang_code AND ".
                                        "log1.post_type = 'posts' AND log2.post_type = 'pages' ".
                                        "ORDER BY log1.translation_date DESC, log2.translation_date DESC ".
                                        "LIMIT 1) AS percent ".
                                        "FROM wppo_languages langs ORDER BY lang_name ASC");
        
        echo wppo_tpl_parser('admin/wppo', array('grouped_posts' => $grouped_posts, 'languages' => $languages)); 
        
    });
    
    /*add_action('admin_notices', function() {
        echo "<div class=\"updated fade\"><p>WPPO is active!</p></div>";
    }); */
    
});

