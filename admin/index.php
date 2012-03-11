<div class="wrap">
    <div id="icon-edit-pages" class="icon32"><br /></div>
    <h2>
        Translations
        <a class="button add-new-h2" href="?page=wppo&checkforlangupdates=1">Check for translation updates</a>
    </h2>
    
    <div class="metabox-holder" style="width: 60%; float: left;">
        <div class="postbox">
            <h3 style="cursor: default;">Recent activities</h3>
            <style type="text/css">
                .wppo-recent-activities li {
                    margin: 0;
                    padding: 10px 15px;
                    border-bottom: 1px solid #d9d9d9;
                }
                .wppo-recent-activities li .date {
                    display: inline-block;
                    width: 100px;
                    font-size: 11px;
                }
                .wppo-recent-activities li:last-child {
                    border-bottom: 0;
                    -moz-border-radius: 0 0 5px 5px;
                    -webkit-border-radius: 0 0 5px 5px;
                    border-radius: 0 0 5px 5px;
                }
                .wppo-recent-activities li.uncommited {
                    background: #fefbdd;
                }
                .wppo-recent-activities li.uncommited:nth-child(odd) {
                    background: #fef9d0;
                }
                .wppo-recent-activities li.last-pot-update {
                    background: #fbcdcd !important;
                }
                .wppo-recent-activities li.last-pot-update.ok {
                    background: #daf6bf !important;
                }
                .wppo-recent-activities li:nth-child(odd) {
                    background: #fff;
                }
            </style>
            <ul class="wppo-recent-activities">
                <?php
                $showed = false;
                foreach($grouped_posts as $i => $group): ?>
                <?php if($group[0]->post_modified_gmt < date("Y-m-d H:i:s", get_option('wppo_last_pot_generate')) && !$showed):
                $showed = true;
                ?> 
                <li class="last-pot-update<?php if($i == 0) echo ' ok';?>"><span class="date" title="<?php echo date('Y/m/d H:m:i', get_option('wppo_last_pot_generate')); ?>"><?php echo date('Y/m/d', get_option('wppo_last_pot_generate')); ?></span>Last time content changes were sent to translators <a class="button" href="?page=wppo&generatepot=1">Send again</a></li>
                <?php endif; ?>
                <li<?php if (!$showed) echo ' class="uncommited"'; ?>>
                    <span class="date" title="<?php echo $group[0]->post_modified_gmt; ?>"><?php echo $group[0]->date; ?></span><?php echo $group[0]->type; ?> <a href="<?php echo $group[0]->link; ?>"><?php echo get_the_title($group[0]->post_parent); ?></a> <?php echo $group[0]->by; ?>
                </li>
                <?php endforeach; ?>
                
                <?php if (!$showed): ?>
                <li class="last-pot-update"><span class="date" title="<?php echo date('Y/m/d H:m:i', get_option('wppo_last_pot_generate')); ?>"><?php echo date('Y/m/d', get_option('wppo_last_pot_generate')); ?></span>Last time content changes were sent to translators <a class="button" href="?page=wppo&generatepot=1">Send again</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <div class="metabox-holder" style="float: right; width: 38%;">
        <div class="postbox">
            <h3 style="cursor: default;">Languages</h3>
            <style type="text/css">
                .wppo-languages-list li {
                    margin: 0;
                    padding: 10px 15px;
                    border-bottom: 1px solid #d9d9d9;
                }
                .wppo-languages-list li:nth-child(odd) {
                    background: #f9f9f9;
                }
                .wppo-languages-list li:last-child {
                    border-bottom: 0;
                    -moz-border-radius: 0 0 5px 5px;
                    -webkit-border-radius: 0 0 5px 5px;
                    border-radius: 0 0 5px 5px;
                }
                .wppo-languages-list li .lang-code {
                    display: inline-block;
                    width: 3.8em;
                }
                .wppo-languages-list li .obs {
                    font-weight: bold;
                    border-bottom: 1px dotted black;
                    color: #BC0B0B;
                    cursor: help;
                }
                .wppo-languages-list li .actions {
                    font-size: 11px;
                    float: right;
                }
                .wppo-languages-list li .actions a {
                    text-decoration: none;
                }
                .wppo-languages-list li .actions a:hover {
                    text-decoration: underline;
                }
                .wppo-languages-list li .actions a.delete {
                    color: #BC0B0B !important;
                }
            </style>
            <ul class="wppo-languages-list">
                <li>
                    <span class="lang-code"><code><?php echo WPPO_DEFAULT_LANGUAGE_CODE; ?></code></span> <?php echo WPPO_DEFAULT_LANGUAGE_NAME; ?>
                    <div class="actions"><em>Default language</em></div>
                </li>
                <?php foreach($languages as $l): ?>
                <li>
                    <?php
                    if($l->lacks_wp_mo && $l->lacks_theme_mo) {
                        $ps = '<span class="obs" title="WordPress and current theme is not available in this language">*</span>';
                    } elseif($l->lacks_wp_mo) {
                        $ps = '<span class="obs" title="WordPress is not available in this language">*</span>';
                    } elseif($l->lacks_theme_mo) {
                        $ps = '<span class="obs" title="Current theme is not available in this language">*</span>';
                    } else {
                        $ps = '';
                    }
                    ?>
                    <span class="lang-code"><code><?php echo $l->lang_code; ?></code></span> <?php echo $l->lang_name; ?> (<?php echo ($l->percent) ? $l->percent : 0; ?>%) <?php echo $ps;?>
                    <div class="actions">
                        <?php if($l->lang_status == 'hidden'): ?>
                            <a href="?page=wppo&amp;lang_code=<?php echo $l->lang_code; ?>&amp;action=changelanguagestatus&amp;lang_status=1">Enable</a>
                            | <a href="?page=wppo&amp;lang_code=<?php echo $l->lang_code; ?>&amp;action=deletelanguage" class="delete">Delete</a>
                        <?php endif; ?>
                        
                        <?php if($l->lang_status == 'visible'): ?>
                            <a href="?page=wppo&amp;lang_code=<?php echo $l->lang_code; ?>&amp;action=changelanguagestatus&amp;lang_status=0">Deactivate</a>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="postbox">
            <h3 style="cursor: default;">Add Language</h3>
            <style type="text/css">
                .wppo-add-language {
                    padding: 12px !important;
                }
                .wppo-add-language form {
                    overflow: hidden;
                }
                .wppo-add-language .field {
                    margin: 0 0 6px;
                    overflow: hidden;
                }
                .wppo-add-language label {
                    display: inline-block;
                    padding-right: 6px;
                    width: 4em;
                }
                .wppo-add-language abbr {
                    border-bottom: 1px dotted black;
                    cursor: help;
                }
                .wppo-add-language input {
                    float: none !important;
                }
                .wppo-add-language input[name="lang_name"] {
                    margin-right: 12px;
                }
                .wppo-add-language input[name="lang_code"] {
                    width: 4em;
                }
                .wppo-add-language .submit-area {
                    clear: both;
                    margin-top: 10px;
                    text-align: right;
                }
            </style>
            <div class="wppo-add-language">
                <form action="?page=wppo&amp;action=addlanguage" method="post">
                    <div class="howto field">
                        <label for="lang_name">Name</label>
                        <input id="lang_name" name="lang_name" type="text" />
                    </div>
                    <div class="howto field">
                        <label for="lang_code">Code</label>
                        <input id="lang_code" name="lang_code" type="text" />
                        in <code style="font-style: normal;"><abbr title="ISO 639 two-letter language code (lowercase)">ll</abbr></code> or <code style="font-style: normal;"><abbr title="ISO 639 two-letter language code (lowercase)">ll</abbr>_<abbr title="ISO 3166 two-letter country code (uppercase)">CC</abbr></code> format
                    </div>
                    <div class="submit-area">
                        <input type="submit" value="Add Language" class="button-secondary" />
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
