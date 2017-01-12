<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

use Spiral\ORM\Record;

class Post extends Record
{
    const SCHEMA = [
        'id'    => 'primary',
        'title' => 'string'
    ];
}