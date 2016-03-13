<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cases\Core;

use Spiral\Core\Container;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function testInterop()
    {
        $container = new Container();

        $this->assertFalse($container->has('abc'));

        $container->bind('abc', function () {
            return 'hello';
        });

        $this->assertTrue($container->has('abc'));
        $this->assertEquals('hello', $container->get('abc'));
    }

    public function testStringBinding()
    {
        $container = new Container();

        $this->assertFalse($container->has('abc'));

        $container->bind('abc', function () {
            return 'hello';
        });

        $container->bind('dce', 'abc');
        $this->assertTrue($container->has('dce'));
        $this->assertEquals('hello', $container->get('dce'));
    }
}