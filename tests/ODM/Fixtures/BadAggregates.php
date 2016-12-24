<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\Document;

class BadAggregates extends Document
{
    const SCHEMA = [
        'pieces' => [self::MANY => DataPiece::class, ['query' => 1]]
    ];
}