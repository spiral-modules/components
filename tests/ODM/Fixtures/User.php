<?php
/**
 * spiral-empty.dev
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\Document;
use Spiral\ODM\Traits\SourceTrait;

class User extends Document
{
    use SourceTrait;

    const COLLECTION = 'users';

    const SCHEMA = [
        '_id'   => 'MongoId',
        'name'  => 'string',
        'piece' => DataPiece::class
    ];

    const FILLABLE = [
        'piece'
    ];

    const INDEXES = [
        ['name', '@options' => ['unique' => true]]
    ];
}