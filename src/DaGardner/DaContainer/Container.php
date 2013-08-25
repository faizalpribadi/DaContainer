<?php namespace DaGardner\DaContainer;
/*
 *  (c) Christian Gärtner <christiangaertner.film@googlemail.com>
 * This file is part of the DaContainer package
 * License: View distributed LICENSE file
 */

use Closure;
use ArrayAccess;
use ReflectionClass;
use ReflectionException;
use DaGardner\DaContainer\Exceptions\ResolveException;
use DaGardner\DaContainer\Exceptions\ParameterResolveException;

/**
* DaContainer main class.
* A simple IoC Container
* @author Christian Gärtner <christiangaertner.film@googlemail.com>
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

                return $container->$method($concrete, array(), false);
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
     * Removes a binding 
     * @param  string $id The id (used while binding)
     */
    public function remove($id)
    {
        unset($this->binds[$id]);
    }

    /**
     * Resolve a binding
     * @param  string $id         The id (used while binding)
     * @param  array  $parameters Parameters are getting passed to the factory
     * @param  boolen $final      Whether this the final resolve of a class
     * @return mixed              The return value of the closure
     */
    public function resolve($id, array $parameters = array(), $final = true)
    {
        if (isset($this->singletons[$id])) {

            return $this->singletons[$id];

        }

        $concrete = $this->getConcrete($id);

        if ($this->isInstantiable($id, $concrete)) {

            $object = $this->build($concrete, $parameters, false);

        } else {

            $object = $this->resolve($concrete, $parameters, false);

        }

        if ($this->isSingelton($id)) {
            
            $this->singletons[$id] = $object;

        }

        if ($final) {
            $this->fireCallbacks($object);
        }        

        return $object;
    }

    /**
     * Instantiate a concrete
     * @param  string|Closure       $concrete   The concrete
     * @param  array                $parameters Parameters are getting passed to the factory
     * @param  boolen               $final      Whether this the final resolve of a class
     * @return mixed                            The new instance
     */
    public function build($concrete, array $parameters = array(), $final = true)
    {
        if ($concrete instanceof Closure) {

            return $concrete($this, $parameters);

        }
        try {

            $resolver = new ReflectionClass($concrete);

        } catch (ReflectionException $e) {

            throw new ResolveException('Target <' . $concrete . '> could not be found.');
            
        }
        

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
     * Determine if an ID is already bound
     * @param  string  $id The ID
     * @return boolean     Whether the ID is bound
     */
    public function isBound($id)
    {
        return isset($this->binds[$id]);
    }

    /**
     * Enable the powerful injector method detection.
     *
     * Example blacklist array:
     * [
     *     'setString',
     *     'setArray',
     *     '_CLASSES_' => [
     *         SomeClass' => [
     *         'setMailer'
     *         ]
     *     ]
     * 
     * ]
     *
     * Strings in the main array are consired to be global and are ignored everytime.
     * The class specific blacklist is only checked if the object is an instance of this class
     * 
     * @param  array  $blacklist A blacklist of method names
     */
    public function enableInjecterDetection(array $blacklist = array())
    {
        $this->onResolving(function($object) use ($blacklist)
        {
            $class = get_class($object);

            $reflection = new ReflectionClass($class);

            $methods = $reflection->getMethods();

            /**
             * Cycle thru all methods. Filtering in the next control-construct
             */
            foreach ($methods as $method) {

                /**
                 * This is not a complex detection, but most injecter methods are starting with a set[...]
                 */
                if (strpos($method->name, 'set') === 0) {
                    
                    // Just check if the method is in the blacklist
                    if (in_array($method->name, $blacklist) || (isset($blacklist['_CLASSES_'][$class]) && in_array($method->name, $blacklist['_CLASSES_'][$class]))) {
                        continue;
                    }

                    try {
                        
                        $dependencies = $this->getDependencies($method->getParameters());

                        /**
                         * We keep this line in the try/catch block as well in order to skip it if an exception is thrown,
                         * otherwise we would get native PHP errors.. Nasty.
                         */
                        call_user_func_array(array($object, $method->name), $dependencies);

                    /**
                     * If an ParameterResolveException is thrown it hit a non class injector method and we simply ignore these
                     * We do NOT catch ResolveExceptions since a not found class is something we' re not responsible for.
                     */
                    } catch (ParameterResolveException $e) {
                    }
                }
            }
            
            return $object;

        });
    }

    /**
     * Register a listener for the resolving event.
     * This is only fired on the main resolve, not internal dependency resolves.
     * @param  Closure $callback The listener
     */
    public function onResolving(Closure $callback)
    {            
        $this->callbacks[] = $callback;
    }

    /**
     * Returns the concrete of the given id
     * @param  string $id The id
     * @return mixed      The concrete
     */
    protected function getConcrete($id)
    {
        if (!isset($this->binds[$id])) {

            if (class_exists($id)) {

                return $id;

            }
            
            throw new ResolveException('ID is not bound and not a class');
            

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
     * @param  array $parameters    The parameters
     * @return array                The resolved dependencies
     */
    protected function getDependencies($parameters)
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {

            try {

                $dependency = $parameter->getClass();

            } catch (ReflectionException $e) {
                
                throw new ResolveException('Target <' . $parameter . '> could not be found.');

            }

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

            return $this->resolve($parameter->getClass()->name, array(), false);

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

    protected function fireCallbacks($object)
    {
        foreach ($this->callbacks as $callback) {
            
            $callback($object);

        }
    }

    /**
     * ArrayAccess Implementation
     */
    
    /**
     * ArrayAccess
     * @param  string $id  The id used on bind()
     * @return boolean     Whether the id is bound
     *
     * @uses isBound()
     */
    public function offsetExists($id)
    {
        return $this->isBound($id);
    }

    /**
     * Resolve a binding
     * @param  string $id The id (used while binding)
     * @return mixed      The return value of the closure
     *
     * @uses resolve()
     */
    public function offsetGet($id)
    {
        return $this->resolve($id);
    }

    /**
     * Register a binding
     * @param  string               $id        The id (needed for resolving)
     * @param  Closure|string|null  $value     The factory
     *
     * @uses bind()
     */
    public function offsetSet($id, $value)
    {
        $this->bind($id, $value);
    }

    /**
     * Removes a binding
     * @param  string $if The id (used while binding)
     *
     * @uses remove()
     */
    public function offsetUnset($id)
    {
        $this->remove($id);
    }
}