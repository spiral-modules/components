<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Tests\Core;

use Spiral\Core\Container;
use Spiral\Tests\Core\Fixtures\OtherComponent;
use Spiral\Tests\Core\Fixtures\SampleComponent;

class ComponentTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        SampleComponent::shareContainer(null);
    }

    public function testShareContainer()
    {
        $containerA = new Container();
        $containerB = new Container();

        $this->assertNull(SampleComponent::shareContainer($containerA));
        $this->assertSame($containerA, SampleComponent::shareContainer($containerB));
        $this->assertSame($containerB, SampleComponent::shareContainer(null));
    }

    public function testMissingContainer()
    {
        $component = new SampleComponent();
        $this->assertNull($component->getContainer());
    }

    public function testFallbackContainer()
    {
        $sharedContainer = new Container();
        $this->assertNull(SampleComponent::shareContainer($sharedContainer));

        $component = new SampleComponent();
        $this->assertSame($sharedContainer, $component->getContainer());
    }

    public function testLocalContainer()
    {
        $sharedContainer = new Container();
        $this->assertNull(SampleComponent::shareContainer($sharedContainer));

        $localContainer = new Container();

        $component = new OtherComponent($localContainer);
        $this->assertSame($localContainer, $component->getContainer());

        $component = new SampleComponent();
        $this->assertSame($sharedContainer, $component->getContainer());
    }

    public function testSharedScope()
    {
        $containerA = new Container();
        $containerB = new Container();
        $component = new SampleComponent();

        $this->assertNull(SampleComponent::shareContainer($containerA));
        $this->assertSame($containerA, $component->getContainer());

        $this->assertSame($containerA, SampleComponent::shareContainer($containerB));
        $this->assertSame($containerB, $component->getContainer());

        $this->assertSame($containerB, SampleComponent::shareContainer($containerA));
        $this->assertSame($containerA, $component->getContainer());
    }
}
