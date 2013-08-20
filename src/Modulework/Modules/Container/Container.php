<?php namespace Modulework\Modules\Http;
/*
 * (c) Christian Gärtner <christiangaertner.film@googlemail.com>
 * This file is part of the Modulework Framework
 * License: View distributed LICENSE file
 */

use Closure;
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
        if (!$concrete instanceof Closure) {
            
            // If the factory (resolver) is NOT a closure we assume,
            // that it is a classname and wrap it into a closure so it' s
            // easier when resolving.

            $concrete = function($container) use ($id, $concrete)
            {
                $method = ($id == $concrete) ? 'build' : 'resolve';
                
                $container->$method($concrete);
            };
        }


        $this->binds[$id] = compact('concrete', 'singleton');
    }

    /**
     * Register a singleton binding
     * @param  string               $id        The id (needed for resolving)
     * @param  Closure|string|null  $concrete  The factory
     *
     * @uses bind()
     */
    public function singleton($id, $concrete)
    {
        $this->bind($id, $concrete, true);
    }

    /**
     * Put an object (instance) into the singelton registry
     * @param  string $id       The id (needed for resolving)
     * @param  mixed  $instance The object (an instance)
     */
    public function instance($id, $instance)
    {
        $this->singletons[$id] = $instance;
    }

    /**
     * Resolve a binding
     * @param  string $id         The id (used while binding)
     * @param  array  $parameters Parameters are getting passed to the factory
     * @return mixed              The return value of the closure
     */
    public function resolve($id, array $parameters = array())
    {
        if (isset($this->singletons[$id])) {
            return $this->singletons[$id]
        }

        $concrete = $this->getConcrete($id);

        if ($this->isInstantiatable($id, $concrete)) {
            
            $object = $this->build($concrete, $parameters);

        } else {

            $object = $this->make($concrete, $parameters);

        }

        if ($this->isSingelton($id)) {
            
            $this->singletons[$id] = $object;

        }

        return $object;
    }
}