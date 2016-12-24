<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM;

use MongoDB\BSON\ObjectID;
use Spiral\Models\Reflections\ReflectionEntity as RE;
use Spiral\ODM\Schemas\DocumentSchema as DS;
use Spiral\Tests\ODM\Fixtures\User;
use Spiral\Tests\ODM\Traits\ODMTrait;

class AccessorsTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;

    public function testSimpleFilter()
    {

    }
}