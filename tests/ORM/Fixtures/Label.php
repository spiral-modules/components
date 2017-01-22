<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class Label extends AbstactRecord
{
    const SCHEMA = [
        'id'     => 'primary',
        'name'   => 'string',
        'parent' => [
            self::BELONGS_TO_MORPHED => LabelledInterface::class,
            self::INVERSE            => [Record::HAS_MANY, 'labels']
        ]
    ];
}