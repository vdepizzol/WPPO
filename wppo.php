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




/* 
 * Folder structure:
 * /wppo/[post_type]/[file_type]/[lang].[ext]
 * 
 * Example:
 * /wppo/static/po/fr.po
 * /wppo/dynamic/xml/pt_BR.xml
 * 
 * Global POT file:
 * /wppo/dynamic.pot
 * /wppo/static.pot
 * 
 * Global XML file:
 * /wppo/dynamic.xml
 * /wppo/static.xml
 * 
 */

global $wpdb;
define('WPPO_VERSION', '1');

define('WPPO_PREFIX', $wpdb->prefix."wppo_");
define('WPPO_DIR', ABSPATH . "wppo/");
define('WPPO_URI_SCHEME', 'http');

define('WPPO_ABS_URI', $_SERVER['REQUEST_URI']);
define('WPPO_HOME_URL', home_url());
define('WPPO_PLUGIN_DIR', WP_PLUGIN_DIR.'/wppo/');
define('WPPO_PLUGIN_FILE', WPPO_PLUGIN_DIR.'wppo.php');

define('WPPO_DEFAULT_LANGUAGE_NAME', 'English');
define('WPPO_DEFAULT_LANGUAGE_CODE', 'en');

$wppo_cache = array();
$wppo_error = array();


require_once dirname(__FILE__).'/backend.php';
require_once dirname(__FILE__).'/url.php';
require_once dirname(__FILE__).'/widget.php';

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
                                  `lang_status` enum('visible', 'hidden') NOT NULL DEFAULT 'hidden',
                                  PRIMARY KEY ( `lang_code` )
                                ) ENGINE=MYISAM DEFAULT CHARSET=latin1 ;",
        
        
        'translation_log' =>    "CREATE TABLE `".WPPO_PREFIX."translation_log` (
                                  `log_id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                  `lang` varchar(10) NOT NULL,
                                  `post_type` varchar(10) NOT NULL,
                                  `translation_date` int(10) NOT NULL,
                                  `translated` double NOT NULL,
                                  `fuzzy` double NOT NULL,
                                  `untranslated` double NOT NULL
                                ) ENGINE=MYISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;",
                                
        'terms'           =>    "CREATE TABLE `".WPPO_PREFIX."terms` (
                                  `wppo_term_id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                  `term_id` bigint(20) NOT NULL,
                                  `lang` varchar(10) NOT NULL,
                                  `translated_name` varchar(200) NOT NULL
                                ) ENGINE=MYISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;",

        'options'         =>    "CREATE TABLE `".WPPO_PREFIX."options` (
                                  `wppo_option_id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                  `option_name` varchar(64) NOT NULL,
                                  `lang` varchar(10) NOT NULL ,
                                  `translated_value` longtext NOT NULL
                                ) ENGINE = MYISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;"
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
    $directories_for_post_types = array('static', 'dynamic');
    $directories_for_formats = array('po', 'xml', 'mo');
    
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

        if (!file_exists(WPPO_DIR.'/'.$directory.'.xml')) {
            file_put_contents(WPPO_DIR.'/'.$directory.'.xml', '');
        }

        if (!file_exists(WPPO_DIR.'/'.$directory.'.pot')) {
            file_put_contents(WPPO_DIR.'/'.$directory.'.pot', '');
        }
        
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
     
    add_option("wppo_version", WPPO_VERSION);
    
    // Number of old POT files we'll keep in the pot_archive folder
    // FIXME
    add_option("wppo_pot-cache-number", 5);
    
    // Limit of date to select news to translate. Occasionally people
    // want to disable the translation of old news.
    // FIXME
    add_option("wppo_old-news-limit", strtotime('now - 4 months'));
    
}
register_activation_hook(WPPO_PLUGIN_FILE, 'wppo_install');


function wppo_uninstall() {
    
    global $wpdb;
    
    /*
     * Drop existing tables
     */
    
    $tables = array('posts', 'languages', 'translation_log', 'terms', 'options');
    
    foreach ($tables as $index => $name) {
        $tables[$index] = WPPO_PREFIX.$name;
    }
    
    $wpdb->query("DROP TABLE IF EXISTS ".implode(", ", $tables));
    
    delete_option("wppo_version");
    
    /*
     * We won't delete any wppo files here.
     */
    
}
register_deactivation_hook(WPPO_PLUGIN_FILE, 'wppo_uninstall');




if (!is_admin()) {
    
    /*
     * Apply translations for the website title and description
     */

    add_filter('bloginfo', 'parse_bloginfo', 10, 2);
    add_filter('get_bloginfo_rss', 'parse_bloginfo', 10, 2);
    
    function parse_bloginfo($bloginfo, $attribute) {

        global $wppo_cache, $wpdb;

        $lang = wppo_get_lang();
        $col_attr = $attribute;
        
        if (!isset($wppo_cache['bloginfo'][$col_attr]) && $lang != WPPO_DEFAULT_LANGUAGE_CODE) {
            $wppo_cache['bloginfo'][$col_attr] = $wpdb->get_row("SELECT * FROM " . WPPO_PREFIX . "options ".
                                                                "WHERE option_name = '" . mysql_real_escape_string($col_attr) . "' ".
                                                                "AND lang = '" . mysql_real_escape_string($lang) . "'", ARRAY_A);
        }

        if (isset($wppo_cache['bloginfo'][$col_attr]) && is_array($wppo_cache['bloginfo'][$col_attr])) {
            return $wppo_cache['bloginfo'][$col_attr]['translated_value'];
        }
        
        return $bloginfo;
        
        
    }
    
    /*
     * Display proper language in translated RSS feed
     */
    
    add_filter('pre_option_rss_language', function() {
    
        $defined_lang = wppo_get_lang();
        
        $defined_lang_array = explode('_', $defined_lang);
        $defined_lang = strtolower($defined_lang_array[0]).'-'.strtoupper($defined_lang_array[1]);
        
        return $defined_lang;
        
    }, 10);


    /*
     * Apply translations for the title and the content,
     * and other generic page requests
     */

    add_filter('the_title', 'parse_title', 10, 2);
    add_filter('single_post_title', 'parse_title', 10, 2);
    
    function parse_title($title, $id) {
        global $wppo_cache;
        
        $translated_title = trim(wppo_get_translated_data('translated_title', $id));

        if (empty($translated_title)) {
            return $title;
        } else {
            return $translated_title;
        }
    }
    
        
    add_filter('the_excerpt', 'parse_excerpt', 10, 1);
    
    function parse_excerpt($excerpt) {
        global $wppo_cache, $post;

        if (isset($wppo_cache['posts'][$post->ID])) {
            $translated_excerpt = $wppo_cache['posts'][$post->ID]['translated_excerpt'];
        } else {
            $translated_excerpt = trim(wppo_get_translated_data('translated_excerpt', $post->ID));
        }

        if (empty($translated_excerpt)) {
            return $excerpt;
        } else {
            return $translated_excerpt;
        }
    }


    add_filter('the_content', 'parse_content', 10, 1);
    
    function parse_content($content) {
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
    }

    add_filter('get_pages', function($pages) {
        
        global $wppo_cache, $wpdb;

        $lang = wppo_get_lang();

        foreach ($pages as $index => $page) {
            if (!isset($wppo_cache['posts'][$page->ID]) && $lang != WPPO_DEFAULT_LANGUAGE_CODE) {
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




    /*
     * Apply translations for
     * categories, tags and terms
     */

    function wppo_get_translated_term_from_id($term_id) {

        global $wppo_cache, $wpdb;

        $lang = wppo_get_lang();
        
        if (!isset($wppo_cache['terms'][$term_id]) && $lang != WPPO_DEFAULT_LANGUAGE_CODE) {
            $wppo_cache['terms'][$term_id] = $wpdb->get_row("SELECT * FROM " . WPPO_PREFIX . "terms ".
                                                                      "WHERE term_id = '" . mysql_real_escape_string($term_id) . "' ".
                                                                      "AND lang = '" . mysql_real_escape_string($lang) . "'", ARRAY_A);
        }

        if (isset($wppo_cache['terms'][$term_id]) && is_array($wppo_cache['terms'][$term_id])) {
            return $wppo_cache['terms'][$term_id]['translated_name'];
        }

        return false;
        
    }

    add_filter('get_category', function($category) {

        if (wppo_get_translated_term_from_id($category->term_id) != false) {
            $category->name = wppo_get_translated_term_from_id($category->term_id);
        }
        return $category;

    }, 10);

    add_filter('wp_get_object_terms', function($categories) {

        foreach ($categories as $index => $category) {
            if (isset($category->term_id) && wppo_get_translated_term_from_id($category->term_id) != false) {
                $categories[$index]->name   = wppo_get_translated_term_from_id($category->term_id);
            }
        }
        
        return $categories;
        
    }, 10, 2);

    add_filter('list_cats', function($category_name, $attributes) {
        
        if (wppo_get_translated_term_from_id($attributes->term_id) != false) {
            $category_name = wppo_get_translated_term_from_id($attributes->term_id);
        }
        return $category_name;

    }, 10, 2);



    /*
     * Apply translations to navigation menus
     */

    add_filter('wp_get_nav_menu_items', function($items) {
        
        global $wppo_cache, $wpdb;

        $lang = wppo_get_lang();
        
        foreach ($items as $index => $item) {
            
            $post_id = get_post_meta($item->ID, '_menu_item_object_id');
            
            if (isset($post_id[0])) {
                
                $post_id = $post_id[0];
                
                if ($post_id != $item->ID) {
                    
                    if (!isset($wppo_cache['posts'][$post_id]) && $lang != WPPO_DEFAULT_LANGUAGE_CODE) {
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
            
        }
        
        return $items;
    });


    /*
     * Support for search in translated posts
     */

    add_filter('posts_search', function($search) {
        
        global $wpdb;
        
        $lang = wppo_get_lang();
        
        if ($lang != WPPO_DEFAULT_LANGUAGE_CODE) {
            $search = str_replace("({$wpdb->posts}.post_content LIKE ", "(".WPPO_PREFIX."posts.translated_content LIKE ", $search);
            $search = str_replace("({$wpdb->posts}.post_title LIKE ",   "(".WPPO_PREFIX."posts.translated_title LIKE ", $search);
        }
        
        return $search;
        
    });

    add_filter('posts_clauses', function($clauses) {
        
        global $wpdb;
        
        if (is_search()) {
        
            $lang = wppo_get_lang();
            
            if ($lang != WPPO_DEFAULT_LANGUAGE_CODE) {
                $clauses['join'] = "LEFT JOIN ".WPPO_PREFIX."posts ON $wpdb->posts.ID = ".WPPO_PREFIX."posts.post_id ".
                                   "AND ".WPPO_PREFIX."posts.lang = '".mysql_real_escape_string($lang)."'";
            }
        
        }
        
        return $clauses;
        
    });

}

/*
 * Main function to return the current defined language
 */

function wppo_get_lang() {
    
    global $wpdb, $wppo_cache, $wp_query;
    
    /*
     * If there's no WPPO installed, this function won't look for its database
     * tables and produce avoidable errors
     */
    if (get_option('wppo_version') == false) {
        return WPPO_DEFAULT_LANGUAGE_CODE;
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
    

    $lang_uri = wppo_find_lang_in_uri();
        
    if (isset($lang_uri)) {
        
        $defined_lang = $lang_uri;
        
    } elseif (isset($wp_query->query_vars['lang'])) {
        
        $defined_lang = $wp_query->query_vars['lang'];
    
    } elseif (isset($_SESSION['lang'])) {
        
        $defined_lang = $_SESSION['lang'];
        
    }
    
    if (isset($defined_lang)) {
        
        if (strpos($defined_lang, '-') !== false) {
            $defined_lang_array = explode('-', $defined_lang);
            $defined_lang = strtolower($defined_lang_array[0]).'_'.strtoupper($defined_lang_array[1]);
        }
        
        /*
         * Verify if the selected language really exists
         */
        
        if (array_key_exists($defined_lang, $wppo_cache['available_lang'])) {
            $wppo_cache['lang'] = $defined_lang;
            return $defined_lang;
        }
    }

    $wppo_cache['lang'] = WPPO_DEFAULT_LANGUAGE_CODE;
    return WPPO_DEFAULT_LANGUAGE_CODE;
}

function wppo_get_all_available_langs() {
    
    global $wppo_cache;
    wppo_get_lang();
    
    if (!isset($wppo_cache['available_lang']) || !is_array($wppo_cache['available_lang'])) {
        $wppo_cache['available_lang'] = array();
    }
    
    $wppo_cache['available_lang'] = array_merge(
        array(WPPO_DEFAULT_LANGUAGE_CODE => WPPO_DEFAULT_LANGUAGE_NAME),
        $wppo_cache['available_lang']
    );
    
    return $wppo_cache['available_lang'];
    
}

/*
 * Since we just can't redefine constants without special php libraries, we need to
 * ask to the admin manually remove it from wp-config.php.
 */

if (is_admin() && defined('WPLANG')) {
    add_action('admin_notices', function() {
        echo "<div class=\"updated fade\"><p>Please comment line <code>define('WPLANG', '');</code> in wp-config.php to make WPPO Plugin work correctly.</p></div>";
    }); 
}

if (is_admin() && (!is_dir(ABSPATH."wp-content/languages") || !is_writable(ABSPATH."wp-content/languages"))) {
    add_action('admin_notices', function() {
        echo "<div class=\"updated fade\"><p>Please make <code>/wp-content/languages</code> folder writable.</p></div>";
    }); 
}


add_action('setup_theme', function() {
    /*
     * Define default language for WordPress template
     */
    if (wppo_get_lang() != WPPO_DEFAULT_LANGUAGE_CODE) {
        if(!defined('WPLANG')) {
            define('WPLANG', wppo_get_lang());
        }
    }
    
});


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
    
    if ($lang != WPPO_DEFAULT_LANGUAGE_CODE) {
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

