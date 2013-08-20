<?php
/*
 * (c) Christian Gärtner <christiangaertner.film@googlemail.com>
 * This file is part of the Modulework Framework Tests
 * License: View distributed LICENSE file
 *
 * 
 * This file is meant to be used in PHPUnit Tests
 */

use Modulework\Modules\Container\Container;

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
}


class ConcreteStub { }