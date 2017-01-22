<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

/**
 * @property int     $id
 * @property string  $name
 * @property string  $status
 * @property Post[]  $posts
 * @property Profile $profile
 */
class User extends AbstactRecord
{
    //nothing is secured
    const SECURED = [];

    const SCHEMA = [
        'id'      => 'primary',
        'name'    => 'string',
        'status'  => UserStatus::class,
        'balance' => 'float',

        //Relations
        'posts'   => [
            self::HAS_MANY          => Post::class,
            Post::INVERSE           => 'author',
            Post::NULLABLE          => false,
            self::CREATE_CONSTRAINT => false
        ],

        'profile' => [self::HAS_ONE => Profile::class]
    ];

    const DEFAULTS = [
        'name' => null
    ];

    const INDEXES = [
        [self::INDEX, 'status']
    ];
}