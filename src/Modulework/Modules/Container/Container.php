<?php namespace Modulework\Modules\Http;
/*
 * (c) Christian Gärtner <christiangaertner.film@googlemail.com>
 * This file is part of the Modulework Framework
 * License: View distributed LICENSE file
 */

use ArrayAccess;

/**
* A simple IoC Container
*/
class Container implements ArrayAccess
{
    /**
     * The bindings
     * @var array
     */
    protected $binds = array();

    /**
     * The singletons
     * @var array
     */
    protected $singletons = array();

    /**
     * Registered resolver callbacks
     * @var array
     */
    protected $callbacks = array();

    /**
     * Register a binding
     * @param  string               $id        The id (needed for resolving)
     * @param  Closure|string|null  $concrete  The factory
     * @param  boolean              $singleton Whether the binding should be a singelton
     */
    public function bind($id, $concrete, $singleton = false)
    {
        $this->binds[$id] = $concrete;
    }

    public function resolve($id, array $parameters = array())
    {
        return $this->binds[$id];
    }
}