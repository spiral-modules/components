<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\DocumentEntity;

class BadRecursivePiece extends DocumentEntity
{
    const SCHEMA = [
        'name'  => 'string',
        'child' => self::class
    ];

    const DEFAULTS = [
        'name'  => 'test',
        'child' => ['name' => 'nested'] //This is wrong
    ];
}