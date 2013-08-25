<?php
/*
 * (c) Christian Gärtner <christiangaertner.film@googlemail.com>
 * This file is part of the Modulework Framework Tests
 * License: View distributed LICENSE file
 *
 * 
 * This file is meant to be used in PHPUnit Tests
 */

use DaGardner\DaContainer\Container;

/**
* PHPUnit Test
*/
class ContainerTest extends PHPUnit_Framework_TestCase
{
    public function testClosureBinding()
    {
        $con = new Container;
        $con->bind('foo', function () {
            return 'Bar';
        });

        $this->assertEquals('Bar', $con->resolve('foo'));
    }

    public function testSingletonClosureBinding()
    {
        $con = new Container;
        $obj = new StdClass;

        $con->singleton('foo', function () use ($obj) {
            return $obj;
        });

        $this->assertEquals($obj, $con->resolve('foo'));
    }

    public function testNonBindedConreteResolving()
    {
        $con = new Container;

        $this->assertInstanceOf('ConcreteStub', $con->resolve('ConcreteStub'));
    }

    public function testSingeltonConrecteResolving()
    {
        $con = new Container;
        $con->singleton('ConcreteStub');

        $this->assertEquals($con->resolve('ConcreteStub'), $con->resolve('ConcreteStub'));
    }

    public function testContainerGettingPassedToResolver()
    {
        $con = new Container;
        $con->bind('foo', function($container) {
            return $container;
        });

        $c = $con->resolve('foo');

        $this->assertEquals($c, $con);
    }

    public function testParamertersGettingPassedToResolver()
    {
        $con = new Container;
        $con->bind('foo', function ($c, $p) {
            return $p;
        });

        $params = array('foo', 'bar', 'baz');

        $this->assertEquals($params, $con->resolve('foo', $params));
    }

    public function testInstance()
    {
        $con = new Container;

        $obj = new StdClass;
        $obj->name = 'Foo';

        $con->instance('foo', $obj);

        $this->assertEquals($obj, $con->resolve('foo'));

    }

    public function testResolverCallbacks()
    {
        $con = new Container;

        $con->onResolving(function($object) {
            $object->bar = 'baz';
        });

        $con->bind('foo', function()
        {
            return new stdClass;
        });


        $this->assertEquals('baz', $con->resolve('foo')->bar);
    }

    public function testDependenyInjectionConstructor()
    {
        $con = new Container;
        $con->bind('ConcreteStubInterface', 'ConcreteStub');

        $this->isInstanceOf('ConcreteDependsOn', $con->resolve('ConcreteDependsOn'));

    }

    public function testDependenyInjectionMethodDetection()
    {
        $con = new Container;
        $con->enableInjecterDetection(array(
            'setString',
            '_CLASSES_' => array(
                'ConcreteInjectorMethods' => array(
                    'setArray'
                )
            )
        ));
        $obj = $con->resolve('ConcreteInjectorMethods');

        $this->assertTrue($obj->debug);
    }

    public function testDeepResolving()
    {
        $con = new Container;
        $con->bind('foo', 'bar');
        $con->bind('bar', function() {
            return 'baz';
        });

        $this->assertEquals('baz', $con->resolve('foo'));
    }

    /**
     * @expectedException \DaGardner\DaContainer\Exceptions\ResolveException
     */
    public function testRemove()
    {
        $con = new Container;
        $con->bind('foo', function() {
            return 'foo';
        });

        $con->remove('foo');

        $con->resolve('foo');
    }

    public function testIsBound()
    {
        $con = new Container;
        $con->bind('foo', function() {
            return 'foo';
        });

        $this->assertTrue($con->isBound('foo'));
    }

    public function testArrayAccess()
    {
        $con = new Container;

        $con['foo'] = function() {
            return 'bar';
        };

        $this->assertTrue(isset($con['foo']));

        $this->assertEquals('bar', $con['foo']);

        unset($con['foo']);

        $this->assertFalse($con->isBound('foo'));
    }

    /**
     * @expectedException ReflectionException
     */
    public function testExceptionOnBuild()
    {
        $con = new Container;
        $con->bind('foo', 'bar');
        $con->build('foo');
    }

    /**
     * @expectedException \DaGardner\DaContainer\Exceptions\ResolveException
     */
    public function testExceptionOnBuildCustom()
    {
        $con = new Container;
        $con->build('PrivateConcreteStub');
    }
}


class ConcreteStub implements ConcreteStubInterface {}

interface ConcreteStubInterface {}

class ConcreteDependsOn
{
    function __construct(ConcreteStubInterface $dep, $str = 'Foo') {}
}

class PrivateConcreteStub
{
    private function __construct() {}
}

class ConcreteInjectorMethods
{
    public $debug = false;
    function setStdClass(stdClass $class) {
        $this->debug = true;
    }

    public function setString($str = '')
    {
        $this->debug = false;
    }

    public function setArray(array $test)
    {
        $this->debug = false;
    }
}
