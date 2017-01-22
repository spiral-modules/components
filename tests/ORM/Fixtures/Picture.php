<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class Picture extends AbstactRecord
{
    const SCHEMA = [
        'id'     => 'primary',
        'url'    => 'string',
        'parent' => [
            self::BELONGS_TO_MORPHED => PicturedInterface::class,
            self::INVERSE            => [Record::HAS_ONE, 'picture']
        ]
    ];
}