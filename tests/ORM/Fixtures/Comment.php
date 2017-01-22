<?php
/**
 * spiral-empty.dev
 *
 * @author Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

class Comment extends AbstactRecord
{
    const SCHEMA = [
        'id'       => 'primary',
        'message'  => 'string',
        'approved' => 'bool',
        'author'   => [
            self::BELONGS_TO => User::class,
            self::INVERSE    => [User::HAS_MANY, 'comments']
        ]
    ];
}