<?php
/*
Plugin Name: WPPO
Description: Gettext layer for WordPress content
Author: Vinicius Depizzol
Author URI: http://vinicius.depizzol.com.br
License: AGPLv3
*/

/* Copyright 2010  Vinicius Depizzol <vdepizzol@gmail.com>
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

if(!function_exists('_log')) {
    function _log( $message ) {
        if(WP_DEBUG === true) {
            if( is_array($message) || is_object($message) ){
                file_put_contents(ABSPATH.'debug.log', print_r($message, true)."\n", FILE_APPEND);
            } else {
                file_put_contents(ABSPATH.'debug.log', $message."\n" , FILE_APPEND);
            }
        }
    }
}


require_once("wppo.genxml.php");

/* 
 * Folder structure: (TODO)
 * /wppo/[post_type]/[file_type]/[lang].[ext]
 * 
 * Example:
 * /wppo/pages/po/fr.po
 * /wppo/posts/pot/es.pot
 * /wppo/posts/xml/pt_br.xml
 * 
 * Global POT file:
 * /wppo/master.pot
 * 
 */

// FIXME
define('WPPO_DIR', ABSPATH . "wppo/");
define('PO_DIR', WPPO_DIR . "po/");
define('POT_DIR', WPPO_DIR . "pot/");
define('POT_FILE', POT_DIR . "gnomesite.pot");
define('XML_DIR', WPPO_DIR . "xml/");

$wppo_cache = array();

/*
 * Setting up where compiled po files are located and which translation
 * domain to use.
 */
bindtextdomain('gnomesite', PO_DIR);
bind_textdomain_codeset('gnomesite', 'UTF-8');
textdomain('gnomesite');
add_filter('get_pages', 'wppo_filter_get_pages', 1);


/*
 * Creates wppo auxiliary table when plugin is installed to keep all the
 * translated xml in an easy accessible format.
 */
function wppo_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . "wppo";

    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
                 `wppo_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                 `post_id` bigint(20) unsigned NOT NULL,
                 `lang` varchar(10) NOT NULL,
                 `translated_title` text NOT NULL,
                 `translated_excerpt` text NOT NULL,
                 `translated_name` varchar(200) NOT NULL,
                 `translated_content` longtext NOT NULL,
                 PRIMARY KEY (`wppo_id`),
                 KEY `post_id` (`post_id`)
               ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    if(!is_dir(WPPO_DIR)) {
        mkdir(WPPO_DIR, 0755);
        mkdir(PO_DIR, 0755);
        mkdir(POT_DIR, 0755);
        mkdir(XML_DIR, 0755);
    }  
    wppo_update_pot_file();
}
register_activation_hook(__FILE__, 'wppo_install');


add_filter('the_title', function($title, $id) {
    global $wppo_cache;

    $translated_title = trim(wppo_get_translated_data('translated_title', $id));

    if(empty($translated_title)) {
        return $title;
    } else {
        return $translated_title;
    }
}, 10, 2);

add_filter('the_content', function($content) {
    global $wppo_cache, $post;

    if(isset($wppo_cache[$post->ID])) {
        $translated_content = $wppo_cache[$post->ID]['translated_content'];
    } else {
        $translated_content = trim(wppo_get_translated_data('translated_content', $post->ID));
    }

    if(empty($translated_content)) {
        return $content;
    } else {
        return $translated_content;
    }
}, 10, 1);



function wppo_filter_get_pages($pages) {
    global $wppo_cache, $wpdb;

    $lang = wppo_get_lang();
    if(strpos($lang, '_') !== false) {
        $fallback_lang = explode('_', $lang);
        $fallback_lang = $fallback_lang[0];
    } else {
        $fallback_lang = $lang;
    }

    foreach($pages as $page) {
        if(!isset($wppo_cache[$page->ID])) {
          $wppo_cache[$page->ID] = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "wppo WHERE post_id = '" . $page->ID . "' AND (lang = '" . $lang . "' OR lang = '" . $fallback_lang . "')", ARRAY_A);
        }
        
        if(is_array($wppo_cache[$page->ID])) {
          $page->post_title   = $wppo_cache[$page->ID]['translated_title'];
          $page->post_name    = $wppo_cache[$page->ID]['translated_name'];
          $page->post_content = $wppo_cache[$page->ID]['translated_content'];
          $page->post_excerpt = $wppo_cache[$page->ID]['translated_excerpt'];
        }
    }
    
    //_log($pages);
    return $pages;
}

/*
 * This action will be fired when a post/page is updated. It's used to
 * update (regenerate, actually) the pot file with all translatable
 * strings of the gnome.org website.
 */
function wppo_update_pot_file($post) {
    $xml_file = XML_DIR . "gnomesite.xml";
    file_put_contents($xml_file, wppo_generate_po_xml());
    exec("/usr/bin/xml2po -m xhtml -o " . POT_FILE . " $xml_file");
}
add_action('post_updated', 'wppo_update_pot_file');
add_action('post_updated', 'wppo_receive_po_file');


/*
 * this action will be fired when damned lies system send an updated version of
 * a po file. This function needs to take care of creating the translated
 * xml file and separate its content to the wordpress database
 */
function wppo_receive_po_file() {
  global $wpdb;
      
  $table_format = array('%s', '%d', '%s', '%s', '%s');
  
    if($handle = opendir(PO_DIR)) {
        while(false !== ($po_file = readdir($handle))) {
            /*
             * Gets all the .po files from PO_DIR. Then it will generate a translated
             * XML for each language.
             *
             * All the po files must use the following format: "gnomesite.[lang-code].po"
             *
             */
            if(strpos($po_file, '.po') !== false && strpos($po_file, '.pot') === false) {
                $po_file_array = explode('.', $po_file);
                
                /*
                 * Arranging the name of the translated xml to something like
                 * "gnomesite.pt-br.xml".
                 */
                $lang = $po_file_array[1];
                $translated_xml_file = XML_DIR . 'gnomesite.' . $lang . '.xml';
                $cmd = "/usr/bin/xml2po -m xhtml -p " . PO_DIR . "$po_file -o $translated_xml_file " . XML_DIR . "gnomesite.xml";
                $out = exec($cmd);

                $translated_xml = file_get_contents($translated_xml_file);
                $dom = new DOMDocument();
                $dom->loadXML($translated_xml);
                
                $pages = $dom->getElementsByTagName('page');
                
                foreach($pages as $page) {
                
                    $page_id      = $page->getAttributeNode('id')->value;
                    $page_title   = $page->getElementsByTagName('title')->item(0)->nodeValue;
                    $page_excerpt = $page->getElementsByTagName('excerpt')->item(0)->nodeValue;
                    $page_name    = $page->getElementsByTagName('name')->item(0)->nodeValue;


                    $page_content_elements = $page->getElementsByTagName('html')->item(0)->childNodes;
                    $page_content = '';
                    foreach($page_content_elements as $element) {
                        $page_content .= $element->ownerDocument->saveXML($element);
                    }
                    
                    $page_array = array('lang' => $lang,
                                        'post_id' => $page_id,
                                        'translated_title' => $page_title,
                                        'translated_excerpt' => $page_excerpt,
                                        'translated_name' => $page_name,
                                        'translated_content' => $page_content
                                        );
                    
                    /*
                     * Stores in the table the translated version of the page
                     */
                    $wpdb->get_row("SELECT wppo_id FROM " . $wpdb->prefix . "wppo WHERE post_id = '" . $page_id . "' AND lang = '" . $lang . "'");
                    if($wpdb->num_rows == 0) {
                        $wpdb->insert($wpdb->prefix . "wppo", $page_array, $table_format);
                    } else {
                        $wpdb->update($wpdb->prefix . "wppo", $page_array, array('post_id' => $page_id, 'lang' => $lang), $table_format);
                    }
                }
            }
        }
    }
}



function wppo_get_lang() {
    
    if(isset($_REQUEST['lang'])) {
        $lang = $_REQUEST['lang'];
    } elseif(isset($_SESSION['lang'])) {
        $lang = $_SESSION['lang'];
    } else {
        $user_lang = explode(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
        foreach($user_lang as $k => $value) {
            $user_lang[$k] = explode(';', $value);
            $user_lang[$k] = str_replace('-', '_', $user_lang[$k][0]);
        }

        /*
         * FIXME
         * Before this, we need to check if the user language exists,
         * and if not, try the following languages.
         */
        $lang = $user_lang[0];
    }
    
    return $lang;
}


/*
 * Get all the translated data from the current post
 */
function wppo_get_translated_data($string, $id = null) {
    global $post, $wpdb, $wppo_cache;

    $lang = wppo_get_lang();

    if($id !== null) {
        $p = &get_post($id);
    } else {
        $p = $post;
    }

    if(strpos($lang, '_') !== false) {
        $fallback_lang = explode('_', $lang);
        $fallback_lang = $fallback_lang[0];
    } else {
        $fallback_lang = $lang;
    }



    if(!isset($wppo_cache[$p->ID])) {
        $wppo_cache[$p->ID] = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "wppo WHERE post_id = '" . $p->ID . "' AND (lang = '" . $lang . "' OR lang = '" . $fallback_lang . "')", ARRAY_A);
    }

    if(isset($wppo_cache[$p->ID][$string]) && $fallback_lang != "en") {
        return $wppo_cache[$p->ID][$string];
    } else {
        if($string == 'translated_content') {
            return wpautop($p->post_content);
        } else {
            return $p->{str_replace("translated_", "post_", $string)};
        }
    }
}


?>
