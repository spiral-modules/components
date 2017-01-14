<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class Comment extends Record
{
    const SCHEMA = [
        'id'      => 'primary',
        'message' => 'string',

        'author' => [self::BELONGS_TO => User::class]
    ];
}