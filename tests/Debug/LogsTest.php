<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Tests\Debug;

use Interop\Container\ContainerInterface;
use Mockery as m;
use Psr\Log\NullLogger;
use Spiral\Core\Component;
use Spiral\Core\Container;
use Spiral\Debug\LogsInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Tests\Core\Fixtures\SampleComponent;

class LogsTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        SampleComponent::shareContainer(null);
        LoggedClass::shareLogger(null);
    }

    public function testNoContainer()
    {
        $class = new LoggedClass();
        $this->assertInstanceOf(NullLogger::class, $class->getLogger());
    }

    public function testPsrLogger()
    {
        $logger = new NullLogger();

        $class = new LoggedClass();
        $class->setLogger($logger);

        $this->assertSame($logger, $class->getLogger());
    }

    public function testSharedLogger()
    {
        $logger = new NullLogger();
        LoggedClass::shareLogger($logger);

        $classA = new LoggedClass();
        $this->assertSame($logger, $classA->getLogger());

        $classB = new LoggedClass();
        $this->assertSame($logger, $classB->getLogger());
    }

    public function testLoggerThoughtContainer()
    {
        $logger = new NullLogger();

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->with(LogsInterface::class)->andReturn(true);
        $container->shouldReceive('get')->with(LogsInterface::class)->andReturn(
            $logs = m::mock(LogsInterface::class)
        );

        $logs->shouldReceive('getLogger')->with(LoggedClass::class)->andReturn($logger);

        SampleComponent::shareContainer($container);

        $class = new LoggedClass();

        $this->assertSame($container, $class->getContainer());
        $this->assertSame($logger, $class->getLogger());
    }
}

class LoggedClass extends Component
{
    use LoggerTrait;

    public function getLogger()
    {
        return $this->logger();
    }

    public function getContainer()
    {
        return $this->container();
    }
}