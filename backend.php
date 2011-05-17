<?php
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
 * This function regenerates all the POT files, including all the recent
 * changes in any content.
 * Automatically it also updates the internal XML used for translation.
 * 
 * TODO:
 * For reference it shouldn't just replace the previous POT files.
 * It should keep a backup somewhere else.
 * 
 */
function wppo_update_pot($coverage = array('posts', 'pages')) {
    
    global $wpdb;
    
    if($coverage == 'all') {
        $coverage = array('posts', 'pages');
    }
    
    if(is_string($coverage) && $coverage != 'posts' && $coverage != 'pages') {
        die("First argument of wppo_update_pot() must be \"posts\" or \"pages\" (or an array of both).");
    }
    
    if(is_string($coverage)) {
        $coverage = array($coverage);
    }
    
    foreach($coverage as $post_type) {
        
        $pot_file = WPPO_DIR.$post_type.".pot";
        $xml_file = WPPO_DIR.$post_type.".xml";
        
        $generated_xml = wppo_generate_po_xml($post_type);
        
        if(is_writable($xml_file)) {
            file_put_contents($xml_file, $generated_xml);
        } else {
            die("Ooops. We got an error here. The file ".$xml_file." must be writeable otherwise we can't do anything.");
        }
        
        $output = shell_exec(WPPO_XML2PO_COMMAND." -m xhtml -o ".escapeshellarg($pot_file)." ".escapeshellarg($xml_file));
        
        // Updates translation_log table.
        // FIXME
        
    }
    
    
    
}


/*
 * this action will be fired when damned lies system send an updated version of
 * a po file. This function needs to take care of creating the translated
 * xml file and separate its content to the wordpress database
 */
function wppo_check_for_po_changes() {
    global $wpdb;
    
    $po_dates = array();
    
    if($post_type_handle = opendir(WPPO_DIR)) {
        
        // Walk trough post_type folders
        while(false !== ($post_type_item = readdir($post_type_handle))) {
            if(is_dir($post_type_item) && ($post_type_item == 'posts' || $post_type_item == 'pages')) {
                
                // Walk trough lang files inside po folder
                if($lang_handle = opendir(WPPO_DIR.$post_type_item.'/po/')) {
                    while(false !== ($lang_item = readdir($lang_handle))) {
                        
                        if(strpos($lang_item, '.po') !== false && strpos($lang_item, '.pot') === false) {
                            
                            $lang = explode(".", $lang_item);
                            $lang = $lang[0];
                            $po_dates[$post_type_item][$lang] = filemtime(WPPO_DIR.$post_type_item.'/po/'.$lang_item);
                            
                        }
                        
                    }
                }
            }
        }
    }
    
    $po_files_needing_update = array();
    
    foreach($po_dates as $post_type => $langs) {
        foreach($langs as $lang => $last_modified) {
            
            // Check last modified here
            // FIXME
            $last_saved = 'X';
            
            if($last_modified != $last_saved) {
                $po_files_needing_update[$post_type][] = $lang;
            }
        }
    }
    
    foreach($po_files_needing_update as $post_type => $langs) {
        foreach($langs as $lang) {
            
            $original_xml_file   = WPPO_DIR.$post_type.'.xml';
            $po_file             = WPPO_DIR.$post_type.'/po/'.$lang.'.po'
            $translated_xml_file = WPPO_DIR.$post_type.'/xml/'.$lang.'.xml';
            
            
            $command = WPPO_XML2PO_COMMAND." -m xhtml -p ".escapeshellarg($po_file)." -o ".escapeshellarg($translated_xml_file)." ".escapeshellarg($original_xml_file);
            $output = shell_exec($command);
            
            // read generated translated xml file
            // FIXME
            
            // save translation log
            // FIXME
        }
    }
    
    ////////////////////////////////////////////////////////
    // old code of this function bellow
    ////////////////////////////////////////////////////////
    
    /*
     * WordPress pattern for validating data
     */
    $table_format = array('%s', '%d', '%s', '%s', '%s');
    
    // FIXME
    $post_type = 'posts';
    
    
    
    $po_dir = WPPO_DIR."/".$post_type;
    
    
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
                    $wpdb->get_row("SELECT wppo_id FROM ".WPPO_PREFIX."posts WHERE post_id = '". mysql_real_escape_string($page_id) ."' AND lang = '". mysql_real_escape_string($lang) ."'");
                    if($wpdb->num_rows == 0) {
                        $wpdb->insert(WPPO_PREFIX."posts", $page_array, $table_format);
                    } else {
                        $wpdb->update(WPPO_PREFIX."posts", $page_array, array('post_id' => $page_id, 'lang' => $lang), $table_format);
                    }
                }
            }
        }
    }
}


function wppo_generate_po_xml($post_type) {
    global $wpdb;
    
    if($post_type != 'pages' || $post_type != 'posts') {
        return false;
    }
    
    $sql = "SELECT ID, post_content, post_title, post_excerpt, post_name 
            FROM wp_posts
            WHERE
                post_status IN ('publish', 'future') AND
                post_type != 'revision'";
    
    if($post_type == 'pages') {
        
        /*
         * Pages must include also some permanent data in the future,
         * like website name and navigation menus
         * TODO
         */
        
        $sql .= "AND post_type = 'page'";
        
    } elseif($post_type == 'posts') {
        
        /*
         * We need to verify how attachments are stored in wp_posts before
         * giving it to the translators
         * FIXME
         */
        
        $sql .= "AND post_type != 'page' AND post_type != 'nav_menu'";
        
    }
    
    $posts = $wpdb->get_results($sql);
    
    /*
     * We still don't do anything with the list of broken DOM pages
     * FIXME
     */
    $broken_dom_pages = array();

    /*
     * Starts to create the XML
     */
    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;
    $root = $dom->createElement("wppo");

    foreach($posts as $id => $row) {
        $page = $dom->createElement("page");
        
        $attributes = array(
            'id' => 'ID',
            'title' => 'post_title',
            'excerpt' => 'post_excerpt',
            'name' => 'post_name',
            'content' => 'post_content'
        );
        
        foreach($attributes as $tag => $column) {
            
            if($tag != 'content') {
                
                $node[$tag]['attr'] = $dom->createAttribute($tag);
                $node[$tag]['value'] = $dom->createTextNode($value->{$column});
                $node[$tag]['attr']->appendChild($node[$tag]['value']);
                
            } else {
                
                $value->{$column} = wpautop($value->{$column});

                $node[$tag]['value'] = $dom->createDocumentFragment();
                $node[$tag]['value']->appendXML('<html>'.$value->{$column}.'</html>');

                if ($$node[$tag]['xml'] == false) {
                    $broken_dom_pages[] = $value->ID;
                }

                $node[$tag]['attr'] = $dom->createElement($tag);
                $node[$tag]['attr']->appendChild($node[$tag]['value']);
                
            }
        
            $page->appendChild($node[$tag]['attr']);
            
        }
        
        $root->appendChild($page);
    }
    
    $dom->appendChild($root);
    $content = $dom->saveXML();
    return $content;
}


?>
