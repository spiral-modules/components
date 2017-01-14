<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

/**
 * @property string $name
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
            self::NULLABLE          => false,
            Post::INVERSE           => 'author',
            self::CREATE_CONSTRAINT => false
        ]
    ];

    const DEFAULTS = [
        'name'   => null,
        'status' => 'active'
    ];

    const INDEXES = [
        [self::INDEX, 'status']
    ];
}