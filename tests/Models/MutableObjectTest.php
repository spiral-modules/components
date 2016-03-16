<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Models;

use Mockery as m;
use Spiral\Models\MutableObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MutableObjectTest //extends \PHPUnit_Framework_TestCase
{
    //TODO: finish it

    public function testEventsDispatcher()
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, MutableClass::events());
        $this->assertInstanceOf(EventDispatcherInterface::class, MutableObject::events());
        $this->assertNotSame(MutableClass::events(), MutableObject::events());

        $class = new MutableClass();
        $this->assertSame(MutableClass::events(), $class->events());
    }

    public function testSetEventsDispatcher()
    {
        $events = m::mock(EventDispatcherInterface::class);
        MutableClass::setEvents($events);

        $this->assertSame($events, MutableClass::events());

        $class = new MutableClass();
        $this->assertSame($events, $class->events());

        MutableClass::setEvents(null);

        $this->assertInstanceOf(EventDispatcherInterface::class, $class->events());
        $this->assertNotSame($events, $class->events());
    }
}

class MutableClass extends MutableObject
{

}