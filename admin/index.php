<div class="wrap">
    <div id="icon-edit-pages" class="icon32"><br /></div>
    <h2>
        Translations
        <a class="button add-new-h2" href="?page=wppo&checkforlangupdates=1">Check for translation updates</a>
    </h2>
    
    <div class="metabox-holder" style="width: 60%; float: left;">
        <div class="postbox">
            <h3 style="cursor: default;">Recent activities</h3>
            <div class="inside">
                <style type="text/css">
                    .wppo-recent-activities li {
                        margin: 0;
                        padding: 5px 15px;
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
                        background: #f9f9f9;
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
    </div>
    
    <div class="metabox-holder" style="float: right; width: 38%;">
        <div class="postbox">
            <h3 style="cursor: default;">Languages</h3>
            <div class="inside">
                <style type="text/css">
                    .wppo-languages-list li {
                        margin: 0;
                        padding: 5px 15px;
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
                        English
                        <div class="actions"><em>Default language</em></div>
                    </li>
                    <?php foreach($languages as $l): ?>
                    <li>
                        <?php echo $l->lang_name; ?> (<?php echo ($l->percent) ? $l->percent : 0; ?>%)
                        <div class="actions">
                            <?php if($l->lang_status == 'hidden'): ?>
                                <a href="?page=wppo&lang_code=<?php echo $l->lang_code; ?>&lang_status=1">Enable</a>
                                | <a href="?page=wppo&lang_code=<?php echo $l->lang_code; ?>&action=delete" class="delete">Delete</a>
                            <?php endif; ?>
                            
                            <?php if($l->lang_status == 'visible'): ?>
                                <a href="?page=wppo&lang_code=<?php echo $l->lang_code; ?>&lang_status=0">Deactivate</a>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
