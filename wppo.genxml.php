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

// This code by now only generates an XML with all the content from
// WordPress database.

require_once(ABSPATH."wp-config.php");

function wppo_generate_po_xml($post_type) {
    global $wpdb;
    
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
