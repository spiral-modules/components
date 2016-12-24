<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\Document;

class User extends Document
{
    const COLLECTION = 'users';

    const SCHEMA     = [
        '_id'     => 'MongoId',
        'name'    => 'string',
        'superYo' => DataPiece::class
    ];
}