<div class="wrap">
    <div id="icon-edit-pages" class="icon32"><br /></div>
    <h2>Translations</h2>
    
    <p><a style="float:right;" class="button rbutton" href="?page=wppo&generate=1">Generate POT file</a> <br style="clear:both;" /></p>
    
    <div class="metabox-holder">
        <div class="postbox">
            <h3>Recent activities</h3>
            <div class="inside" style="padding: 15px;">
            <!--<ul> -->
                <?php $showed = false; foreach($grouped_posts as $group): ?>
                <?php if($group[0]->post_modified_gmt < date("Y-m-d H:i:s", get_option('wppo_last_pot_generate')) && !$showed):
                $showed = true;
                ?> 
                <li>===============</li>
                <?php endif; ?>
                <li>
                    <?php echo $group[0]->type; ?><a href="<?php echo $group[0]->link; ?>"><?php echo get_the_title($group[0]->post_parent); ?></a> <?php echo $group[0]->by; ?>
                </li>
                <?php endforeach; ?>
            <!--</ul> -->
    
            </div>
        </div>
    </div>
    
    <div class="metabox-holder">
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
