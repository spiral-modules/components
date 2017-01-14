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
    const SCHEMA = [
        'id'      => 'primary',
        'name'    => 'string',
        'status'  => 'enum(active, disabled)',
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
        'name'   => null,
        'status' => 'active'
    ];

    const INDEXES = [
        [self::INDEX, 'status']
    ];
}