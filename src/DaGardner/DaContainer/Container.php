<?php namespace DaGardner\DaContainer;
/*
 *  (c) Christian Gärtner <christiangaertner.film@googlemail.com>
 * This file is part of the DaContainer package
 * License: View distributed LICENSE file
 */

use Closure;
use ArrayAccess;
use ReflectionClass;
use DaGardner\DaContainer\Exceptions\ResolveException;
use DaGardner\DaContainer\Exceptions\ParameterResolveException;

/**
* DaContainer main class.
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

        if (is_null($concrete)) {
            
            $concrete = $id;

        }

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
    public function singleton($id, $concrete = null)
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

            return $this->singletons[$id];

        }

        $concrete = $this->getConcrete($id);

        if ($this->isInstantiable($id, $concrete)) {
            
            $object = $this->build($concrete, $parameters);

        } else {

            $object = $this->resolve($concrete, $parameters);

        }

        if ($this->isSingelton($id)) {
            
            $this->singletons[$id] = $object;

        }

        return $object;
    }

    /**
     * Instantiate a concrete
     * @param  string|Closure       $concrete   The concrete
     * @param  array                $parameters Parameters are getting passed to the factory
     * @return mixed                            The new instance
     */
    public function build($concrete, array $parameters = array())
    {
        if ($concrete instanceof Closure) {

            return $concrete($this, $parameters);

        }

        $resolver = new ReflectionClass($concrete);

        if (!$resolver->isInstantiable()) {
            
            throw new ResolveException('Target <' . $concrete . '> is not instantiable.');
            
        }

        $constructor = $resolver->getConstructor();

        // If there is no constructor we can just return a new one
        // (otherwise are parameters required)
        if (is_null($constructor)) {
            
            return new $concrete;

        }

        $dependencies = $this->getDependencies($constructor->getParameters());

        return $resolver->newInstanceArgs($dependencies);
    }

    /**
     * Returns the concrete of the given id
     * @param  string $id The id
     * @return mixed      The concrete
     */
    protected function getConcrete($id)
    {
        if (!isset($this->binds[$id])) {
            
            return $id;

        } else {

            return $this->binds[$id]['concrete'];

        }
    }

    /**
     * Checks if a concrete can get instantiated
     * @param  string  $id       The id of the concrete
     * @param  mixed   $concrete The concrete
     * @return boolean           Whether the conrete is instantiable
     */
    protected function isInstantiable($id, $concrete)
    {
        return ($concrete === $id || $concrete instanceof Closure);
    }

    /**
     * Checks whether the binding is a singelton
     * @param  string  $id The id
     * @return boolean     Whether the binding is a singelton
     */
    protected function isSingelton($id)
    {
        return (isset($this->binds[$id]['singleton']) && $this->binds[$id]['singleton'] === true);
    }

    /**
     * Resolve all dependencies of the reflection parameters
     * @param  array $parameters The parameters
     * @return array             The resolved dependencies
     */
    protected function getDependencies($parameters)
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {
            
            $dependency = $parameter->getClass();

            if (is_null($dependency)) {
                // It 's a string or the like
                $dependencies[] = $this->resolveArgument($parameter);

            } else {

                $dependencies[] = $this->resolveClass($parameter);

            }
        }

        return (array) $dependencies;
    }

    /**
     * Resolve a class.
     * @param  \ReflectionParameter $parameter The parameter
     * @return mixed                           The resolved class
     *
     * @throws \Modulework\Modules\Container\Exceptions\ResolveException If the class cannot get resolved.
     */
    protected function resolveClass($parameter)
    {
        try {
            
            return $this->resolve($parameter->getClass()->name);

        } catch (ResolveException $e) {
            
            if ($parameter->isOptional()) {
                // Just pass the default
                return $parameter->getDefaultValue();

            } else {

                throw $e;

            }

        }
    }

    /**
     * Resolve a non-class argument
     * @param  \ReflectionParamter $parameter The parameter
     * @return mixed                          The resolved type
     *
     * @throws \Modulework\Modules\Container\Exceptions\ParameterResolveException If the parameter cannot get resolved.
     */
    protected function resolveArgument($parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            
            return $parameter->getDefaultValue();

        } else {
            // We cannot guess the value, can we!
            
            throw new ParameterResolveException('Unresolvable parameter <' . $parameter . '>');
            
        }
    }

    /**
     * ArrayAccess Implementation
     */
    
    public function offsetExists($key)
    {
        return isset($this->binds[$key]);
    }

    public function offsetGet($key)
    {
        return $this->resolve($key);
    }

    public function offsetSet($key, $value)
    {
        if (!$value instanceof Closure) {
            
            $value = function() use ($value)
            {
                return $value;
            };

            $this->bind($key, $value);
        }
    }

    public function offsetUnset($key)
    {
        unset($this->binds[$key]);
    }
}