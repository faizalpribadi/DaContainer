# DaContainer


A simpe IoC container for PHP.

DaContainer is a powerful inversion of control container for managing class dependencies.

## Installation

Simply add ```dagardner/dacontainer``` to your composer dependencies and run ```composer install```.

The last step is to include composer' s autoload file (like this: ```require './vendor/autoload.php'```.

And thats it. In the next chapter we we' ll cover basic usage.

## Basic Usage

**DaContainer** can resolve both via Closure and direct resolution. Let' s start with Closures:

### Binding...

    $container = new \DaGardner\DaContainer\Container;
    $container->bind('foo', function() {
      return new stdClass;
    });

### Resolving

    $container->resolve('foo');

This will call the closure callback and therefor returns a new instance of a ```stdClass```.

### Singeltons

Even if this pattern is often consired to be an anti-pattern, I implemented it, just in case somebody craves for it...

    $container->singelton('foo', function() {
      return new stdClass;
    });

This closure is only called *once* during execution. The returned instance is stored and second resolve call will return the stored instance:

    ($container->resolve('foo') === $container->resolve('foo));

This statement will be true.

You can also store existing objects into the container:

    $object = new stdClass;
    $container->instance('foo', $object);


### Automatic Injection

The IoC DaContainer can also auto-inject dependencies of a class into it' s constrcutor and injector methods.

    class Foo
    {
      public function __construct(stdClass $class) {}
      public function setLogger(LoggerInterface $logger) {}
    }

With some other containers this wouldn' t be possible. The DaContainer will analyze the class with Reflections and auto-inject classes.

    $container->resolve('Foo');

This will just return a new instance of ```Foo``` with a ```stdClass``` injected into the constructor.
But how do we enable the injector method detection?

    $container->enableInjecterDetection();
    $container->resolve('Foo');

But this won' t work right now and throws a ```\DaGardner\DaContainer\Exceptions\ResolveException``` exception. Why?

The container doesn' t know what to do with this ```LoggerInterface```. Since the container doesn' t know which implementation to use you have to tell it the container!

    $container->bind('LoggerInterface', 'BasicLogger');

If a class requires an implementation of the ```LoggerInterface``` the container knows which implementation to use!

### Events

The IoC Container has it' s own simple event system, which can be used standalone or getting hooked into the main event dispatcher!

    $container->onResolving(function($object) {
      // Do something
    });

**NOTE!** This event fires on the main resolve ONLY. That means that it doesn' t fire when the container is resolving dependencies.

There are two types listeners. Silent and active listeners.

#### Silent

Theses listeners are getting the resolved object handed over and are then ignored by the container. Manipulations on the object are not applied to the resolved object.

##### Active

You can register a active listener the same way as a silten one, just pass ```true``` as the second argument to the ```onResolving()``` method.

Theses listeners are getting the resolved object handed over and the container replaces the return of the listener with the object.

Active listerners should **always** return the same (and perhaps modified) object, otherwise you won' t be able to use the container anymore:

    /**
     * Correct usage!
     */
    $container->onResolving(function($object) {
      // Do something
      return $object;
    }, true);


    /**
     * WRONG usage!
     */
    $container->onResolving(function($object) {
      // Do something
      return null;
    }, true);
