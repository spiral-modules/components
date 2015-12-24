<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\Cases\Events;

use Spiral\Events\Traits\EventsTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventsTest extends \PHPUnit_Framework_TestCase
{
    use EventsTrait;

    public function testTrait()
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, self::events());

        $dispatcher = new EventDispatcher();
        self::setEvents($dispatcher);
        $this->assertEquals($dispatcher, self::events());

        /**
         * The rest is verified by Symfony... i hope.
         */
    }
}
