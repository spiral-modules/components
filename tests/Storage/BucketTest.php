<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Storage;

use Mockery as m;
use Spiral\Storage\Entities\StorageBucket;
use Spiral\Storage\ServerInterface;

class BucketTest extends \PHPUnit_Framework_TestCase
{
    public function testWithOptions()
    {
        $bucket = new StorageBucket(
            'bucket',
            'bucket:',
            [
                'name' => 'value'
            ],
            m::mock(ServerInterface::class)
        );

        $this->assertSame('value', $bucket->getOption('value'));

        $bucket1 = $bucket->withOption('name', 'value1');
        $this->assertSame('value', $bucket->getOption('value'));
        $this->assertSame('value1', $bucket1->getOption('value'));
    }
}