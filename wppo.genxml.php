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

require_once (ABSPATH . "wp-config.php");

function wppo_generate_po_xml () {
  global $wpdb;

  $myrows = $wpdb->get_results ("SELECT ID, post_content, post_title, post_excerpt, post_name
                                 FROM wp_posts
                                 WHERE post_type != 'revision' && post_type != 'nav_menu_item'");



  $broken_dom_pages = array ();

  $dom = new DOMDocument ('1.0', 'utf-8');
  $dom->formatOutput = true;
  $root = $dom->createElement ("website");

  foreach ($myrows as $key => $value) {
    $page = $dom->createElement ("page");

    // ID
    $page_id = $dom->createAttribute ('id');
    $page_id_value = $dom->createTextNode ($value->ID);
    $page_id->appendChild ($page_id_value);
    $page->appendChild ($page_id);

    // page_title
    $page_title = $dom->createElement ("title");
    $page_title_value = $dom->createTextNode ($value->post_title);
    $page_title->appendChild ($page_title_value);
    $page->appendChild ($page_title);
    
    // page_excerpt
    $page_excerpt = $dom->createElement ("excerpt");
    $page_excerpt_value = $dom->createTextNode ($value->post_excerpt);
    $page_excerpt->appendChild ($page_excerpt_value);
    $page->appendChild ($page_excerpt);

    // page_name
    $page_name = $dom->createElement ("name");
    $page_name_value = $dom->createTextNode ($value->post_name);
    $page_name->appendChild ($page_name_value);
    $page->appendChild ($page_name);

    // page_content
    $value->post_content = wpautop ($value->post_content);

    $content_xml = $dom->createDocumentFragment ();
    $content_xml->appendXML ('<html>'.$value->post_content.'</html>');

    if ($content_xml == false) {
      $broken_dom_pages[] = $value->ID;
    }

    $page_content = $dom->createElement ("content");
    $page_content->appendChild ($content_xml);
    $page->appendChild ($page_content);

    $root->appendChild ($page);
  }

  $dom->appendChild ($root);
  $content = $dom->saveXML ();
  return $content;
}
?>
