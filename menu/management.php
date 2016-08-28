<?php
use RewriteLinkManager\Rewrite\Manager;

$taxonomies = get_taxonomies( [ '_builtin' => false ] );
// Add default category
$taxonomies['category'] = 'category';
$taxonomies_selected = [];
$ts_settings = get_option( 'ts_settings' );
if( !empty( $ts_settings['taxonomy_remove'] ) ) {
    $taxonomies_selected = $ts_settings['taxonomy_remove'];
} else {
    $taxonomies_selected = [];
}
$prevent_duplicate = 0;
if( !empty( $ts_settings['prevent_duplicate']) ) {
    $prevent_duplicate = intval( $ts_settings['prevent_duplicate'] );
}
?>
<div class="wrap">
    <div id="icon-wpml" class="icon32"><br /></div>
    <h2><?php echo __('Setup rewrite link manager', 'rewrite-link-manager') ?></h2>
    <form name="icl_save_taxonomy_options" id="icl_save_taxonomy_options" action="" method="post">
    <h3><?php _e('Options', 'rewrite-link-manager')?></h3>
        <label><input type="checkbox" name="prevent_duplicate" value="1"
                      <?php if( $prevent_duplicate == 1 ):?>checked="checked"<?php endif;?>/><?php _e( 'Prevent duplicate slug (Extend id to end link)', 'rewrite-link-manager' );?></label>
    <h3><?php _e( 'Taxonomy list', 'rewrite-link-manager' )?></h3>

    <?php wp_nonce_field( 'icl_taxonomy_slug_save' ); ?>
    <ul>
        <?php foreach ( $taxonomies as $taxonomy ) { ?>
        <li>
            <label><input type="checkbox" name="taxonomy_remove[]" value="<?php echo $taxonomy;?>"
            <?php if( in_array( $taxonomy, $taxonomies_selected) ):?>checked="checked"<?php endif;?>/><?php echo ucfirst( $taxonomy );?></label>
        </li>
        <?php } ?>
    </ul>
    <p>
        <a class="button" name="ts_save_taxonomy" id="ts_save_taxonomy" href="#"><?php echo __('Save taxonomy','rewrite-link-manager') ?></a>
        <span class="icl_ajx_response" id="icl_ajx_response1"></span>
    </p>    
    </form>
    <p>
        <img id="alp_ajx_ldr_1" src="<?php echo REWRITE_LINK_MANAGER_PATH ?>/res/img/ajax-loader.gif" width="16" height="16" style="display:none" alt="loading" />
    </p>
    <p>
    <?php
        $rewriteManager = Manager::getInstance();

        highlight_string( '<?php ' . $rewriteManager->displayOut( true ) ) ;
    ?>
    </p>
    <p>
        <img id="alp_ajx_ldr_2" src="<?php echo REWRITE_LINK_MANAGER_PATH; ?>/res/img/ajax-loader.gif" width="16" height="16" style="display:none" alt="loading" />
    </p>

</div>
<?php
$posttypes = get_post_types( [ '_builtin' => false, 'publicly_queryable' => true ] );
$posttypes_selected = [];
if( !empty( $ts_settings['posttype_remove'] )) {
    $posttypes_selected = $ts_settings['posttype_remove'];
} else {
    $posttypes_selected = [];
}
?>
<div class="wrap">
    <div id="icon-wpml" class="icon32"><br /></div>
    <h2><?php echo __('Setup Post type remove slug', 'rewrite-link-manager') ?></h2>
    <h3><?php _e('Options', 'rewrite-link-manager')?></h3>
    <form name="icl_save_taxonomy_options" id="icl_save_posttype_options" action="" method="post">
        <?php wp_nonce_field('icl_taxonomy_slug_save'); ?>
        <ul>
            <?php foreach ($posttypes as $posttype) { ?>
                <li>
                    <label><input type="checkbox" name="posttype_remove[]" value="<?php echo $posttype;?>"
                          <?php if( in_array( $posttype, $posttypes_selected) ):?>checked="checked"<?php endif;?>/><?php echo ucfirst( $posttype );?></label>
                </li>
            <?php } ?>
        </ul>
        <p>
            <a class="button" name="ts_save_posttype" id="ts_save_posttype" href="#"><?php echo __('Save post type','rewrite-link-manager') ?></a>
            <span class="icl_ajx_response" id="icl_ajx_response_pt"></span>
        </p>
    </form>
    <p>
        <img id="alp_ajx_ldr_pt" src="<?php echo REWRITE_LINK_MANAGER_PATH; ?>/res/img/ajax-loader.gif" width="16" height="16" style="display:none" alt="loading" />
    </p>
</div>
<div class="wrap">
<div id="icon-wpml" class="icon32"><br /></div>
<h2><?php echo __('Setup link extension', 'rewrite-link-manager') ?></h2>
<h3><?php _e('Link extension', 'rewrite-link-manager')?></h3>
<form name="icl_save_extension_link" id="icl_save_extension_link" action="" method="post">
    <?php
    $extension_links = [];
    if( !empty( $ts_settings['link_extension'] ) ) {
        $extension_links = $ts_settings['link_extension'];
    } else {
        $extension_links = [];
    }
    $link_dot = '';
    $enable_link_extension = 0;
    if( !empty( $ts_settings['link_dot'] )) {
        $link_dot = $ts_settings['link_dot'];
    }
    if( !empty( $ts_settings['enable_link_extension'] )) {
        $enable_link_extension = $ts_settings['enable_link_extension'];
    }
    wp_nonce_field('icl_taxonomy_slug_save');
    ?>
    <p>
        <label><input type="checkbox" name="enable_link_extension" value="1"
                      <?php if( $enable_link_extension ):?>checked="checked"<?php endif;?>/><?php _e( 'Enable', 'rewrite-link-manager');?></label>
    </p>
        <p>
            <label><?php _e( 'Link extension', 'rewrite-link-manager');?></label>
            <input type="text" name="link_dot" value="<?php echo $link_dot;?>"> <?php _e( '(Not start with dot, ex: html, htm)', 'rewrite-link-manager');?>
        </p>
        <p>
            <label><input type="checkbox" name="extension_link[]" value="page"
                          <?php if( in_array( 'page', $extension_links) ):?>checked="checked"<?php endif;?>/><?php _e( 'Page', 'rewrite-link-manager');?></label>
        </p>
        <ul>
            <?php foreach ($taxonomies as $taxonomy) {?>
                <li>
                    <label><input type="checkbox" name="extension_link[]" value="<?php echo $taxonomy;?>"
                                  <?php if( in_array( $taxonomy, $extension_links) ):?>checked="checked"<?php endif;?>/><?php echo ucfirst( $taxonomy );?></label>
                </li>
            <?php }?>
        </ul>
        <ul>
            <?php foreach ($posttypes as $posttype) {?>
                <li>
                    <label><input type="checkbox" name="extension_link[]" value="<?php echo $posttype;?>"
                                  <?php if( in_array( $posttype, $extension_links) ):?>checked="checked"<?php endif;?>/><?php echo ucfirst( $posttype );?></label>
                </li>
            <?php }?>
        </ul>
        <p>
            <a class="button" name="ts_save_link_extension" id="ts_save_link_extension" href="#"><?php echo __('Save link extension','rewrite-link-manager') ?></a>
            <span class="icl_ajx_response" id="icl_ajx_response_lx"></span>
        </p>
</form>
<p>
    <img id="alp_ajx_ldr_lx" src="<?php echo REWRITE_LINK_MANAGER_PATH ?>/res/img/ajax-loader.gif" width="16" height="16" style="display:none" alt="loading" />
</p>
</div>