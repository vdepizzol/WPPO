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
    
    global $wpdb, $wppo_update_message, $wppo_error;
    
    /*
     * Admin actions
     */
    
    if(isset($_GET['generatepot']) && $_GET['generatepot']) {
        wppo_update_pot();
        update_option('wppo_last_pot_generate', time());
        $wppo_update_message = 'Changes successfully sent to translators.';
    }
    
    if(isset($_GET['checkforlangupdates']) && $_GET['checkforlangupdates']) {
        $number_of_changed_po_files = wppo_check_for_po_changes();
        
        if ($number_of_changed_po_files == 0) {
            $wppo_update_message = 'No new translations found.';
        } elseif ($number_of_changed_po_files === 1) {
            $wppo_update_message = $number_of_changed_po_files . ' translation file updated.';
        } else {
            $wppo_update_message = $number_of_changed_po_files . ' translation files updated.';
        }
    }
    
    /*
     * Enables or disables the language
     */
     
    if (isset($_GET['action']) && $_GET['action'] == 'changelanguagestatus' && isset($_GET['lang_code']) && isset($_GET['lang_status'])) {
        $wpdb->update(WPPO_PREFIX.'languages', array( 'lang_status' => ($_GET['lang_status'] == 1) ? 'visible' : 'hidden'), array( 'lang_code' => $_GET['lang_code'] ));
        
        $lang_name = $wpdb->get_var('SELECT lang_name FROM '.WPPO_PREFIX.'languages WHERE lang_code = \''.mysql_real_escape_string($_GET['lang_code'])."'");
        
        if ($_GET['lang_status'] == '0') {
            $wppo_update_message = $lang_name.' disabled.';
        } else {
            $wppo_update_message = $lang_name.' enabled.';
        }
        
    }
    
    /*
     * Deletes a language
     */
    
    if (isset($_GET['action']) && $_GET['action'] == 'deletelanguage' && isset($_GET['lang_code'])) {
        
        $lang_attr = $wpdb->get_row('SELECT lang_code, lang_name FROM '.WPPO_PREFIX.'languages WHERE lang_code = \''.mysql_real_escape_string($_GET['lang_code'])."'");
        
        if(isset($lang_attr->lang_code)) {
        
            $wpdb->query("DELETE FROM ".WPPO_PREFIX.'languages'." ".
                         "WHERE lang_code = '".mysql_real_escape_string($_GET['lang_code'])."'");
        
            $wppo_update_message = $lang_attr->lang_name . " deleted.";
            
        } else {
            
            $wppo_update_message = "Language <code>".$_GET['lang_code']."</code> doesn't exists.";
            
        }
    }
    
    /*
     * Add a language
     */
    
    if (isset($_GET['action']) && $_GET['action'] == 'addlanguage' && $_POST['lang_code'] != '' && $_POST['lang_name'] != '') {
        
        $verify_lang = $wpdb->get_row('SELECT lang_code FROM '.WPPO_PREFIX.'languages WHERE lang_code = \''.mysql_real_escape_string($_POST['lang_code'])."'");
        
        if (!isset($verify_lang) && $_POST['lang_code'] != WPPO_DEFAULT_LANGUAGE_CODE) {
        
            $wpdb->insert(WPPO_PREFIX.'languages', array('lang_code' => $_POST['lang_code'], 'lang_name' => $_POST['lang_name']), array('%s', '%s'));
            
            
            /*
             * We need to have at least one registry for statistics
             * in each post type.
             */
            
            // static 
            if ($wpdb->get_var("SELECT lang FROM ".WPPO_PREFIX.'translation_log'." WHERE post_type = 'static' AND lang = '".mysql_real_escape_string($_POST['lang_code'])."'") == null) {

                if (file_exists(WPPO_DIR."static.pot")) {
                    $default_status['static'] =  POParser::stats(WPPO_DIR."static.pot");
                } else {
                    $default_status['static']['untranslated'] = 0;
                }
                
                $wpdb->insert(WPPO_PREFIX.'translation_log', array('lang' => $_POST['lang_code'],
                                                                   'post_type' => 'static',
                                                                   'translation_date' => time(),
                                                                   'translated' => '0',
                                                                   'fuzzy' => '0',
                                                                   'untranslated' => $default_status['static']['untranslated']));
                                                               
            }
            
            // dynamic
            if ($wpdb->get_var("SELECT lang FROM ".WPPO_PREFIX.'translation_log'." WHERE post_type = 'dynamic' AND lang = '".mysql_real_escape_string($_POST['lang_code'])."'") == null) {
                
                if (file_exists(WPPO_DIR."dynamic.pot")) {
                    $default_status['dynamic'] =  POParser::stats(WPPO_DIR."dynamic.pot");
                } else {
                    $default_status['dynamic']['untranslated'] = 0;
                }
                
                $wpdb->insert(WPPO_PREFIX.'translation_log', array('lang' => $_POST['lang_code'],
                                                                   'post_type' => 'dynamic',
                                                                   'translation_date' => time(),
                                                                   'translated' => '0',
                                                                   'fuzzy' => '0',
                                                                   'untranslated' => $default_status['dynamic']['untranslated']));
            }

            /*
             * We'll try to download WordPress locale based on lang_code
             */

            $locale_url = "http://svn.automattic.com/wordpress-i18n/";

            if (!file_exists(ABSPATH."wp-content/languages/".$_POST['lang_code'].'.mo')) {
                
                $lang_code = $_POST['lang_code'];
                if (strlen($lang_code) == 5) {
                    $alt_lang_code = substr($lang_code, 0, 2);
                } elseif (strlen($lang_code) == 2) {
                    $alt_lang_code = $lang_code.'_'.strtoupper($lang_code);
                }

                if(!$open_mo = @fopen($locale_url.$lang_code    .'/tags/'.$GLOBALS['wp_version'].'/messages/'.$lang_code.'.mo', 'r'))
                if(!$open_mo = @fopen($locale_url.$alt_lang_code.'/tags/'.$GLOBALS['wp_version'].'/messages/'.$alt_lang_code.'.mo', 'r'))
                if(!$open_mo = @fopen($locale_url.$lang_code    .'/branches/'.$GLOBALS['wp_version'].'/messages/'.$lang_code.'.mo', 'r'))
                if(!$open_mo = @fopen($locale_url.$alt_lang_code.'/branches/'.$GLOBALS['wp_version'].'/messages/'.$alt_lang_code.'.mo', 'r'))
                if(!$open_mo = @fopen($locale_url.$lang_code    .'/branches/'.$GLOBALS['wp_version'].'/'.$lang_code.'.mo', 'r'))
                if(!$open_mo = @fopen($locale_url.$alt_lang_code.'/branches/'.$GLOBALS['wp_version'].'/'.$alt_lang_code.'.mo', 'r'))
                if(!$open_mo = @fopen($locale_url.$lang_code    .'/trunk/messages/'.$lang_code.'.mo', 'r'))
                if(!$open_mo = @fopen($locale_url.$alt_lang_code.'/trunk/messages/'.$alt_lang_code.'.mo', 'r')) {

                    $open_mo = false;
                    
                }

                if ($open_mo) {
                    $mo_content = stream_get_contents($open_mo);
                    file_put_contents(ABSPATH."wp-content/languages/".$lang_code.'.mo', $mo_content);
                }

            }
            
            $wppo_update_message = htmlspecialchars($_POST['lang_name'])." added.";
            
        } else {
            
            $wppo_update_message = "Language <code>".$_POST['lang_code']."</code> already exists.";
            
        }
    }
    
    
    
    
    if (isset($wppo_update_message) && $wppo_update_message != '') {
        add_action('admin_notices', function() {
            global $wppo_update_message;
            echo "<div class=\"updated fade\"><p>".$wppo_update_message."</p></div>";
        });
    }
    
    /*
     * XML2PO Error display
     */
    if (count($wppo_error) > 0) {
        add_action('admin_notices', function() {
            global $wppo_error;
            echo '<div class="updated error fade">';
            
            foreach ($wppo_error as $action => $value) {
                
                if($action == 'po2xml') {
                    
                    echo '<p>There were some problems on handling the following PO file(s):</p>';
                    
                    echo '<ul>';
                    foreach ($value as $coverage => $array) {
                        foreach($array as $lang => $output) {
                            echo '<li>'.$coverage.'/'.$lang.'.po<br /><pre><code style="display: block;">'.htmlspecialchars($output).'</code></pre></li>';
                        }
                    }
                    echo '</ul>';
                    
                } elseif ($action == 'xml2pot') {
                    
                    echo '<p>There were some problems on generating the following POT file:</p>';
                    
                    echo '<ul>';
                    foreach ($value as $coverage => $output) {
                        echo '<li>'.$coverage.'.pot<br /><pre><code style="display: block;">'.htmlspecialchars($output).'</code></pre></li>';
                    }
                    echo '</ul>';
                    
                } elseif ($action == 'msgfmt') {
                    
                    echo '<p>There were some problems on compiling the following MO file:</p>';
                    
                    echo '<ul>';
                    foreach ($value as $coverage => $output) {
                        echo '<li>'.$coverage.'.pot<br /><pre><code style="display: block;">'.htmlspecialchars($output).'</code></pre></li>';
                    }
                    echo '</ul>';
                }
                
            }
            
            echo '</div>';
        });
    }
    
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

        
        $posts = $wpdb->get_results("SELECT {$wpdb->posts}.ID, {$wpdb->posts}.post_parent, {$wpdb->posts}.post_modified_gmt, DATE_FORMAT({$wpdb->posts}.post_modified_gmt, '%Y/%m/%d') as date, display_name as author FROM {$wpdb->posts} ".
                                    "LEFT JOIN {$wpdb->users} ON {$wpdb->posts}.post_author = {$wpdb->users}.ID ".
                                    "WHERE post_type IN ('".implode("','", $supported_post_types)."') ".
                                    "AND {$wpdb->posts}.post_modified_gmt != '0000-00-00 00:00:00' ".
                                    "ORDER BY post_modified_gmt DESC LIMIT 0,30");
        
        
        $grouped_posts = array();
        
        $last_post = array();
        
        $last_pot_generation_date = date("Y-m-d H:i:s", get_option('wppo_last_pot_generate'));
        
        foreach ($posts as $post) {
            
            if (empty($post->post_parent)) {
                $post->post_parent = $post->ID;
            }
            
            /*
             * This verifies if we should group posts that have a POT generation between them
             * 
             * For example, if a page is edited 4 times and after 3 editions the
             * POT file was regenerated, we will group the 3 editions and the last
             * one will be separated, as there will be a "pot edition" item in the list.
             * 
             */
            if (
                isset($last_post->post_modified_gmt) && isset($post->post_modified_gmt) &&
                (
                    ($last_post->post_modified_gmt > $last_pot_generation_date && $post->post_modified_gmt > $last_pot_generation_date)
                    ||
                    ($last_post->post_modified_gmt < $last_pot_generation_date && $post->post_modified_gmt < $last_pot_generation_date)
                )
            ) {
                $pot_generation_separate = false;
            } else {
                $pot_generation_separate = true;
            }
            
            if (isset($last_post->post_parent) && $last_post->post_parent == $post->post_parent && $pot_generation_separate == false) {
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
                    $group[0]->type = 'created';
                } else {
                    $previous_revision = wppo_get_previous_revision($group[0]->ID);
                    $group[0]->link = 'revision.php?action=diff&post_type='.get_post_type($group[0]->post_parent).'&right='.$previous_revision.'&left='.$group[0]->ID;
                    $group[0]->type = 'edited';
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
        
        
        /*
         * Load the languages ​​and the percentage translated of each
         */
        $languages = $wpdb->get_results("SELECT langs.*, ".
                                            "(SELECT ROUND(((log1.translated+log2.translated)/(log1.translated+log2.translated+log1.untranslated+log2.untranslated+log1.fuzzy+log2.fuzzy))*100) ".
                                            "FROM ".WPPO_PREFIX."translation_log log1, ".WPPO_PREFIX."translation_log log2 ".
                                            "WHERE log1.lang = langs.lang_code AND log2.lang = langs.lang_code AND ".
                                            "log1.post_type = 'dynamic' AND log2.post_type = 'static' ".
                                            "ORDER BY log1.translation_date DESC, log2.translation_date DESC ".
                                            "LIMIT 1) AS percent ".
                                        "FROM ".WPPO_PREFIX."languages langs ORDER BY percent DESC, lang_name ASC");

        foreach ($languages as $i => $language) {

            if (!file_exists(ABSPATH.'wp-content/languages/'.$language->lang_code.'.mo')) {
                $languages[$i]->lacks_wp_mo = true;
            } else {
                $languages[$i]->lacks_wp_mo = false;
            }


            if (!file_exists(get_stylesheet_directory().'/languages/'.$language->lang_code.'.mo')) {
                $languages[$i]->lacks_theme_mo = true;
            } else {
                $languages[$i]->lacks_theme_mo = false;
            }
            
        }
        
        echo wppo_tpl_parser('admin/index', array('grouped_posts' => $grouped_posts, 'languages' => $languages)); 
        
    });    
});
