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




require_once("poparser.class.php");

/*
 * This function regenerates all the POT files, including all the recent
 * changes in any content.
 * Automatically it also updates the internal XML used for translation.
 * 
 * TODO:
 * For reference it shouldn't just replace the previous POT files.
 * In the future we should keep a backup somewhere else.
 * 
 */
function wppo_update_pot($coverage = array('dynamic', 'static')) {
    
    global $wpdb, $wppo_error;
    
    if ($coverage == 'all') {
        $coverage = array('dynamic', 'static');
    }
    
    if (is_string($coverage) && $coverage != 'dynamic' && $coverage != 'static') {
        die("First argument of wppo_update_pot() must be \"dynamic\" or \"static\" (or an array of both).");
    }
    
    if (is_string($coverage)) {
        $coverage = array($coverage);
    }
    
    
    foreach ($coverage as $post_type) {
        
        $pot_file = WPPO_DIR.$post_type.".pot";
        $xml_file = WPPO_DIR.$post_type.".xml";
        
        wppo_update_xml($post_type);
        
        $output = shell_exec(WPPO_XML2PO_COMMAND." -m xhtml -o ".escapeshellarg($pot_file)." ".escapeshellarg($xml_file)." 2>&1");
        
        if (trim($output) != '') {
            $wppo_error['xml2pot'][$post_type] = $output;
        }
    }
    
}

/*
 * This regenerates the main XML files used for xml2po
 */
function wppo_update_xml($coverage = array('dynamic', 'static')) {
    
    global $wpdb;
    
    if ($coverage == 'all') {
        $coverage = array('dynamic', 'static');
    }
    
    if (is_string($coverage) && $coverage != 'dynamic' && $coverage != 'static') {
        die("First argument of wppo_update_xml() must be \"dynamic\" or \"static\" (or an array of both).");
    }
    
    if (is_string($coverage)) {
        $coverage = array($coverage);
    }
    
    foreach ($coverage as $post_type) {
        
        $xml_file = WPPO_DIR.$post_type.".xml";
        
        $generated_xml = wppo_generate_po_xml($post_type);
        
        if (is_writable($xml_file)) {
            file_put_contents($xml_file, $generated_xml);
        } else {
            die("Ooops. We got an error here. The file ".$xml_file." must be writeable otherwise we can't do anything.");
        }
        
    }
    
    return true;
}




/*
 * This function will check for changes in all the PO files,
 * in order to try to keep track of changes in the translations
 * 
 * With $force = true, changes will be done even if PO files
 * aren't updated. This is used when original content is changed.
 */
function wppo_check_for_po_changes($force = false, $coverage = array('dynamic', 'static')) {
    
    global $wpdb, $wppo_error;
    
    if ($coverage == 'all') {
        $coverage = array('dynamic', 'static');
    }
    
    if (is_string($coverage) && $coverage != 'dynamic' && $coverage != 'static') {
        die("Second argument of wppo_check_for_po_changes() must be \"dynamic\" or \"static\" (or an array of both).");
    }
    
    if (is_string($coverage)) {
        $coverage = array($coverage);
    }
    
    
    $po_dates = array();
    
    if ($post_type_handle = opendir(WPPO_DIR)) {
        
        // Walk trough post_type folders
        while(false !== ($post_type_item = readdir($post_type_handle))) {
            
            if (is_dir(WPPO_DIR.$post_type_item) && in_array($post_type_item, $coverage)) {
                
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
             * translation_log table.
             * 
             * If the function needs to force an update,
             * ignores this step.
             */
            if ($force == false) {
                if (!$wpdb->get_row("SELECT translation_date FROM `".WPPO_PREFIX."translation_log` ".
                                    "WHERE lang = '".mysql_real_escape_string($lang)."' ".
                                    "AND post_type = '".mysql_real_escape_string($post_type)."' ".
                                    "AND translation_date = '".mysql_real_escape_string($last_modified)."' ".
                                    "LIMIT 1")) {
                    
                    /*
                     * Here we get all the statistics regarding the file
                     */
                    
                    $stats = POParser::stats(WPPO_DIR.$post_type.'/po/'.$lang.'.po');
                    
                    $wpdb->insert(WPPO_PREFIX."translation_log",
                        array(
                            'lang' => $lang,
                            'post_type' => $post_type,
                            'translation_date' => $last_modified,
                            'translated' => $stats['translated'],
                            'fuzzy' => $stats['fuzzy'],
                            'untranslated' => $stats['untranslated'],
                        ),
                        array('%s', '%s', '%d', '%d', '%d', '%d', '%d')
                    );
                    
                    $po_files_needing_update[$post_type][] = $lang;
                    
                }
            } else {
                $po_files_needing_update[$post_type][] = $lang;
            }
        }
    }
    
    if (count($po_files_needing_update) == 0) {
        return 0;
    }
    
    $updated_po_files = 0;
    
    foreach ($po_files_needing_update as $post_type => $langs) {
        foreach ($langs as $lang) {
            
            $original_xml_file   = WPPO_DIR.$post_type.'.xml';
            $po_file             = WPPO_DIR.$post_type.'/po/'.$lang.'.po';
            $translated_xml_file = WPPO_DIR.$post_type.'/xml/'.$lang.'.xml';
            
            
            $command = WPPO_XML2PO_COMMAND." -m xhtml -p ".escapeshellarg($po_file)." -o ".escapeshellarg($translated_xml_file)." ".escapeshellarg($original_xml_file)." 2>&1";
            $output = shell_exec($command);
            
            if (trim($output) == '') {
                        
                $translated_xml_content = file_get_contents($translated_xml_file);
                
                $dom = new DOMDocument();
                $dom->loadXML($translated_xml_content);
                
                /*
                 * Read the bloginfo
                 */
                $bloginfo = $dom->getElementsByTagName('bloginfo');
                
                if ($bloginfo->item(0) != null) {
                    foreach ($bloginfo->item(0)->childNodes as $option) {
                        if (get_class($option) == 'DOMElement') {
                            $option_node['option_name'] = $option->nodeName;
                            $option_node['lang'] = $lang;
                            $option_node['translated_value'] = $option->nodeValue;
                            
                            if (!$wpdb->get_row("SELECT option_name FROM ".WPPO_PREFIX."options WHERE option_name = '". mysql_real_escape_string($option_node['option_name']) ."' AND lang = '". mysql_real_escape_string($lang) ."'")) {
                                $wpdb->insert(WPPO_PREFIX."options", $option_node);
                            } else {
                                $wpdb->update(WPPO_PREFIX."options", $option_node, array('option_name' => $option_node['options_name'], 'lang' => $lang));
                            }
                        }
                    }
                }
                
                
                /*
                 * Read the terms
                 */
                
                $terms = $dom->getElementsByTagName('term');
                foreach($terms as $item) {
                    $terms_node['term_id'] = $item->getAttributeNode('id')->value;
                    $terms_node['translated_name'] = $item->nodeValue;
                    $terms_node['lang'] = $lang;
                    
                    if (!$wpdb->get_row("SELECT term_id FROM ".WPPO_PREFIX."terms WHERE term_id = '". mysql_real_escape_string($terms_node['term_id']) ."' AND lang = '". mysql_real_escape_string($lang) ."'")) {
                        $wpdb->insert(WPPO_PREFIX."terms", $terms_node);
                    } else {
                        $wpdb->update(WPPO_PREFIX."terms", $terms_node, array('term_id' => $terms_node['term_id'], 'lang' => $lang));
                    }
                    unset($terms_node);
                }
                
                
                /*
                 * Read all the posts
                 */
                 
                $posts = $dom->getElementsByTagName('post');
                
                /*
                 * An underline before the tag name means that it is an
                 * attribute in the XML tree
                 * (attributes are not translated by xml2po)
                 */
                $attributes = array(
                    '_id' => 'post_id',
                    'title' => 'translated_title',
                    'excerpt' => 'translated_excerpt',
                    'content' => 'translated_content'
                );
                
                foreach ($posts as $post) {
                    
                    foreach ($attributes as $tag => $column) {
                        
                        if (substr($tag, 0, 1) == '_') {
                            $tag = substr($tag, 1);
                            $isAttribute = true;
                        } else {
                            $isAttribute = false;
                        }
                        
                        switch ($tag) {
                            
                            case 'content':
                            
                                if (!empty($post->getElementsByTagName('content')->item(0)->childNodes)) {
                                    $temporary_content_tree = $post->getElementsByTagName('html')->item(0)->childNodes;
                                    $node[$column] = '';
                                    foreach ($temporary_content_tree as $element) {
                                        $node[$column] .= $element->ownerDocument->saveXML($element);
                                    }
                                    
                                    // Find all the links and convert to current language
                                    $node[$column] = wppo_recreate_links_in_html($node[$column], $lang);
                                }
                                
                            break;
                            
                            default:
                            
                                if ($isAttribute) {
                                    if (!empty($post->getAttributeNode($tag)->value)) {
                                        $node[$column] = $post->getAttributeNode($tag)->value;
                                    }
                                } else {
                                    if (!empty($post->getElementsByTagName($tag)->item(0)->nodeValue)) {
                                        $node[$column] = $post->getElementsByTagName($tag)->item(0)->nodeValue;
                                    }
                                }
                                
                            break;
                            
                        }
                    }
                    
                    $node['lang'] = $lang;
                    
                    /*
                     * Stores in the table the translated version of the page
                     */

                    if (!$wpdb->get_row("SELECT wppo_id FROM ".WPPO_PREFIX."posts WHERE post_id = '". mysql_real_escape_string($node['post_id']) ."' AND lang = '". mysql_real_escape_string($lang) ."'")) {
                        $wpdb->insert(WPPO_PREFIX."posts", $node);
                    } else {
                        $wpdb->update(WPPO_PREFIX."posts", $node, array('post_id' => $node['post_id'], 'lang' => $lang));
                    }
                    
                }
                
                $updated_po_files++;
                
            } else {
                
                /*
                 * XML2PO returned some error
                 */
                
                $wppo_error['po2xml'][$post_type][$lang] = $output;
                
            }
        }
    }
    
    return $updated_po_files;
}


function wppo_generate_po_xml($post_type) {
    global $wpdb;
    
    if ($post_type != 'static' && $post_type != 'dynamic') {
        return false;
    }
    
    $sql = "SELECT ID, post_content, post_title, post_excerpt, post_name, post_type
            FROM wp_posts
            WHERE
                post_status IN ('inherit', 'publish', 'future') AND
                post_type != 'revision'";
    
    if ($post_type == 'static') {
        
        $sql .= " AND post_type IN ('page', 'nav_menu_item') ORDER BY post_type ASC";
        
    } elseif ($post_type == 'dynamic') {
        
        $sql .= " AND post_type NOT IN ('page', 'nav_menu_item')";
        
    }
    
    
    $posts = $wpdb->get_results($sql);
    
    
    /*
     * We still don't do anything with the list of broken DOM pages
     * TODO
     */
    $broken_dom_pages = array();

    /*
     * Starts to create the XML
     */
    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;
    $root = $dom->createElement("wppo");
    
    if ($post_type == 'static') {
        
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
        
        
        /*
         * Support for translated terms
         */
         
        $terms = $wpdb->get_results("SELECT * FROM {$wpdb->terms}");
        
        $node['terms']['elem'] = $dom->createElement("terms");
        
        foreach ($terms as $row) {
            
            $node['terms'][$row->slug]['tag'] = $dom->createElement('term');
            
            $node['terms'][$row->slug]['id'] = $dom->createAttribute('id');
            $node['terms'][$row->slug]['id_value'] = $dom->createTextNode($row->term_id);
            $node['terms'][$row->slug]['id']->appendChild($node['terms'][$row->slug]['id_value']);
            
            $node['terms'][$row->slug]['name'] = $dom->createTextNode($row->name);
            
            $node['terms'][$row->slug]['tag']->appendChild($node['terms'][$row->slug]['id']);
            $node['terms'][$row->slug]['tag']->appendChild($node['terms'][$row->slug]['name']);
            
            $node['terms']['elem']->appendChild($node['terms'][$row->slug]['tag']);
            
        }
        
        $root->appendChild($node['terms']['elem']);
        
    }
    
    $posts_group = $dom->createElement("posts");

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
                '_id' => 'ID',
                'title' => 'post_title',
                'excerpt' => 'post_excerpt',
                'content' => 'post_content',
            );
            
            foreach ($attributes as $tag => $column) {
                
                if (substr($tag, 0, 1) == '_') {
                    $tag = substr($tag, 1);
                    $isAttribute = true;
                } else {
                    $isAttribute = false;
                }
                
                if($row->{$column} != '') {
                    
                    switch ($tag) {
                        
                        case 'content':
                        
                            $row->{$column} = wptexturize(wpautop($row->{$column}));

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
                            
                            if ($isAttribute) {
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
            
            $posts_group->appendChild($post);
            
        }
    }
    
    $root->appendChild($posts_group);
    
    $dom->appendChild($root);
    $content = $dom->saveXML();
    
    return $content;
}


/*
 * Regenerates main XML file when some post is edited.
 * 
 * This will make sure every translated page is up to date
 * with the original content, even if no translation is available
 * for all the strings.
 */
function wppo_apply_changes_to_translated_content($post_id, $post) {
    
    if ($post->post_type == 'page') {
        $coverage = 'static';
    } else {
        $coverage = 'dynamic';
    }
    
    wppo_update_xml($coverage);
    
    wppo_check_for_po_changes('force', $coverage);
    
}
add_action('post_updated', 'wppo_apply_changes_to_translated_content', 10, 2);
