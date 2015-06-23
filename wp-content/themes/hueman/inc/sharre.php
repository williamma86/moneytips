<?php $shareUrl = get_permalink(get_the_ID()); ?>
<div class="sharrre-container">
    <span>Share</span>
    <div id="twitter" class="sharrre" data-title="Tweet" data-text="<?php the_title(); ?>" data-url="<?php echo $shareUrl; ?>">
        <a class="box" href="#">
            <div class="count" href="#">2</div>
            <div class="share">
                <i class="fa fa-twitter"></i>
            </div>
        </a>
    </div>
    <div id="facebook" class="sharrre" data-title="Like" data-text="<?php the_title(); ?>" data-url="<?php echo $shareUrl; ?>">
        <a class="box" href="#">
            <div class="count" href="#">3</div>
            <div class="share">
                <i class="fa fa-facebook-square"></i>
            </div>
        </a>
    </div>
    <div id="googleplus" class="sharrre" data-title="+1" data-text="<?php the_title(); ?>" data-url="<?php echo $shareUrl; ?>">
        <a class="box" href="#">
            <div class="count" href="#">0</div>
            <div class="share">
                <i class="fa fa-google-plus-square"></i>
            </div>
        </a>
    </div>
    <div id="pinterest" class="sharrre" data-title="Pin It" data-text="<?php the_title(); ?>" data-url="<?php echo $shareUrl; ?>">
        <a class="box" rel="nofollow" href="#">
            <div class="count" href="#">0</div>
            <div class="share">
                <i class="fa fa-pinterest"></i>
            </div>
        </a>
    </div>
</div>
<script type="text/javascript">
    jQuery(document).ready(function(){
        jQuery('#twitter').sharrre({
            share: {
                twitter: true
            },
            template: '<a class="box" href="#"><div class="count" href="#">{total}</div><div class="share"><i class="fa fa-twitter"></i></div></a>',
            enableHover: false,
            enableTracking: true,
            buttons: { twitter: {via: ''}},
            click: function(api, options){
                api.simulateClick();
                api.openPopup('twitter');
            }
        });
        jQuery('#facebook').sharrre({
            share: {
                facebook: true
            },
            template: '<a class="box" href="#"><div class="count" href="#">{total}</div><div class="share"><i class="fa fa-facebook-square"></i></div></a>',
            enableHover: false,
            enableTracking: true,
            click: function(api, options){
                api.simulateClick();
                api.openPopup('facebook');
            }
        });
        jQuery('#googleplus').sharrre({
            share: {
                googlePlus: true
            },
            template: '<a class="box" href="#"><div class="count" href="#">{total}</div><div class="share"><i class="fa fa-google-plus-square"></i></div></a>',
            enableHover: false,
            enableTracking: true,
//            urlCurl: 'http://demo.alxmedia.se/hueman/wp-content/themes/hueman/js/sharrre.php',
            click: function(api, options){
                api.simulateClick();
                api.openPopup('googlePlus');
            }
        });
        jQuery('#pinterest').sharrre({
            share: {
                pinterest: true
            },
            template: '<a class="box" href="#" rel="nofollow"><div class="count" href="#">{total}</div><div class="share"><i class="fa fa-pinterest"></i></div></a>',
            enableHover: false,
            enableTracking: true,
            buttons: {
                pinterest: {
                    description: '<?php the_title(); ?>',media: '<?php if ( has_post_thumbnail() ) {the_post_thumbnail('thumb-large'); } ?>'}
            },
            click: function(api, options){
                api.simulateClick();
                api.openPopup('pinterest');
            }
        });
    });
</script>