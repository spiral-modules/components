<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

class Recursive extends AbstactRecord
{
    const SCHEMA = [
        'id'     => 'bigPrimary',
        'parent' => [
            self::BELONGS_TO => self::class,
            self::NULLABLE   => true
        ],
    ];
}