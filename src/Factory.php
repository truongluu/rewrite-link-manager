<?php
/**
 * Created by PhpStorm.
 * User: xuantruong
 * Date: 12/27/15
 * Time: 1:39 PM
 */
namespace  RewriteLinkManager;

use RewriteLinkManager\Rewrite\Manager;

class Factory {
    
    protected static  $_instance = null;
    protected $settings;
    private  $data_return;
    private $rewriteManager;

    const OPTION_NAME = 'rewrite_link_manager';

    function __construct( $init = true )
    {
        if ( $init ) {
            $this->data_return = [];
            $this->rewriteManager = Manager::getInstance();
            add_action( 'plugins_loaded', [ $this, 'pluginsLoaded' ], 0 );
            add_action( 'init', [ $this, 'init' ] );

        }

    }

    public static function instance( $init )
    {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( $init);
        }
        return self::$_instance;
    }

    function init()
    {
        // Check ajax request
        $this->ajaxResponses();
        // Setup the WP-Admin resources
        add_action( 'admin_init', [ $this, 'adminInit' ] );
        // Setup the WP-Admin menus
        add_action('admin_menu', [ $this, 'menu' ]);
        add_action( 'created_term', [ $this, 'createdTerm' ], 10, 3 );
        add_action( 'edit_terms', [ $this, 'editTerms' ], 999, 2 );
        add_action( 'pre_delete_term', [ $this, 'preDeleteTerm' ], 10, 2 );
        add_action( 'parse_request', [$this, 'parseRequest' ], 11, 2 );
        add_action( 'pre_get_posts', [ $this, 'preGetPosts'] );
        add_filter( 'term_link', [ $this, 'termLink' ], 10, 3 );
        add_filter( 'post_type_link', [ $this, 'postTypeLink' ], 10, 3 );
        add_filter( 'plugin_action_links' , [ $this, 'pluginActionLink' ], 10, 2 );

        $this->addCustomRewrite();
        $this->initActiveLinkPage();
        return true;
    }

    function ts_connect_fs( $url, $method, $context, $fields = null )
    {
        global $wp_filesystem;
        if ( false === ( $credentials = request_filesystem_credentials( $url, $method, false, $context, $fields ) ) ) {
            return false;
        }

        //check if credentials are correct or not.
        if ( !WP_Filesystem( $credentials ) ) {
            request_filesystem_credentials( $url, $method, true, $context );
            return false;
        }
        return true;
    }

    function populatingData()
    {
        global $wp_filesystem;
        $url = wp_nonce_url( "options-general.php?page=rewrite-link-manager", "filesystem-nonce" );
        $form_fields = [];

        if ( connect_fs( $url, "", WP_PLUGIN_DIR . "/filesystem/filesystem-demo", $form_fields )) {
            $dir = $wp_filesystem->find_folder(WP_PLUGIN_DIR . "/filesystem/filesystem-demo");
            $file = trailingslashit( $dir ) . "demo.txt";
            $wp_filesystem->put_contents( $file, "", FS_CHMOD_FILE);

            return "";
        } else {
            return new WP_Error( "filesystem_error", "Cannot initialize filesystem" );
        }
    }



    function pluginActionLink( $links, $file )
    {
        $this_plugin = basename( REWRITE_LINK_MANAGER_BASE ) . '/plugin.php';
        if ( $file == $this_plugin ) {
            $links[] = '<a href="' . admin_url( 'options-general.php?page=rewrite-link-manager' ) . '">' . __( 'Configure' , 'rewrite-link-manager' ) . '</a>';
        }
        return $links;
    }

    function initActiveLinkPage ()
    {
        $this->activePageLink(  );
    }

    /**
     * Some hackery to have WordPress match postname to any of our public post types
     * All of our public post types can have /post-name/ as the slug, so they better be unique across all posts
     * Typically core only accounts for posts and pages where the slug is /post-name/
     */
    function preGetPosts( $query )
    {
        $st_settings = get_option( 'ts_settings' );
        $postype_removes = [];
        if ( $st_settings ) {
            if( !empty( $st_settings['posttype_remove'] )) {
                $postype_removes = $st_settings['posttype_remove'];
            }
        }
        if ( count( $postype_removes ) ) {
            // Only noop the main query

            if ( ! $query->is_main_query() )
                return;
            // Only noop our very specific rewrite rule match
            if (! isset( $query->query['page'] ) ) {
                return;
            }

            // 'name' will be set if post permalinks are just post_name, otherwise the page rule will match
            if ( ! empty( $query->query_vars['name'] ) ) {
                if( $this->settings['enable_link_extension'] ) {
                    $dot = ltrim( $this->settings['link_dot'], '.' );
                    $query->query_vars['name'] = preg_replace( '/\.' . $dot . '/', '', $query->query_vars['name'] );
                }
                $query->set( 'post_type', $postype_removes );
            }
        }

    }

    function parseRequest( $wp )
    {
        if ( isset( $wp->request ) && !is_admin() ) {
            $requests = explode( '/', $wp->request );
            $request_amount = count( $requests );
            $post_name = array_shift( $requests );
            // if an attachment has been queried under this or another post type, skip checking
            if ( $request_amount > 1 && !in_array( $requests['0'], array('feed', 'page') ) )
                return;
            $post_name = rtrim( $post_name, '.html' );
            global $wpdb;
            $post = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $wpdb->posts WHERE post_type=%s AND post_name =%s",
                'attachment',
                $post_name
            ));
            if ( $post && isset($post->ID) ) {
                $wp->set_query_var( 'name', $post->post_name );
                $wp->set_query_var( 'pagename', false );
                return;
            }
        }
    }

    /**
     * Remove the slug from published post permalinks.
     */
    function postTypeLink( $post_link, $post, $leavename )
    {
        $postype_removes = [];

        if ( $this->settings ) {
            if ( !empty( $this->settings['posttype_remove'] ) ) {
                $postype_removes = $this->settings['posttype_remove'];
            }
        }

        if ( count( $postype_removes ) ) {
            if ( 'publish' != $post->post_status ) {
                return $post_link;
            }
            in_array( $post->post_type, $postype_removes )
            and $post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );

            // Check dot link and replace
            if ( $this->settings['enable_link_extension'] ) {
                $dot = ltrim( $this->settings['link_dot'], '.' );
                $post_link = rtrim( $post_link, '/' ) . '.' . $dot;
            }


        }
        return $post_link;
    }


    function termLink( $link, $term, $taxonomy )
    {
        if ( $this->checkTaxonomyValid( $taxonomy ) ) {
            $link = rtrim( $link, '/' );
            $prevent_duplicate = 0;
            if( !empty( $this->settings['prevent_duplicate'] ) ) {
                $prevent_duplicate = intval( $this->settings['prevent_duplicate'] );
            }
            if( $prevent_duplicate )
                $link .= '-' . substr( md5( $term->term_id), 0, 5 );
            return str_replace( $taxonomy . '/', '', $link );
        }
        return $link;

    }


    function createdTerm( $term_id, $tt_id, $taxonomy )
    {
        // Check multiple language
        if( $this->checkTaxonomyValid( $taxonomy ) ) {
            // Remove action edit term
            remove_action( 'created_term', [ $this, 'createdTerm'], 10, 3 );
            $term = get_term( $term_id );
            $prevent_duplicate = 0;
            if ( !empty( $this->settings['prevent_duplicate'] ) ) {
                $prevent_duplicate = intval( $this->settings['prevent_duplicate'] );
            }
            $term_hash = substr( md5( $term->term_id ), 0, 5 );
            $term_extend = '';
            if( $prevent_duplicate ) {
                $term_extend = "-{$term_hash}";

            }
            $this->rewriteManager->addRewrite( "^{$term->slug}{$term_extend}/?$", "index.php?{$taxonomy}={$term->slug}" );
            $this->rewriteManager->addRewrite( "^{$term->slug}{$term_extend}/page/([^/]*)$", "index.php?{$taxonomy}={$term->slug}&paged=\$matches[1]" );
            $this->rewriteManager->saveDatabase();
        }
    }

    public function editTerms( $term_id, $taxonomy )
    {
        if ( $this->checkTaxonomyValid( $taxonomy ) ) {
            // Remove action edit term
            remove_action( 'edit_terms', [ $this, 'editTerms'], 999, 2 );
            $term = get_term( $term_id );
            $old_slug = $term->slug;
            $new_slug = sanitize_title( $_POST['slug'] );
            if( empty( $_POST['slug'] ) ) {
               $new_slug = sanitize_title( $_POST['name'] );
            }

            $prevent_duplicate = 0;
            if ( !empty( $this->settings['prevent_duplicate'] ) ) {
                $prevent_duplicate = intval( $this->settings['prevent_duplicate'] );
            }

            $term_hash = substr( md5( $term->term_id), 0, 5 );
            $term_extend = '';
            if ( $prevent_duplicate ) {
                $term_extend = "-{$term_hash}";
            }

            // Remove rewrite old
            $this->rewriteManager->removeRewrite( "^{$old_slug}{$term_extend}/?$" );
            $this->rewriteManager->removeRewrite( "^{$old_slug}{$term_extend}/page/([^/]*)$" );

            // Add rewrite new
            $this->rewriteManager->addRewrite( "^{$new_slug}{$term_extend}/?$", "index.php?{$taxonomy}={$new_slug}" );
            $this->rewriteManager->addRewrite( "^{$new_slug}{$term_extend}/page/([^/]*)$", "index.php?{$taxonomy}={$new_slug}&paged=\$matches[1]" );

            $this->rewriteManager->saveDatabase();

        }
    }


    function preDeleteTerm( $term_id, $taxonomy )
    {
        // Check multiple language
        if ( $this->checkTaxonomyValid( $taxonomy ) ) {
            $term = get_term( $term_id );
            $prevent_duplicate = 0;
            if ( !empty( $this->settings['prevent_duplicate'] ) ) {
                $prevent_duplicate = intval( $this->settings['prevent_duplicate'] );
            }

            $term_hash = substr( md5( $term->term_id), 0, 5 );
            $term_extend = '';
            if( $prevent_duplicate ) {
                $term_extend = "-{$term_hash}";
            }
            $this->rewriteManager->removeRewrite( "^{$term->slug}{$term_extend}/?$" );
            $this->rewriteManager->removeRewrite( "^{$term->slug}{$term_extend}/page/([^/]*)$" );
            $this->rewriteManager->saveDatabase();
        }
    }

    function  checkTaxonomyValid( $taxonomy )
    {
        $taxonomies = [];
        $st_settings = get_option( 'ts_settings' );
        if ( $st_settings ) {
            $taxonomies = $st_settings['taxonomy_remove'];
            $taxonomies = array_values( $taxonomies );
        }
        if ( count( $taxonomies ) && in_array( $taxonomy, $taxonomies) ) {
            return true;
        }
        return false;
    }
    // Rewrite url
    function addCustomRewrite()
    {
        $this->rewriteManager->generateRewriteRoute();

    }

    function pluginsLoaded() {
        $this->settings = get_option( 'ts_settings' );
        // Load resources
        add_action( 'admin_enqueue_scripts', [ $this, 'adminEnqueueScripts' ] );
        $this->pluginLocalization();
    }

    // Process ajax response
    function ajaxResponses()
    {
        if( $this->checkVerify() ) {
            $action = esc_attr( $_POST['action'] );
            $dataReturn = [
                'status' => 'failed',
                'msg' => ''
            ];
            switch( $action ) {
                case 'ts_save_taxonomy':
                    $this->saveTaxonomy();
                    break;
                case 'ts_save_posttype':
                    $this->savePostType();
                    break;
                case 'ts_save_link_extension':
                    $this->saveLinkExtension();
                    break;
            }
        }

    }

    function saveLinkExtension()
    {
        $this->settings['link_extension'] = $_POST['extension_link'];
        $this->settings['link_dot'] = $_POST['link_dot'];
        $this->settings['enable_link_extension'] = 0;
        // flush_rules
        if ( !empty( $_POST['enable_link_extension'] ) ) {
            $this->settings['enable_link_extension'] = 1;
            $this->activePageLink( true );
        } else {
            $this->deactivePageLink( true );
        }

        $this->saveSettings();
        $this->data_return['status'] = 'ok';
        $this->data_return['msg'] = __('Save link extension successfull', 'rewrite-link-manager');
        $this->responseJson( );
    }

    function saveTaxonomy()
    {
        $this->settings['taxonomy_remove'] = $_POST['taxonomy_remove'];
        $this->settings['prevent_duplicate'] = 0;
        if ( !empty( $_POST['prevent_duplicate'] ) ) {
            $this->settings['prevent_duplicate'] = 1;
        }
        $this->saveSettings();
        $this->generateRewriteTermUrl();
        $this->data_return['status'] = 'ok';
        $this->data_return['msg'] = __('Save taxonomy remove slug successfull', 'rewrite-link-manager');
        $this->responseJson( );
    }

    function savePostType()
    {
        $this->settings['posttype_remove'] = $_POST['posttype_remove'];
        $this->saveSettings();
        $this->data_return['status'] = 'ok';
        $this->data_return['msg'] = __('Save post type remove slug successfull', 'rewrite-link-manager');
        $this->responseJson( );
    }

    function generateRewriteTermUrl( $dot = '' )
    {
        $taxonomies = $this->settings['taxonomy_remove'];
        $prevent_duplicate = 0;
        if ( !empty( $this->settings['prevent_duplicate'] ) ) {
            $prevent_duplicate = intval( $this->settings['prevent_duplicate'] );
        }
        $content_rewrite =  '';
        if ( count( $taxonomies ) ) {
            $this->rewriteManager->resetRewrite();
            foreach( $taxonomies as $taxonomy ) {
                $terms = get_terms( $taxonomy, [ 'hide_empty' => false ] );
                if ( $terms ) {

                    if ( $prevent_duplicate ) {
                        foreach ( $terms as $term ) {
                            $term_hash = substr( md5( $term->term_id ), 0, 5);
                            $term_slug = sanitize_title( $term->name );
                            $term_slug_after = str_replace( '-' . $term_hash, '',  $term_slug ) . '-' .$term_hash;
                            wp_update_term( $term->term_id, $taxonomy, [ 'slug' => $term_slug ] );
                            $this->rewriteManager->addRewrite( "^{$term_slug_after}{$dot}/?$", "index.php?{$taxonomy}={$term_slug}" );
                            $this->rewriteManager->addRewrite( "^{$term_slug_after}{$dot}/page/([^/]*)$", "index.php?{$taxonomy}={$term_slug}&paged=\$matches[1]" );
                        }
                    } else {
                        foreach ( $terms as $term ) {
                            $term_slug = sanitize_title( $term->name );
                            wp_update_term( $term->term_id, $taxonomy, [ 'slug' => $term_slug ]);
                            $this->rewriteManager->addRewrite( "^{$term_slug}{$dot}/?$", "index.php?{$taxonomy}={$term_slug}" );
                            $this->rewriteManager->addRewrite( "^{$term_slug}{$dot}/page/([^/]*)$", "index.php?{$taxonomy}={$term_slug}&paged=\$matches[1]" );
                        }
                    }
                }
            }
            $this->rewriteManager->saveDatabase();
        }
    }

    function responseJson( )
    {
        exit( json_encode( $this->data_return ) );
    }

    function checkVerify()
    {
        if( $_POST && !empty( $_POST['_wpnonce']) ) {
            if ( wp_verify_nonce( esc_attr($_POST['_wpnonce']), 'icl_taxonomy_slug_save') ) {
                return true;
            }
        }
        return false;
    }

    function scriptAndStyles()
    {

    }

    function adminInit()
    {
        add_action( 'admin_head', [ $this, 'loadJsHead' ]);
    }

    function loadJsHead() {?>
        <script type="text/javascript">
            jQuery( document).ready( function() {
                var dataPost = {};
                var response1 = jQuery( '#icl_ajx_response1'),
                    response2 = jQuery( '#icl_ajx_response2'),
                    responsept = jQuery( '#icl_ajx_response_pt'),
                    responselx = jQuery( '#icl_ajx_response_lx'),
                    loading1 = jQuery( '#alp_ajx_ldr_1'),
                    loading2 = jQuery( '#alp_ajx_ldr_2');
                    loadingpt = jQuery( '#alp_ajx_ldr_pt');
                    loadinglx = jQuery( '#alp_ajx_ldr_lx');
                jQuery( document).on( 'click', '#ts_save_taxonomy', function( event ) {
                    event.preventDefault();
                    response1.text( '' );
                    loading1.fadeIn();
                    dataPost = jQuery( '#icl_save_taxonomy_options').serializeObject();
                    dataPost.action = 'ts_save_taxonomy';
                    jQuery.ajax({
                        type: "POST",
                        url: "<?php echo htmlentities( $_SERVER['REQUEST_URI'] ); ?>",
                        data: dataPost,
                        dataType: 'json',
                        success: function( data ) {
                            loading1.fadeOut();
                            if( data.status == 'ok') {
                                response1.css( 'display', 'block' );
                                response1.text( data.msg );
                                setTimeout( function() { location.reload(); }, 1000 );
                            }
                        },
                        error: function( xhr, statusText, errorThrow ) {
                            loading1.fadeOut();
                        }
                    });
                });
                jQuery( document).on( 'click', '#ts_save_rewrite_code', function( event ) {
                    event.preventDefault();
                    dataPost = jQuery( '#icl_save_taxonomy_slug_rewrite').serializeObject();
                    dataPost.action = 'ts_save_manual_rewrite';
                    loading2.fadeIn();
                    jQuery.ajax( {
                        type: "POST",
                        url: "<?php echo htmlentities($_SERVER['REQUEST_URI']); ?>",
                        data: dataPost,
                        dataType: 'json',
                        success: function( data ) {
                            loading2.fadeOut();
                            if( data.status == 'ok') {
                                response2.css( 'display', 'block' );
                                response2.text( data.msg );
                                setTimeout( function() { response2.hide('slow'); }, 2000 );
                            }
                        },
                        error: function( xhr, statusText, errorThrow ) {
                            loading2.fadeOut();
                        }
                    } );
                });

                jQuery( document).on( 'click', '#ts_save_posttype', function( event ) {
                    event.preventDefault();
                    responsept.text( '' );
                    loadingpt.fadeIn();
                    dataPost = jQuery( '#icl_save_posttype_options').serializeObject();
                    dataPost.action = 'ts_save_posttype';
                    jQuery.ajax({
                        type: "POST",
                        url: "<?php echo htmlentities($_SERVER['REQUEST_URI']); ?>",
                        data: dataPost,
                        dataType: 'json',
                        success: function( data ) {
                            loading1.fadeOut();
                            if( data.status == 'ok') {
                                responsept.css( 'display', 'block' );
                                responsept.text( data.msg );
                                setTimeout( function() { location.reload(); }, 1000 );
                            }
                        },
                        error: function( xhr, statusText, errorThrow ) {
                            loading1.fadeOut();
                        }
                    });
                });
                jQuery( document).on( 'click', '#ts_save_link_extension', function( event ) {
                    event.preventDefault();
                    responselx.text( '' );
                    loadinglx.fadeIn();
                    dataPost = jQuery( '#icl_save_extension_link').serializeObject();
                    dataPost.action = 'ts_save_link_extension';
                    jQuery.ajax({
                        type: "POST",
                        url: "<?php echo htmlentities($_SERVER['REQUEST_URI']); ?>",
                        data: dataPost,
                        dataType: 'json',
                        success: function( data ) {
                            loading1.fadeOut();
                            if( data.status == 'ok') {
                                responselx.css( 'display', 'block' );
                                responselx.text( data.msg );
                                setTimeout( function() { location.reload(); }, 1000 );
                            }
                        },
                        error: function( xhr, statusText, errorThrow ) {
                            loading1.fadeOut();
                        }
                    });
                });
            });

        </script>
    <?php
    }

    function adminEnqueueScripts()
    {
        wp_enqueue_script( 'jquery-serialize-object', REWRITE_LINK_MANAGER_PATH . '/res/js/jquery.serialize-object.js', [ ], '1.0.1' );
    }

    function _no_wpml_warning()
    {

    }

    function menu()
    {
        add_submenu_page(
            'options-general.php',
            __('Rewrite link manager','rewrite-link-manager'),
            __('Rewrite link manager','rewrite-link-manager'),
            'activate_plugins',
            'rewrite-link-manager',
            [ $this, 'menuContent' ]);
    }

    function menuContent()
    {
        include REWRITE_LINK_MANAGER_BASE . '/view/management.php';

    }

    function saveSettings()
    {
        update_option( 'ts_settings', $this->settings );
    }

    function removeSettings()
    {
        delete_option( 'ts_settings' );
    }

    function saveForm()
    {
        global $wpdb;
        return true;
    }

    function removeDir( $dir )
    {
        if ( is_dir( $dir ) ) {
            $objects = scandir( $dir );
            foreach ( $objects as $object ) {
                if ($object != "." && $object != "..") {
                    if ( is_dir( $dir . "/" . $object ) )
                        $this->removeDir( $dir . "/" . $object );
                    else
                        unlink( $dir . "/" . $object );
                }
            }
            rmdir( $dir );
        }
    }

    function activePageLink( $save = false )
    {
        if ( $data = $this->checkLinkValid( $save ) ) {
            global $wp_rewrite;
            $dot = '.' . ltrim( $data->dot_link, '.' );
            // Replace permalink with page
            if ( !strpos( $wp_rewrite->get_page_permastruct(), $dot ) ) {
                $wp_rewrite->page_structure = $wp_rewrite->page_structure . $dot;
            }
            $wp_rewrite->flush_rules();
        }
    }

    function deactivePageLink( $save = false ) {
        if ( $data = $this->checkLinkValid( $save ) )
        {
            global $wp_rewrite;
            $dot = '.' . ltrim(  $data->dot_link, '.' );
            $wp_rewrite->page_structure = str_replace( $dot ,"", $wp_rewrite->page_structure );
            $wp_rewrite->flush_rules();
        }
    }

    function  checkLinkValid( $save = false, $posttype = 'page' )
    {
        $st_settings = get_option( 'ts_settings' );
        $link_extension = [];
        if ( $save ) {
            $dataReturn = new \stdClass();
            $dataReturn->dot_link = $st_settings['link_dot'];
            $dataReturn->link_extension = $st_settings['link_extension'];
            return $dataReturn;
        }

        if ( $st_settings ) {
            if ( !empty( $st_settings['enable_link_extension'] )
                && intval( $st_settings['enable_link_extension'] ) == 1
                && !empty( $st_settings['link_dot'] )
            ) {
                if ( !empty( $st_settings['link_extension'] )
                    && in_array( $posttype, $st_settings['link_extension'] )
                ) {
                    $dataReturn = new \stdClass();
                    $dataReturn->dot_link = $st_settings['link_dot'];
                    $dataReturn->link_extension = $st_settings['link_extension'];
                    return $dataReturn;
                }
            }
        }
        return false;
    }

    function pluginActivate()
    {
        
    }

    function pluginDeactivate()
    {
        $this->deactivePageLink();
        $this->removeSettings();
    }

    // Localization
    function pluginLocalization()
    {
        load_plugin_textdomain( 'rewrite-link-manager', false, basename( REWRITE_LINK_MANAGER_BASE ) . '/locale' );
    }
}


/**
 * Returns the main instance of TPRS to prevent the need to use globals.
 *
 * @since  2.1
 * @return RewriteLinkManager
 */
function rewritelinkManager() {
    return Factory::instance( false );
}

// Global for backwards compatibility.
$GLOBALS['RLM'] = rewritelinkManager();