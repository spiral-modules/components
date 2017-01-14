<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

use Spiral\Database\Schemas\Prototypes\AbstractColumn;

class Post extends AbstactRecord
{
    const SCHEMA = [
        'id'      => 'bigPrimary',
        'title'   => 'string(64)',
        'content' => 'text',
        'public'  => 'bool',

        'comments' => [
            self::HAS_MANY   => Comment::class,
            Comment::INVERSE => 'post'
        ],

        'tags' => [
            self::MANY_TO_MANY   => Tag::class,
            self::PIVOT_COLUMNS  => ['time_linked' => 'datetime'],
            self::PIVOT_DEFAULTS => ['time_linked' => AbstractColumn::DATETIME_NOW],
            Tag::INVERSE         => 'posts'
        ]
    ];
}