<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Core;

use Spiral\Core\Exceptions\Container\ArgumentException;
use Spiral\Core\Exceptions\Container\AutowireException;
use Spiral\Core\Exceptions\Container\ContainerException;
use Spiral\Core\Exceptions\DependencyException;

class ExceptionsTest extends \PHPUnit_Framework_TestCase
{
    public function testArgumentException(string $param = null)
    {
        $method = new \ReflectionMethod($this, 'testArgumentException');

        $e = new ArgumentException(
            $method->getParameters()[0],
            $method
        );

        $this->assertInstanceOf(AutowireException::class, $e);
        $this->assertInstanceOf(ContainerException::class, $e);
        $this->assertInstanceOf(DependencyException::class, $e);
        $this->assertInstanceOf(\Interop\Container\Exception\ContainerException::class, $e);

        $this->assertSame($method, $e->getContext());
        $this->assertSame('param', $e->getParameter()->getName());
    }
}