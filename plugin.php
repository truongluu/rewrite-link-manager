<?php
/**
 * User: xuantruong
 * Date: 12/27/15
 * Time: 1:13 PM
 * Plugin Name: Rewrite link mangager
 * Description: Remove Taxonomy, post type link. Add extension after link (page, post_type )
 * Plugin URI: http://xuantruong.info/
 * Author: truong.luu
 * Author URI: http://xuantruong.info/
 * Version: 1.0.1
 * Plugin Slug: rewrite-link-manager
 * Text Domain: rewrite-link-manager
 *
 */
if( !defined( 'ABSPATH' ) )
    return;
define( 'REWRITE_LINK_MANAGER_VERSION', '1.0.1' );
define( 'REWRITE_LINK_MANAGER_BASE', dirname(__FILE__) );
define( 'REWRITE_LINK_MANAGER_PATH', plugin_dir_url(  __FILE__ ));

spl_autoload_register( 'rewriteLinkAutoload' );
function rewriteLinkAutoload( $className )
{
    $prefix = 'RewriteLinkManager\\';
    $len = strlen( $prefix );
    $fileName = '';
    $namespace = '';
    $includePath = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'src';
    if( 0 !== strncmp( $prefix, $className, $len ) ) {
        // no, move to the next registered autoloader
        return;
    }
    $className = substr( $className, $len );
    $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $className );
    $fullFileName = $includePath . DIRECTORY_SEPARATOR . $fileName . '.php';
    if ( file_exists( $fullFileName ) ) {
        require_once $fullFileName;
    } else {
        echo 'Class "' . $className . '" does not exist.';
    }
}
$rewriteLinkManager = new RewriteLinkManager\Factory();
register_activation_hook( REWRITE_LINK_MANAGER_BASE . '/plugin.php', array( $rewriteLinkManager, 'pluginActivate') );
register_deactivation_hook(  REWRITE_LINK_MANAGER_BASE . '/plugin.php', array( $rewriteLinkManager, 'pluginDeactivate') );
