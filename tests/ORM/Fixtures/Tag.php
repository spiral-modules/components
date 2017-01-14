<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class Tag extends Record
{
    const SCHEMA = [
        'id'   => 'primary',
        'name' => 'string(32)'
    ];
}