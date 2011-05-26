<?php

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
        
        ?>
        <div class="wrap">
            <div id="icon-edit-pages" class="icon32"><br /></div>
            <h2>Translations</h2>
            
            <div class="metabox-holder">
                <div class="postbox">
                    <h3>Recent activities</h3>
                    <div class="inside" style="padding: 15px;">
                        
                        <?php
                        
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
                        $supported_post_types['revisions'] = 'revision';

                        
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
                            
                            echo '<li>';
                            if (count($group) === 1) {
                                if (empty($group[0]->post_parent)) {
                                    $group[0]->post_parent = $group[0]->ID;
                                }
                                
                                $first_revision = wppo_is_first_revision($group[0]->ID);
                                
                                if ($first_revision) {
                                    $link = 'revision.php?action=edit&revision='.$group[0]->ID;
                                    echo 'created ';
                                } else {
                                    $previous_revision = wppo_get_previous_revision($group[0]->ID);
                                    $link = 'revision.php?action=diff&post_type='.get_post_type($group[0]->post_parent).'&right='.$previous_revision.'&left='.$group[0]->ID;
                                    echo 'edited ';
                                }
                                
                                echo '<a href="'.$link.'">'.get_the_title($group[0]->post_parent).'</a> ';
                                
                                echo ' by '.$group[0]->author;
                                
                                //echo ' on '.$group[0]->post_modified_gmt;
                                
                                
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
                                $temp_post_authors = array_unique($temp_post_authors);
                                
                                $link = 'revision.php?action=diff&post_type='.get_post_type($group[0]->post_parent).'&right='.$temp_min_id.'&left='.$temp_max_id;
                                echo 'edited ';
                                echo '<a href="'.$link.'">'.get_the_title($group[0]->post_parent).'</a> ';
                                
                                if ($number_of_edits === 1) {
                                    echo 'once';
                                } elseif ($number_of_edits == 2) {
                                    echo 'twice';
                                } else {
                                    echo $number_of_edits . ' times';
                                }
                                
                                echo ' by '.implode(', ', $temp_post_authors);
                            }
                            echo '</li>';
                            
                        }
                        
                        ?>
                        
                    </div>
                </div>
            </div>
        </div>
        <?php
        
    });
    
    /*add_action('admin_notices', function() {
        echo "<div class=\"updated fade\"><p>WPPO is active!</p></div>";
    }); */
    
});

?>
