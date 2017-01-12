<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class User extends Record
{
    const SCHEMA = [
        'id'    => 'primary',
        'name'  => 'string',
        'email' => 'string',

        //Related posts
        'posts' => [self::HAS_MANY => Post::class]
    ];

    const INDEXES = [
        [self::UNIQUE, 'email']
    ];
}