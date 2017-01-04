<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use Spiral\ODM\Schemas\NullLocator;

class NullLocatorTest extends \PHPUnit_Framework_TestCase
{
    public function testLocator()
    {
        $locator = new NullLocator();

        $this->assertSame([], $locator->locateSchemas());
        $this->assertSame([], $locator->locateSources());
    }
}