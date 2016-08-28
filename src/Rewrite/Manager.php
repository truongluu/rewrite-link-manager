<?php
/**
 * Created by PhpStorm.
 * User: xuantruong
 * Date: 8/8/16
 * Time: 11:43 AM
 */
namespace RewriteLinkManager\Rewrite;

use RewriteLinkManager\Factory;
use RewriteLinkManager\Rewrite\RewriteRow;

class Manager implements \ArrayAccess, \Countable{

    public static $instance = null;
    private $rewriteMap = [];

    public function __construct()
    {
        if( ( $rewrite = get_option( Factory::OPTION_NAME ) ) ) {
            $this->rewriteMap = $rewrite;
        }
    }

    public static function  getInstance()
    {
        if( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public  function resetRewrite()
    {
        $this->rewriteMap = [];
    }

    public function addRewrite( $regex, $query, $position  = 'top' )
    {
        $this->rewriteMap[$regex] = new RewriteRow( $regex, $query, $position );
    }

    public  function removeRewrite( $regex )
    {
        $this->offsetUnset( $regex );
    }

    public function saveDatabase()
    {
        add_option( Factory::OPTION_NAME, $this->rewriteMap ) OR update_option( Factory::OPTION_NAME, $this->rewriteMap );
    }

    public  function generateRewriteRoute()
    {
        global $wp_rewrite;
        if( !$wp_rewrite->using_permalinks() ) {
            return;
        }
        foreach ( $this->rewriteMap as $rewriteItem ) {
            add_rewrite_rule( $rewriteItem->regex, $rewriteItem->query, $rewriteItem->after );
        }
        flush_rewrite_rules();

    }

    public  function displayOut( $return = false )
    {
        global $wp_rewrite;
        if( !$wp_rewrite->using_permalinks() ) {
            return;
        }
        if( $return ) {
            ob_start();
        }
        foreach ( $this->rewriteMap as $key => $rewriteItem ) {
            printf( "\nadd_rewrite_rule( '%s', '%s', '%s' );", $rewriteItem->regex, $rewriteItem->query, $rewriteItem->after );
        }
        if( $return ) {
            return ob_get_clean();
        }
    }


    public function offsetExists($offset)
    {
        return isset( $this->rewriteMap[$offset] );
    }

    public function offsetGet($offset)
    {
        return $this->rewriteMap[$offset]?: null;
    }

    public function offsetSet($offset, $value)
    {
        $this->rewriteMap[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        if( isset( $this->rewriteMap[$offset]) ) {
            unset( $this->rewriteMap[$offset] );
        }
    }

    public  function  count()
    {
        return count( $this->rewriteMap );
    }
}
