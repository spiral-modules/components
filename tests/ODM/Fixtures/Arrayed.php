<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use MongoDB\BSON\ObjectID;
use Spiral\ODM\DocumentEntity;

class Arrayed extends DocumentEntity
{
    const SCHEMA = [
        'strings' => ['string'],
        'numbers' => ['int'],
        'ids'     => [ObjectID::class]
    ];

    const DEFAULTS = [
        'strings' => [1234, 'test'],
        'numbers' => [1, 2, '3'],
        'ids'     => ['507f1f77bcf86cd799439011']
    ];
}