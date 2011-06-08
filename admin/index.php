<div class="wrap">
    <div id="icon-edit-pages" class="icon32"><br /></div>
    <h2>
        Translations
        <a class="button add-new-h2" href="?page=wppo&checkforlangupdates=1">Check for translation updates</a>
    </h2>
    
    <p>
        <a style="float: right;" class="button rbutton" href="?page=wppo&generatepot=1">Generate POT file</a> <br style="clear:both;" />
    </p>
    
    <div class="metabox-holder" style="width: 60%; float: left;">
        <div class="postbox">
            <h3>Recent activities</h3>
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
                    }
                    .wppo-recent-activities li.uncommited {
                        background: #fefbdd;
                    }
                    .wppo-recent-activities li.uncommited:nth-child(odd) {
                        background: #fef9d0;
                    }
                    .wppo-recent-activities li.last-pot-update {
                        background: #fdf3a3 !important;
                    }
                    .wppo-recent-activities li:nth-child(odd) {
                        background: #f9f9f9;
                    }
                </style>
                <ul class="wppo-recent-activities">
                    <?php
                    $showed = false;
                    foreach($grouped_posts as $group): ?>
                    <?php if($group[0]->post_modified_gmt < date("Y-m-d H:i:s", get_option('wppo_last_pot_generate')) && !$showed):
                    $showed = true;
                    ?> 
                    <li class="last-pot-update"><span class="date" title="<?php echo date('Y/m/d H:m:i', get_option('wppo_last_pot_generate')); ?>"><?php echo date('Y/m/d', get_option('wppo_last_pot_generate')); ?></span>Changes were sent to translators <a class="button" href="?page=wppo&generatepot=1">Send changes to translators</a></li>
                    <?php endif; ?>
                    <li<?php if (!$showed) echo ' class="uncommited"'; ?>>
                        <span class="date" title="<?php echo $group[0]->post_modified_gmt; ?>"><?php echo $group[0]->date; ?></span><?php echo $group[0]->type; ?> <a href="<?php echo $group[0]->link; ?>"><?php echo get_the_title($group[0]->post_parent); ?></a> <?php echo $group[0]->by; ?>
                    </li>
                    <?php endforeach; ?>
                    
                    <?php if (!$showed): ?>
                    <li class="last-pot-update"><span class="date"><?php echo date('Y/m/d', get_option('wppo_last_pot_generate')); ?></span>Last time changes were sent to translators <a class="button" href="?page=wppo&generatepot=1">Send changes to translators</a></li>
                    <?php endif; ?>
                </ul>
    
            </div>
        </div>
    </div>
    
    <div class="metabox-holder" style="float: right; width: 38%;">
        <div class="postbox">
            <h3>Languages</h3>
            <div class="inside" style="padding: 15px;">
            <!--<ul> -->
                <?php foreach($languages as $l): ?>
                <li>
                    <?php echo $l->lang_name; ?> (<?php echo ($l->percent) ? $l->percent : 0; ?>%)<br />
                    <?php if($l->lang_status == 'hidden'): ?><a href="?page=wppo&lang_code=<?php echo $l->lang_code; ?>&lang_status=1">Enable</a><?php else: ?>Enable<?php endif; ?> |
                    <?php if($l->lang_status == 'visible'): ?><a href="?page=wppo&lang_code=<?php echo $l->lang_code; ?>&lang_status=0">Disable</a><?php else: ?>Disable<?php endif; ?>
                </li>
                <?php endforeach; ?>
            <!--</ul> -->
    
            </div>
        </div>
    </div>
</div>
