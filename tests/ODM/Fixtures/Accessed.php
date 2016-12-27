<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use MongoDB\BSON\ObjectID;
use Spiral\ODM\Document;

class Accessed extends Document
{
    const SCHEMA = [
        '_id'  => ObjectID::class,
        'name' => 'string',

        'tags'       => ['string'],
        'relatedIDs' => [ObjectID::class]
    ];
}