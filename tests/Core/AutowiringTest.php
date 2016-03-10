<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Tests\Core;

use Spiral\Core\Container;
use Spiral\Tests\Core\Fixtures\Bucket;
use Spiral\Tests\Core\Fixtures\DependedClass;
use Spiral\Tests\Core\Fixtures\ExtendedSample;
use Spiral\Tests\Core\Fixtures\SampleClass;
use Spiral\Tests\Core\Fixtures\SoftDependedClass;

/**
 * The most fun test.
 */
class AutowiringTest extends \PHPUnit_Framework_TestCase
{
    public function testSimple()
    {
        $container = new Container();

        $this->assertInstanceOf(SampleClass::class, $container->get(SampleClass::class));
        $this->assertInstanceOf(SampleClass::class, $container->make(SampleClass::class, []));
    }

    public function testFollowBindings()
    {
        $container = new Container();

        $container->bind(SampleClass::class, ExtendedSample::class);

        $this->assertInstanceOf(ExtendedSample::class, $container->get(SampleClass::class));
        $this->assertInstanceOf(ExtendedSample::class, $container->make(SampleClass::class, []));
    }

    /**
     * @expectedException \Spiral\Core\Exceptions\Container\ArgumentException
     * @expectedExceptionMessage Unable to resolve 'name' argument in
     *                           'Spiral\Tests\Fixtures\Bucket::__construct'
     */
    public function testArgumentException()
    {
        $container = new Container();

        $bucket = $container->get(Bucket::class);
    }

    public function testDefaultValue()
    {
        $container = new Container();

        $bucket = $container->make(Bucket::class, ['name' => 'abc']);

        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertSame('abc', $bucket->getName());
        $this->assertSame('default-data', $bucket->getData());
    }

    public function testCascade()
    {
        $container = new Container();

        $object = $container->make(DependedClass::class, [
            'name' => 'some-name'
        ]);

        $this->assertInstanceOf(DependedClass::class, $object);
        $this->assertSame('some-name', $object->getName());
        $this->assertInstanceOf(SampleClass::class, $object->getSample());
    }

    public function testCascadeFollowBindings()
    {
        $container = new Container();

        $container->bind(SampleClass::class, ExtendedSample::class);

        $object = $container->make(DependedClass::class, [
            'name' => 'some-name'
        ]);

        $this->assertInstanceOf(DependedClass::class, $object);
        $this->assertSame('some-name', $object->getName());
        $this->assertInstanceOf(ExtendedSample::class, $object->getSample());
    }

    /**
     * @expectedException \Spiral\Core\Exceptions\Container\AutowireException
     * @expectedExceptionMessage Undefined class or binding 'WrongClass'
     */
    public function testAutowireException()
    {
        $container = new Container();

        $container->bind(SampleClass::class, \WrongClass::class);
        $container->make(DependedClass::class, [
            'name' => 'some-name'
        ]);
    }

    public function testAutowireWithDefaultOnWrongClass()
    {
        $container = new Container();

        $container->bind(SampleClass::class, \WrongClass::class);

        $object = $container->make(SoftDependedClass::class, [
            'name' => 'some-name'
        ]);

        $this->assertInstanceOf(SoftDependedClass::class, $object);
        $this->assertSame('some-name', $object->getName());
        $this->assertNull($object->getSample());
    }
}
