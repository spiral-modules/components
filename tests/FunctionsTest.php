<?php

/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests;

class FunctionsTest extends \PHPUnit_Framework_TestCase
{
    //Base test to verify function is working
    public function testInterpolate()
    {
        $result = \Spiral\interpolate("Hello {name}", ['name' => 'Anton']);
        $this->assertSame('Hello Anton', $result);
    }

    public function testInterpolateCustomBraces()
    {
        $result = \Spiral\interpolate("Hello [name]", ['name' => 'Anton'], '[', ']');
        $this->assertSame('Hello Anton', $result);
    }
}