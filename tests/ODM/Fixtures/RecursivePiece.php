<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\DocumentEntity;

class RecursivePiece extends DocumentEntity
{
    const SCHEMA = [
        'name'  => 'string',
        'child' => self::class
    ];

    const DEFAULTS = [
        'name' => 'test',
    ];
}