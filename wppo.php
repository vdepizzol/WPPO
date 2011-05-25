<?php
/*
Plugin Name: WPPO
Description: Gettext layer for WordPress content
Author: Vinicius Depizzol
Author URI: http://vinicius.depizzol.com.br
License: AGPLv3
*/

/* Copyright 2011  Vinicius Depizzol <vdepizzol@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!function_exists('_log')) {
    function _log( $message ) {
        if (WP_DEBUG === true) {
            if ( is_array($message) || is_object($message)) {
                file_put_contents(ABSPATH.'debug.log', print_r($message, true)."\n", FILE_APPEND);
            } else {
                file_put_contents(ABSPATH.'debug.log', $message."\n" , FILE_APPEND);
            }
        }
    }
}


/* 
 * Folder structure:
 * /wppo/[post_type]/[file_type]/[lang].[ext]
 * 
 * Example:
 * /wppo/pages/po/fr.po
 * /wppo/posts/xml/pt_BR.xml
 * 
 * Global POT file:
 * /wppo/posts.pot
 * /wppo/pages.pot
 * 
 * Global XML file:
 * /wppo/posts.xml
 * /wppo/pages.xml
 * 
 */

define('WPPO_DIR', ABSPATH . "wppo/");
define('WPPO_PREFIX', "wppo_");
define('WPPO_XML2PO_COMMAND', "/usr/bin/xml2po");

$wppo_cache = array();


require_once dirname(__FILE__).'/backend.php';

if (is_admin()) {
    require_once dirname(__FILE__).'/admin.php';
}

/*
 * Install WPPO Plugin
 */

function wppo_install() {
    
    global $wpdb;
    
    $tables = array(
        'posts' =>              "CREATE TABLE `".WPPO_PREFIX."posts` (
                                  `wppo_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                  `post_id` bigint(20) unsigned NOT NULL,
                                  `lang` varchar(10) NOT NULL,
                                  `translated_title` text NOT NULL,
                                  `translated_excerpt` text NOT NULL,
                                  `translated_name` varchar(200) NOT NULL,
                                  `translated_content` longtext NOT NULL,
                                  PRIMARY KEY (`wppo_id`),
                                  KEY `post_id` (`post_id`)
                                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;",
        
        
        'languages' =>          "CREATE TABLE `".WPPO_PREFIX."languages` (
                                  `lang_code` varchar(10) NOT NULL,
                                  `lang_name` varchar(100) NOT NULL,
                                  `lang_status` enum('visible', 'hidden') NOT NULL,
                                  PRIMARY KEY ( `lang_code` )
                                ) ENGINE=MYISAM DEFAULT CHARSET=latin1 ;",
        
        
        'translation_log' =>    "CREATE TABLE `".WPPO_PREFIX."translation_log` (
                                  `log_id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                  `lang` varchar(10) NOT NULL,
                                  `post_type` varchar( 10 ) NOT NULL,
                                  `translation_date` timestamp NOT NULL,
                                  `strings_translated` double NOT NULL,
                                  `strings_fuzzy` double NOT NULL,
                                  `strings_untranslated` double NOT NULL,
                                ) ENGINE=MYISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;"
    );
    
    foreach ($tables as $name => $sql) {
        if ($wpdb->get_var("SHOW TABLES LIKE '".WPPO_PREFIX."{$name}'") != WPPO_PREFIX.$name) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    
    /*
     * Create WPPO directories
     */
    $directories_for_post_types = array('pages', 'posts');
    $directories_for_formats = array('po', 'xml');
    
    if (!is_dir(WPPO_DIR)) {
        
        if (!is_writable(ABSPATH)) {
            die("Please, make ".ABSPATH." a writeable directory or create manually a folder called “wppo” with chmod 0755 on it.");
        }
        
        if (!mkdir(WPPO_DIR, 0755)) {
            die("The plugin tried but couldn't create the directory “".WPPO_DIR."”. Please, create it manually with chmod 0755.");
        }
    }
    
    if (!is_writable(WPPO_DIR)) {
        die("The directory ".WPPO_DIR." must be writeable.");
    }
    
    foreach ($directories_for_post_types as $directory) {
        if (!is_dir(WPPO_DIR.'/'.$directory)) {
            if (!mkdir(WPPO_DIR.'/'.$directory, 0755)) {
                die("The plugin tried but couldn't create the directory “".WPPO_DIR.'/'.$directory."”. Please, create it manually with chmod 0755.");
            }
        } else {
            if (!is_writable(WPPO_DIR."/".$directory)) {
                die("All the folders inside ".WPPO_DIR." should be writeable.");
            }
        }
                
        foreach ($directories_for_formats as $format) {
            if (!is_dir(WPPO_DIR.'/'.$directory.'/'.$format)) {
                if (!mkdir(WPPO_DIR.'/'.$directory.'/'.$format, 0755)) {
                    die("The plugin tried but couldn't create the directory “".WPPO_DIR.'/'.$directory.'/'.$format."”. Please, create it manually with chmod 0755.");
                }
            } else {
                if (!is_writable(WPPO_DIR."/".$directory."/".$format)) {
                    die("All the folders inside ".WPPO_DIR." should be writeable.");
                }
            }
        }
    }
    
    /*
     * Add the default plugin options
     */
    
    // Number of old POT files we'll keep in the pot_archive folder
    // FIXME
    add_option("wppo_pot-cache-number", 5);
    
    // Limit of date to select news to translate. Occasionally people
    // want to disable the translation of old news.
    // FIXME
    add_option("wppo_old-news-limit", strtotime('now - 4 months'));
    
    
    /*
     * Check for existing translations
     */
    // FIXME
    //wppo_update_pot_file();
    
}
register_activation_hook(__FILE__, 'wppo_install');


function wppo_uninstall() {
    
    global $wpdb;
    
    /*
     * Drop existing tables
     */
    
    $tables = array('posts', 'languages', 'translation_log');
    
    foreach ($tables as $index => $name) {
        $tables[$index] = WPPO_PREFIX.$name;
    }
    
    $wpdb->query("DROP TABLE IF EXISTS ".implode(", ", $tables));
    
    
    /*
     * We won't delete any wppo files here.
     */
    
}
register_deactivation_hook(__FILE__, 'wppo_uninstall');





add_filter('the_title', function($title, $id) {
    global $wppo_cache;
    
    $translated_title = trim(wppo_get_translated_data('translated_title', $id));

    if (empty($translated_title)) {
        return $title;
    } else {
        return $translated_title;
    }
}, 10, 2);

add_filter('the_content', function($content) {
    global $wppo_cache, $post;

    if (isset($wppo_cache['posts'][$post->ID])) {
        $translated_content = $wppo_cache['posts'][$post->ID]['translated_content'];
    } else {
        $translated_content = trim(wppo_get_translated_data('translated_content', $post->ID));
    }

    if (empty($translated_content)) {
        return $content;
    } else {
        return $translated_content;
    }
}, 10, 1);

add_filter('get_pages', function($pages) {
    
    global $wppo_cache, $wpdb;

    $lang = wppo_get_lang();

    foreach ($pages as $index => $page) {
        if (!isset($wppo_cache['posts'][$page->ID]) && $lang != 'C') {
            $wppo_cache['posts'][$page->ID] = $wpdb->get_row("SELECT * FROM " . WPPO_PREFIX . "posts ".
                                                             "WHERE post_id = '" . mysql_real_escape_string($page->ID) . "' ".
                                                             "AND lang = '" . mysql_real_escape_string($lang) . "'", ARRAY_A);
        }
        
        if (isset($wppo_cache['posts'][$page->ID]) && is_array($wppo_cache['posts'][$page->ID])) {
            $pages[$index]->post_title   = $wppo_cache['posts'][$page->ID]['translated_title'];
            $pages[$index]->post_name    = $wppo_cache['posts'][$page->ID]['translated_name'];
            $pages[$index]->post_content = $wppo_cache['posts'][$page->ID]['translated_content'];
            $pages[$index]->post_excerpt = $wppo_cache['posts'][$page->ID]['translated_excerpt'];
        }
    }
    
    return $pages;   
     
}, 1);


add_filter('wp_get_nav_menu_items', function($items) {
    
    global $wppo_cache, $wpdb;

    $lang = wppo_get_lang();
    
    foreach ($items as $index => $item) {
        
        $post_id = get_post_meta($item->ID, '_menu_item_object_id');
        $post_id = $post_id[0];
            
        if ($post_id != $item->ID) {
            
            if (!isset($wppo_cache['posts'][$post_id]) && $lang != 'C') {
                $wppo_cache['posts'][$post_id] = $wpdb->get_row("SELECT * FROM " . WPPO_PREFIX . "posts ".
                                                                "WHERE post_id = '" . mysql_real_escape_string($post_id) . "' ".
                                                                "AND lang = '" . mysql_real_escape_string($lang) . "'", ARRAY_A);
            }
            
            if (isset($wppo_cache['posts'][$post_id]) && is_array($wppo_cache['posts'][$post_id])) {
                  $items[$index]->post_title   = $wppo_cache['posts'][$post_id]['translated_title'];
                  $items[$index]->title        = $wppo_cache['posts'][$post_id]['translated_title'];
                  $items[$index]->post_name    = $wppo_cache['posts'][$post_id]['translated_name'];
                  $items[$index]->post_content = $wppo_cache['posts'][$post_id]['translated_content'];
                  $items[$index]->post_excerpt = $wppo_cache['posts'][$post_id]['translated_excerpt'];
            }
        }
        
    }
    
    return $items;
});


/*
 * Support for search in translated posts
 */

add_filter('posts_search', function($search) {
    
    global $wpdb;
    
    $lang = wppo_get_lang();
    
    if ($lang != 'C') {
        $search = str_replace("({$wpdb->posts}.post_content LIKE ", "(".WPPO_PREFIX."posts.translated_content LIKE ", $search);
        $search = str_replace("({$wpdb->posts}.post_title LIKE ",   "(".WPPO_PREFIX."posts.translated_title LIKE ", $search);
    }
    
    return $search;
    
});

add_filter('posts_clauses', function($clauses) {
    
    global $wpdb;
    
    $lang = wppo_get_lang();
    
    if ($lang != 'C') {
        $clauses['join'] = "LEFT JOIN ".WPPO_PREFIX."posts ON $wpdb->posts.ID = ".WPPO_PREFIX."posts.post_id ".
                           "AND ".WPPO_PREFIX."posts.lang = '".mysql_real_escape_string($lang)."'";
    }
    
    return $clauses;
    
});


/*
 * Main function to return the current defined language
 */

function wppo_get_lang() {
    
    global $wpdb, $wppo_cache;
    
    
    if (isset($wppo_cache['lang'])) {
        
        return $wppo_cache['lang'];
        
    } elseif (isset($_REQUEST['lang'])) {
        
        $defined_lang = $_REQUEST['lang'];
        
    } elseif (isset($_SESSION['lang'])) {
        
        $defined_lang = $_SESSION['lang'];
        
    }
    
    if (isset($defined_lang)) {
        
        /*
         * Verify if the selected language really exists
         */
        
        $check_lang = $wpdb->get_row(
            "SELECT lang_code, lang_name FROM ".WPPO_PREFIX."languages ".
            "WHERE lang_status = 'visible' AND lang_code = '".mysql_real_escape_string($defined_lang)."'", ARRAY_A
        );

        if ($wpdb->num_rows === 1) {
            $wppo_cache['lang'] = $defined_lang;
            return $defined_lang;
        }
        
    }
    
    /*
     * Since at this point no one told us what language the user wants to see the content,
     * we'll try to guess from the HTTP headers
     */
    if (!function_exists('get_http_accept_lang')) {

        function get_http_accept_lang() {
            $langs = array();

            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                
                $accept_lang_underscore = str_replace('-', '_', $_SERVER['HTTP_ACCEPT_LANGUAGE']); 
            
                preg_match_all('/([a-z]{1,8}(_[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $accept_lang_underscore, $lang_parse);

                if (count($lang_parse[1])) {
                    $langs = array_combine($lang_parse[1], $lang_parse[4]);
                	
                    foreach ($langs as $lang => $val) {
                        if ($val === '') {
                            $langs[$lang] = 1;
                        }
                    }

                    arsort($langs, SORT_NUMERIC);
                    
                    $langs = array_keys($langs);
                    
                    /*
                     * If the first language available also contains the country code,
                     * we'll automatically add as a second option the same language without the country code
                     * (only if it doesn't already exists)
                     */
                    if ((strpos($langs[0], '_') !== false) && (!isset($langs[1]) || substr($langs[0], 0, 2) != substr($langs[1], 0, 2))) {
                        $default_lang = array_shift($langs); 
                        $fallback_lang = explode('_', $default_lang);
                        array_unshift($langs, $fallback_lang[0]);
                        array_unshift($langs, $default_lang);
                    }
                }
            }
            
            return $langs;
        }
    }
    
    /*
     * Here we'll keep in cache all the existing languages in database.
     * We use it here to check which desired language from browser exists and is available.
     */
    if (!isset($wppo_cache['available_lang'])) {
        $all_languages = $wpdb->get_results("SELECT lang_code, lang_name FROM ".WPPO_PREFIX."languages WHERE lang_status = 'visible'", ARRAY_A);
        
        foreach ($all_languages as $index => $array) {
            $wppo_cache['available_lang'][$array['lang_code']] = $array['lang_name'];
        }
    }
    
    $user_lang = get_http_accept_lang();
    
    foreach ($user_lang as $lang_code) {
        if (isset($wppo_cache['available_lang'][$lang_code])) {
            $defined_lang = $lang_code;
            break;
        }
    }
    
    if (isset($defined_lang)) {
        $wppo_cache['lang'] = $defined_lang;
    } else {
        /*
         * Returning this means that we won't show any translation to the user.
         * "C" disables all localization.
         */
        $wppo_cache['lang'] = 'C';
        return 'C';
    }
    
    return $defined_lang;
}

/*
 * Since we just can't redefine constants without special php libraries, we need to
 * ask to the admin manually remove it from wp-config.php.
 * 
 * However, if we find runkit_constant_redefine available, we'll try to use it
 * to redefine the contant.
 * 
 */
if (is_admin() && defined('WPLANG') && !function_exists('runkit_constant_redefine')) {
    add_action('admin_notices', function() {
        echo "<div class=\"updated fade\"><p>Please comment line <code>define('WPLANG', '');</code> in wp-config.php to make WPPO Plugin work correctly.</p></div>";
    }); 
}

/*
 * Define default language for WordPress template
 */
if (wppo_get_lang() != 'C') {
    if(!defined('WPLANG') && !function_exists('runkit_constant_redefine')) {
        define('WPLANG', wppo_get_lang());
    }
    
    if(function_exists('runkit_constant_redefine')) {
        runkit_constant_redefine('WPLANG', wppo_get_lang());
    }
}


/*
 * Get all the translated data from the current post.
 */
function wppo_get_translated_data($string, $id = null) {
    global $post, $wpdb, $wppo_cache;

    $lang = wppo_get_lang();

    if ($id !== null) {
        $p = &get_post($id);
    } else {
        $p = $post;
    }
    
    if ($lang != "C") {
        if (!isset($wppo_cache['posts'][$p->ID])) {
            $wppo_cache['posts'][$p->ID] = $wpdb->get_row("SELECT * FROM " . WPPO_PREFIX . "posts ".
                                                          "WHERE post_id = '" . mysql_real_escape_string($p->ID). "' ".
                                                          "AND lang = '" . mysql_real_escape_string($lang) . "'", ARRAY_A);
        }
    
        if (isset($wppo_cache['posts'][$p->ID][$string])) {
            return $wppo_cache['posts'][$p->ID][$string];
        }
    }
    
    if ($string == 'translated_content') {
        return wpautop($p->post_content);
    } else {
        return $p->{str_replace("translated_", "post_", $string)};
    }
}

?>

