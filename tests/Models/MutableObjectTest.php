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
use Symfony\Component\EventDispatcher\GenericEvent;

class MutableObjectTest extends \PHPUnit_Framework_TestCase
{
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

    public function testFireEvent()
    {
        $events = m::mock(EventDispatcherInterface::class);
        MutableClass::setEvents($events);

        $events->shouldReceive('dispatch')->with(
            'test',
            m::on(function (GenericEvent $event) {
                return $event->getSubject() == 'subject';
            })
        )->andReturn(
            new GenericEvent('out subject')
        );

        $class = new MutableClass();
        $this->assertInstanceOf(GenericEvent::class, $class->doSomething());
        $this->assertSame('out subject', $class->doSomething()->getSubject());

    }
}

class MutableClass extends MutableObject
{
    public function doSomething()
    {
        return $this->dispatch('test', new GenericEvent(
            'subject'
        ));
    }
}