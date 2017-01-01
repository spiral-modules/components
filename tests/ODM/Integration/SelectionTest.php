<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Integration;

use Spiral\Tests\ODM\Traits\ODMTrait;

/**
 * Test data storage and operations with real mongo (there is no other way to test Cursor since
 * it's final).
 *
 * Only when MongoDatabase configured.
 */
class SelectionTest extends \PHPUnit_Framework_TestCase
{
    use ODMTrait;
}