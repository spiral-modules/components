<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

class Profile extends AbstactRecord
{
    const SCHEMA = [
        'id'  => 'bigPrimary',
        'bio' => 'text'
    ];
}