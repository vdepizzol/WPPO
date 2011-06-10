<?php

add_action('parse_request', function($request) {
    
    $lang_value = wppo_find_lang_in_uri();
    
    if (isset($lang_value)) {
        $request->query_vars['lang'] = $lang_value;
    }
    
});


add_action('set_lang_request', 'wppo_remove_lang_from_request_uri');

do_action('set_lang_request');


/*
 * This is an utility function that returns the language regex,
 * the changeable part of the URL and the home path
 */
function wppo_get_absolute_uri_vars() {
    
    /*
     * This is the default regular expression that filters
     * the language code, with or without the country code.
     * 
     * For instance:
     * es
     * pt-br
     * it
     * de-at
     */
    $lang_rule = "/(^([a-zA-Z]{2}(-[a-zA-Z]{2})?)\/|^([a-zA-Z]{2}(-[a-zA-Z]{2})?)$)/";
    
    $req_uri = WPPO_ABS_URI;
    
    /*
     * WPPO_HOME_URL is just like home_url(),
     * except that it won't come with the filter we just added
     */
    $home_path = parse_url(WPPO_HOME_URL);
    
    if ( isset($home_path['path']) )
        $home_path = $home_path['path'];
    else
        $home_path = '';
    $home_path = trim($home_path, '/');

    $req_uri = trim($req_uri, '/');
    $req_uri = preg_replace("|^$home_path|", '', $req_uri);
    $req_uri = trim($req_uri, '/');
    
    return compact('lang_rule', 'req_uri', 'home_path');
    
}

function wppo_find_lang_in_uri() {

    // $lang_rule, $req_uri, $home_path
    extract(wppo_get_absolute_uri_vars());
    
    $matches = array();
    
    preg_match($lang_rule, $req_uri, $matches);
    
    if (count($matches)) {
        return trim($matches[0], '/');
    } else {
        /*
         * If we don't get the language pattern, we'll check if the lang
         * is available as a query string
         */
        $query_string = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($query_string);
        if (isset($lang)) {
            return $lang;
        }
        
        return null;
    }

}

function wppo_remove_lang_from_request_uri() {

    global $wppo_cache;

    /*
     * Create cache of all available languages
     */
    wppo_get_lang();
    
    /*
     * $lang_rule, $req_uri, $home_path
     */
    extract(wppo_get_absolute_uri_vars());
    
    
    /*
     * Discover which language the URL is trying to
     * access
     */
    
    
    $lang = wppo_find_lang_in_uri();

    if (strlen($lang) == 2) {
        $lang_in_gettext_format = strtolower($lang);
    } elseif(strlen($lang) == 5) {
        $lang_in_gettext_format = strtolower(substr($lang, 0, 2)).'_'.strtoupper(substr($lang, 3, 2));
    } else {
        $lang_in_gettext_format = WPPO_DEFAULT_LANGUAGE_CODE;
    }
    
    /*
     * We won't remove lang from request URI if the informed language doesn't exists
     */
    if ($lang_in_gettext_format != WPPO_DEFAULT_LANGUAGE_CODE && !array_key_exists($lang_in_gettext_format, $wppo_cache['available_lang'])) {
        return false;
    }
    
    /*
     * Group extra queries to put in the right place later
     */
    if (strpos($req_uri, '?') !== false) {
        $req_uri = explode('?', $req_uri);
        $new_req_uri = $req_uri[0];
        unset($req_uri[0]);
        $extra_queries =  '?'.implode('?', $req_uri);
        $req_uri = $new_req_uri;
    } else {
        $extra_queries = '';
    }
    
    /*
     * Check if original URI comes with slash in the end.
     * If so, also adds a final slash in the end of the new URI
     */
    $abs_uri = explode('?', WPPO_ABS_URI);
    $abs_uri = $abs_uri[0];
    
    if (substr($abs_uri, -1) == '/') {
        $extra_queries = '/'.$extra_queries;
    }
    
    /*
     * Replace REQUEST_URI with a version that doesn't contain the
     * language part. This smart move avoids the plugin to have to hack
     * all the WordPress rewrite rules.
     * 
     * WordPress will then blindy feel fine with a fake permalink
     * that looks like the original.
     * 
     * Example:
     * 
     * Version used in WPPO:
     *      /website_path/pt-br/about/
     * New version that looks like default:
     *      /website_path/about/
     * 
     */
    
    if (preg_match($lang_rule, $req_uri)) {
        $_SERVER['REQUEST_URI'] = str_replace('//', '/', ('/' .$home_path . '/' . trim(preg_replace($lang_rule, '', $req_uri), '/') . $extra_queries));
    }
        
    /*
     * If the requested lang is the same as the default, we'll redirect
     * to the page without the language permalink.
     */
    if($lang_in_gettext_format == WPPO_DEFAULT_LANGUAGE_CODE && $_SERVER['REQUEST_URI'] != WPPO_ABS_URI) {
        header("Location: ".WPPO_URI_SCHEME.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
        exit;
    }

    return true;

}

/*
 * From time to time WordPress' Canonical API won't be happy
 * with the way URL is received and will reconstruct it entirely.
 * 
 * Before reloading the page with the new URL, we need to check if we 
 * need to put the language in place again.
 * 
 * 
 */

add_filter('redirect_canonical', function($absolute_uri) {
    
    $lang = wppo_get_lang();

    if ($lang == WPPO_DEFAULT_LANGUAGE_CODE) {
        return $absolute_uri;
    }
    
    $scheme = explode('://', $absolute_uri);
    $scheme = $scheme[0];
    
    $requested_uri = $scheme.'://'.$_SERVER['HTTP_HOST'].WPPO_ABS_URI;
    
    $parsed_absolute_url = wppo_recreate_url($absolute_uri, $lang, 'internal');
    
    /* 
     * We won't tell Canonical API to redirect to the same URL
     * originally received because infinite loops are bad.
     * Really bad.
     */
    if($parsed_absolute_url == $requested_uri) {
        return false;
    }
    
    return $parsed_absolute_url;
    
});



add_filter('query_vars', function($vars) {
    array_push($vars, 'lang');
    return $vars;
});


add_filter('body_class', function($classes) {
    array_push($classes, 'lang-'.strtolower(str_replace("_", "-", wppo_get_lang())));
    return $classes;
});


add_filter('home_url', 'wppo_rewrite_permalinks', 10);

function wppo_rewrite_permalinks($permalink) {    
    $lang = wppo_get_lang();
    return wppo_recreate_url($permalink, $lang, 'internal');
}



add_filter('wp_nav_menu_items', function($menu_items) {
    return wppo_recreate_links_in_html($menu_items, wppo_get_lang());
});

/*
 * This function will look for all the links around
 * any html and test if the link deserves a reconstructed URL
 */
function wppo_recreate_links_in_html($html, $lang) {
    
    global $wppo_recreate_links_temp_lang;
    $wppo_recreate_links_temp_lang = $lang;
    
    $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
    $output = preg_replace_callback('/'.$regexp.'/siU', 'wppo_recreate_links_preg_replace_callback', $html);
    
    unset($wppo_recreate_links_temp_lang);
    
    return $output;
    
}

function wppo_recreate_links_preg_replace_callback($matches) {
    
    global $wppo_recreate_links_temp_lang;
    $lang = $wppo_recreate_links_temp_lang;
    
    $new_url = wppo_recreate_url($matches[2], $lang, 'external');
    $matches[0] = str_replace($matches[2], $new_url, $matches[0]);
    return $matches[0];
}

/*
 * This is the main function that reconstructs the URL
 * with desired language.
 */
function wppo_recreate_url($url, $lang, $coverage = 'external') {
    
    $lang = strtolower(str_replace("_", "-", $lang));
    if($lang == WPPO_DEFAULT_LANGUAGE_CODE) {
        return $url;
    }
        
    $uri_vars = wppo_get_absolute_uri_vars();
    
    /*
     * If the URL comes starting with a slash, it means
     * it is pointing to current domain.
     */
    if (substr($url, 0, 1) == '/') {
        $url = WPPO_URI_SCHEME.'://' . $_SERVER['HTTP_HOST'] . $url;
    }
    
    if (substr($url, 0, 1) == '#') {
        return $url;
    }
    
    $old_url = $url;
    
    
    /*
     * With coverage as "external", we also check
     * if the link is pointing to another domain.
     */
    if ($coverage == 'external') {
    
        /*
         * We're not going to add language support to
         * links that don't point to our domain :)
         */
        
        if(strpos($url, '://') === false) {
            return $url;
        } else {
            $domain = explode('://', $url);
            if(strpos($domain[1], '/') !== false) {
                $domain = explode('/', $domain[1]);
                $domain = $domain[0];
            }
        
            if ($_SERVER['HTTP_HOST'] != $domain) {
                return $url;
            }
        }
    }
    
    /*
     * Stores the URL address without the domain and the home path
     */
     
    // Removes scheme if it exists
    $file_url = explode('://', $url);
    unset($file_url[0]);
    $file_url = implode('://', $file_url);
    
    // Removes host
    $file_url = explode($_SERVER['HTTP_HOST'], $file_url);
    $file_url = $file_url[1];
    
    // Removes home path
    if ($uri_vars['home_path'] != '') {
        $file_url = explode($uri_vars['home_path'], $file_url);
        unset($file_url[0]);
        $file_url = ltrim(implode($uri_vars['home_path'], $file_url), '/');
    } else {
        $file_url = ltrim($file_url, '/');
    }
    
    
    if ($coverage == 'external') {
        
        /*
         * Checks if link is pointing to a file.
         * If so, we'll not change the link.
         */
        
        if (file_exists(ABSPATH.$file_url) && $file_url != '') {
            return $url;
        }
    
    }
    
    /*
     * Check if there is already a language prefix in
     * the informed URL.
     * If so, keeps the URL intact.
     */
    
    $matches = array();
    preg_match($uri_vars['lang_rule'], $file_url, $matches);
    if (count($matches)) {
        return $url;
    }
    
    /*
     * After not matching any possible break down,
     * Apply the changes.
     */
    
    global $wp_rewrite;
    
    //if ($wp_rewrite->using_permalinks()) {
            
        if ($uri_vars['home_path'] != '') {
            $common_url = '://'.$_SERVER['HTTP_HOST'].'/'.$uri_vars['home_path'].'/';
        } else {
            $common_url = '://'.$_SERVER['HTTP_HOST'].'/';
        }
        
        $new_url = str_replace($common_url, $common_url.$lang.'/', $url);

    /*
    } else {

        if (strpos($url, '?') === false) {
            $glue = '?';
        } else {
            $glue = '&';
        }

        $new_url = $url.$glue.'lang='.$lang;
        
    }
    */

    
    return $new_url;
    
}
