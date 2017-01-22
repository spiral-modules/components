<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\RecordEntity;

class Picture extends RecordEntity
{
    const SCHEMA = [
        'id'     => 'primary',
        'url'    => 'string',
        'parent' => [
            self::BELONGS_TO_MORPHED => PicturedInterface::class,
            self::INVERSE            => 'picture'
        ]
    ];
}