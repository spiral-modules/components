<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class User extends Record
{
    const SCHEMA = [
        'primary' => 'id',
        'name'    => 'string'
    ];
}