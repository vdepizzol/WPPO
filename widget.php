<?php

wp_register_sidebar_widget(
    'wppo_language_chooser',
    __('Languages'),
    function() {
        
        $langs = wppo_get_all_available_langs();
        
        echo '<aside id="languages" class="widget widget_languages">'."\n".
             '<h3 class="widget-title">'.__('Languages').'</h3>'."\n".
             '<ul>'."\n";
        
        foreach ($langs as $lang_code => $lang_name) {
            echo '<li><a href="'.wppo_recreate_url($_SERVER['REQUEST_URI'], $lang_code).'">'.$lang_name.'</a></li>';
        }
        echo '</ul>'."\n".
             '</aside>'."\n";
    },
    array(
        'description' => 'List all available languages'
    )
);
