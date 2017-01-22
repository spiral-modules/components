<?php
/**
 * spiral-empty.dev
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\Document;

class NullableComposition extends Document
{
    const COLLECTION = 'users';

    const FILLABLE = [
        'piece'
    ];

    const SCHEMA = [
        '_id'   => 'MongoId',
        'name'  => 'string',
        'piece' => DataPiece::class
    ];

    const DEFAULTS = [
        'piece' => null
    ];

    const INDEXES = [
        ['name', '@options' => ['unique' => true]]
    ];
}