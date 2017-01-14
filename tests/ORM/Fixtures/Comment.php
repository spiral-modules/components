<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

class Comment extends AbstactRecord
{
    const SCHEMA = [
        'id'      => 'primary',
        'message' => 'string',

        'author' => [self::BELONGS_TO => User::class]
    ];
}