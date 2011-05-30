<?php

add_action('parse_request', function($request) {
    
    $lang_value = wppo_find_lang_in_uri();
    
    if (isset($lang_value)) {
        $request->query_vars['lang'] = $lang_value;
    }
    
});


add_action('set_lang_request', 'wppo_remove_lang_from_request_uri');


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
    
    return (count($matches)) ? trim($matches[0], '/') : null;
}

function wppo_remove_lang_from_request_uri() {
    
    /*
     * $lang_rule, $req_uri, $home_path
     */
    extract(wppo_get_absolute_uri_vars());
    
    
    /*
     * Discover which language the URL is trying to
     * access
     */
    
    // We need to check if the language exists
    // FIXME
    
    $lang = wppo_find_lang_in_uri();
    
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
        $_SERVER['REQUEST_URI'] = '/' .$home_path . '/' . trim(preg_replace($lang_rule, '', $req_uri), '/') . $extra_queries;
    }
    
}

/*
 * From time to time WordPress' Canonical API won't be happy
 * with the way URL is received and will reconstruct it entirely.
 * 
 * Before reloading the page with the new URL, we need to check if we 
 * need to put the language in place again.
 * 
 */
function add_lang_to_uri($url) {
    
    $lang = wppo_get_lang();
        
    if($lang != 'C') {
        
        $old_url = $url;
    
        $uri_vars = wppo_get_absolute_uri_vars();
        
        $lang = strtolower(str_replace('_', '-', $lang));
        
        
        // Removes scheme if it exists
        $url = explode('://', $url);
        unset($url[0]);
        $url = implode('://', $url);
        
        // Removes host
        $url = explode($_SERVER['HTTP_HOST'], $url);
        $url = $url[1];
        
        // Removes home path
        $url = explode($uri_vars['home_path'], $url);
        unset($url[0]);
        $url = implode($uri_vars['home_path'], $url);
        
        
        /*
         * Constructs the new absolute URL with the initial part
         * of the original one
         */
        $before_url = substr($old_url, 0, (strlen($old_url) - strlen($url)));
        
        
        // Checks if language is already part of the path
        if (substr($old_url, strlen($before_url)+1, strlen($lang)) != $lang) {
            $url = '/'.$lang.$url;
        }
        
        $url = $before_url . $url;
        
    }
    
    return $url;
}


add_filter('redirect_canonical', function($absolute_uri) {
    
    $parsed_absolute_url = add_lang_to_uri($absolute_uri);
    
    $uri_reference = substr($parsed_absolute_url, (strlen($parsed_absolute_url) - strlen(WPPO_ABS_URI)), strlen($parsed_absolute_url));
    
    /* 
     * We won't tell Canonical API to redirect to the same URL
     * originally received because infinite loops are bad.
     */
    if($uri_reference == WPPO_ABS_URI) {
        return false;
    }
    
    return $uri_reference;
    
});



add_filter('query_vars', function($vars) {
    array_push($vars, 'lang');
    return $vars;
});


add_filter('body_class', function($classes) {
    array_push($classes, 'lang-'.strtolower(str_replace("_", "-", wppo_get_lang())));
    return $classes;
});


/*add_filter('generate_rewrite_rules', function($rules) {
    print_r($rules);
    die;
    
    $lang_rule = "([a-z\-]{2,5})/";
    
    $new_rules = array();
    foreach($rules as $regex => $url) {
        $regex = $lang_rule.$regex;
        $url = str_replace(array('[1]', '[2]', '[3]'), array('[2]', '[3]', '[4]'), $url);
        $url = $url.'&lang=$matches[1]';
        $new_rules[$regex] = $url;
    }
    
    //print_r($new_rules);
    //return $new_rules;
    
    die;
});
*/


/*
add_filter('rewrite_rules_array', function($rules) {
    
    $new_rules = array();
    
    $lang_rule = '([a-z-]{2,5})';
    
    // Home page
    $new_rules[$lang_rule.'/?$'] = 'index.php?lang=$matches[1]';
    
    foreach($rules as $regex => $url) {
        
        if (strpos($regex, '/?$') !== false) {
            
            $new_regex = $lang_rule.'/'.$regex;
            
            $new_url = preg_replace_callback('/\[(\d{1,2})\]/', function($match) {
                return "[".($match[1]+1)."]";
            }, $url);
            
            $new_url = $url.'&lang=$matches[1]';
            
            $new_rules[$new_regex] = $new_url;
            $new_rules[$regex] = $url;
        }
        
    }
    
    //$new_rules[] 'index.php?pagename=$matches2&lang=$matches[1]';
    
    //$lang_rule = "([a-z]{2}_[A-Z]{2})";
    //$lang_rule = "([a-z-]{2,5})/?$";
    //$new_rules[$lang_rule] = 'index.php?lang=$matches[1]';
    
    //$new_rules['([a-z-]{2,5})'.'/' .'([0-9]{4})/?$'] = 'index.php?year=$matches[2]' . '&lang=$matches[1]';
    //$new_rules['[(.+?)(/[([a-z]{2}_[A-Z]{2})]+)?/?$]'] = 'index.php?pagename=$matches[1]&lang=$matches[2]';
    
    
    //echo '<pre>';
    //print_r($new_rules + $rules);
    //echo '</pre>';
    
    //die;
    return $new_rules;
    //return $new_rules + $rules;
});
*/

/*
add_filter('page_link', function($link) {
    //var_dump($link);
    //die;
});
*/


add_filter('home_url',              'wppo_rewrite_permalinks', 10, 2);

function wppo_rewrite_permalinks($permalink, $path) {
    
    $uri_vars = wppo_get_absolute_uri_vars();
    
    $lang = strtolower(str_replace("_", "-", wppo_get_lang()));
    if($lang == 'c') {
        return $permalink;
    }
    
    $common_url = '://'.$_SERVER['HTTP_HOST'].'/'.$uri_vars['home_path'].'/';
    
    return str_replace($common_url, $common_url.$lang.'/', $permalink);
}


do_action('set_lang_request');
