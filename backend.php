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
    
    if ($coverage == 'all') {
        $coverage = array('posts', 'pages');
    }
    
    if (is_string($coverage) && $coverage != 'posts' && $coverage != 'pages') {
        die("First argument of wppo_update_pot() must be \"posts\" or \"pages\" (or an array of both).");
    }
    
    if (is_string($coverage)) {
        $coverage = array($coverage);
    }
    
    
    foreach ($coverage as $post_type) {
        
        $pot_file = WPPO_DIR.$post_type.".pot";
        $xml_file = WPPO_DIR.$post_type.".xml";
        
        $generated_xml = wppo_generate_po_xml($post_type);
        
        if (is_writable($xml_file)) {
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
 * This function will check for changes in all the PO files,
 * in order to try to keep track of changes in the translations
 */
function wppo_check_for_po_changes() {
    global $wpdb;
    
    $po_dates = array();
    
    if ($post_type_handle = opendir(WPPO_DIR)) {
        
        // Walk trough post_type folders
        while(false !== ($post_type_item = readdir($post_type_handle))) {
            if (is_dir($post_type_item) && ($post_type_item == 'posts' || $post_type_item == 'pages')) {
                
                // Walk trough lang files inside po folder
                if ($lang_handle = opendir(WPPO_DIR.$post_type_item.'/po/')) {
                    while(false !== ($lang_item = readdir($lang_handle))) {
                        
                        if (strpos($lang_item, '.po') !== false && strpos($lang_item, '.pot') === false) {
                            
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
    
    foreach ($po_dates as $post_type => $langs) {
        foreach ($langs as $lang => $last_modified) {
            
            
            /*
             * Check if the existing PO file exists in the
             * translation_log table
             */
            if (!$wpdb->get_row("SELECT translation_date FROM `".WPPO_PREFIX."translation_log` WHERE lang = '".mysql_real_escape_string($lang)."' AND post_type = '".mysql_real_escape_string($post_type)."' AND translation_date = '".mysql_real_escape_string($last_modified)."' LIMIT 1")) {
                
                /*
                 * We are not inserting the status of the PO file
                 * FIXME
                 */
                $wpdb->insert(WPPO_PREFIX."translation_log",
                    array(
                        'lang' => $lang,
                        'post_type' => $post_type,
                        'translation_date' => $last_modified
                        //'status' => TODO
                    ),
                    array('%s', '%s', '%s')
                );
                
                $po_files_needing_update[$post_type][] = $lang;
                
            }
            
        }
    }
    
    foreach ($po_files_needing_update as $post_type => $langs) {
        foreach ($langs as $lang) {
            
            $original_xml_file   = WPPO_DIR.$post_type.'.xml';
            $po_file             = WPPO_DIR.$post_type.'/po/'.$lang.'.po';
            $translated_xml_file = WPPO_DIR.$post_type.'/xml/'.$lang.'.xml';
            
            
            $command = WPPO_XML2PO_COMMAND." -m xhtml -p ".escapeshellarg($po_file)." -o ".escapeshellarg($translated_xml_file)." ".escapeshellarg($original_xml_file);
            $output = shell_exec($command);
            
            $translated_xml_content = file_get_contents($translated_xml_file);
            
            $dom = new DOMDocument();
            $dom->loadXML($translated_xml_content);
            
            $posts = $dom->getElementsByTagName('post');
            
            $attributes = array(
                'id' => 'post_id',
                'title' => 'translated_title',
                'excerpt' => 'translated_excerpt',
                'name' => 'translated_name',
                'content' => 'translated_content'
            );
            
            foreach ($posts as $post) {
                
                foreach ($attributes as $tag => $column) {
                    
                    if ($tag != 'content') {
                        if ($tag == 'id') {
                            $node[$column] = $post->getAttributeNode($tag)->value;
                        } else {
                            $node[$column] = $post->getElementsByTagName($tag)->item(0)->nodeValue;
                        }
                    } else {
                        $temporary_content_tree = $post->getElementsByTagName('html')->item(0)->childNodes;
                        $node[$column] = '';
                        foreach ($temporary_content_tree as $element) {
                            $node[$column] .= $element->ownerDocument->saveXML($element);
                        }
                    }
                }
                
                $node['lang'] = $lang;
                
                /*
                 * Stores in the table the translated version of the page
                 */
                $table_format = array('%s', '%d', '%s', '%s', '%s');
                if (!$wpdb->get_row("SELECT wppo_id FROM ".WPPO_PREFIX."posts WHERE post_id = '". mysql_real_escape_string($page_id) ."' AND lang = '". mysql_real_escape_string($lang) ."'")) {
                    $wpdb->insert(WPPO_PREFIX."posts", $node, $table_format);
                } else {
                    $wpdb->update(WPPO_PREFIX."posts", $node, array('post_id' => $node['post_id'], 'lang' => $lang), $table_format);
                }
            }
        }
    }
}


function wppo_generate_po_xml($post_type) {
    global $wpdb;
    
    if ($post_type != 'pages' && $post_type != 'posts') {
        return false;
    }
    
    $sql = "SELECT ID, post_content, post_title, post_excerpt, post_name, post_type
            FROM wp_posts
            WHERE
                post_status IN ('publish', 'future') AND
                post_type != 'revision'";
    
    if ($post_type == 'pages') {
        
        /*
         * Pages must include also some permanent data in the future,
         * like website name and navigation menus
         * TODO
         */
        
        $sql .= " AND post_type IN ('page', 'nav_menu_item') ORDER BY post_type ASC";
        
    } elseif ($post_type == 'posts') {
        
        /*
         * We need to verify how attachments are stored in wp_posts before
         * giving it to the translators
         * FIXME
         */
        
        $sql .= " AND post_type NOT IN ('page', 'nav_menu_item')";
        
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
    
    if ($post_type == 'pages') {
        
        /*
         * Support for translated bloginfo strings
         */
        
        $bloginfo = array('name', 'description');
        
        $node['bloginfo']['elem'] = $dom->createElement("bloginfo");
        
        foreach ($bloginfo as $row) {
            
            $node['bloginfo'][$row]['tag'] = $dom->createElement($row);
            $node['bloginfo'][$row]['value'] = $dom->createTextNode(get_bloginfo($row));
            $node['bloginfo'][$row]['tag']->appendChild($node['bloginfo'][$row]['value']);
            
            $node['bloginfo']['elem']->appendChild($node['bloginfo'][$row]['tag']);
            
        }
        
        $root->appendChild($node['bloginfo']['elem']);
        
    }

    foreach ($posts as $id => $row) {
        
        /*
         * This will verify if the item is not repeating any existing data
         * from other page. This happens when post_type is nav_menu_item
         * referring directly to a page.
         */
        $post_linked_id = get_post_meta($row->ID, '_menu_item_object_id');
        if (isset($post_linked_id[0])) {
            $post_linked_id = $post_linked_id[0];
        } else {
            $post_linked_id = false;
        }
        
        if ($row->ID == $post_linked_id || $post_linked_id == false) {
        
            $post = $dom->createElement("post");
            
            $attributes = array(
                'id' => 'ID',
                'title' => 'post_title',
                'excerpt' => 'post_excerpt',
                'name' => 'post_name',
                'content' => 'post_content',
            );
            
            foreach ($attributes as $tag => $column) {
                
                if($row->{$column} != '') {
                    
                    switch ($tag) {
                        
                        case 'content':
                        
                            $row->{$column} = wpautop($row->{$column});

                            $node[$tag]['value'] = $dom->createDocumentFragment();
                            $node[$tag]['value']->appendXML('<html>'.$row->{$column}.'</html>');
                            $node[$tag]['value'] = $node[$tag]['value'];

                            if ($node[$tag]['value'] == false) {
                                $broken_dom_pages[] = $row->ID;
                            }

                            $node[$tag]['attr'] = $dom->createElement($tag);
                            $node[$tag]['attr']->appendChild($node[$tag]['value']);
                        
                        break;
                        
                        default:
                        
                            if ($tag == 'id') {
                                $node[$tag]['attr'] = $dom->createAttribute($tag);
                            } else {
                                $node[$tag]['attr'] = $dom->createElement($tag);
                            }
                            $node[$tag]['value'] = $dom->createTextNode($row->{$column});
                            $node[$tag]['attr']->appendChild($node[$tag]['value']);
                        
                        break;
                        
                    }
            
                    $post->appendChild($node[$tag]['attr']);
                    
                }
                
            }
            
            $root->appendChild($post);
            
        }
    }
    
    $dom->appendChild($root);
    $content = $dom->saveXML();
    
    return $content;
}


?>
