<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Models;

use Mockery as m;
use Spiral\Models\DataEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class EventsTest extends \PHPUnit_Framework_TestCase
{
    public function testEventsDispatcher()
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, EventsTestEntity::events());
        $this->assertInstanceOf(EventDispatcherInterface::class, DataEntity::events());
        $this->assertNotSame(EventsTestEntity::events(), DataEntity::events());

        $class = new EventsTestEntity();
        $this->assertSame(EventsTestEntity::events(), $class->events());
    }

    public function testSetEventsDispatcher()
    {
        $events = m::mock(EventDispatcherInterface::class);
        EventsTestEntity::setEvents($events);

        $this->assertSame($events, EventsTestEntity::events());

        $class = new EventsTestEntity();
        $this->assertSame($events, $class->events());

        EventsTestEntity::setEvents(null);

        $this->assertInstanceOf(EventDispatcherInterface::class, $class->events());
        $this->assertNotSame($events, $class->events());
    }

    public function testFireEvent()
    {
        $events = m::mock(EventDispatcherInterface::class);
        EventsTestEntity::setEvents($events);

        $events->shouldReceive('dispatch')->with(
            'test',
            m::on(function (GenericEvent $event) {
                return $event->getSubject() == 'subject';
            })
        )->andReturn(
            new GenericEvent('out subject')
        );

        $class = new EventsTestEntity();
        $this->assertInstanceOf(GenericEvent::class, $class->doSomething());
        $this->assertSame('out subject', $class->doSomething()->getSubject());
    }
}

class EventsTestEntity extends DataEntity
{
    public function doSomething()
    {
        return $this->dispatch('test', new GenericEvent(
            'subject'
        ));
    }
}