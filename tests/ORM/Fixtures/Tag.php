<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

class Tag extends AbstactRecord
{
    const SCHEMA = [
        'id'   => 'primary',
        'name' => 'string(32)'
    ];
}