<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\Document;

class GoodAggregates extends Document
{
    const SCHEMA = [
        '_id'    => 'MongoId',
        'userId' => 'MongoId',

        'user'  => [self::ONE => User::class, ['_id' => 'key::userId']],
        'users' => [self::MANY => User::class, []]
    ];
}