<?php
/**
 * Created by PhpStorm.
 * User: xuantruong
 * Date: 8/8/16
 * Time: 11:50 AM
 */
namespace RewriteLinkManager\Rewrite;

class RewriteRow {

    private $regex;
    private $query;
    private $after;

    public function __construct( $regex, $query, $after = 'top' )
    {
        $this->regex = $regex;
        $this->query = $query;
        $this->after = $after;
    }


    public function __get( $name )
    {
        return property_exists( $this, $name ) ? $this->{$name} : null;
    }

    public function __set($name, $value)
    {
        if( property_exists( $this, $name) ) {
            $this->{$name} = $value;
        }
    }

}