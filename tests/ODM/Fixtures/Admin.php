<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

class Admin extends User
{
    const SCHEMA = [
        'admins' => 'string',
        'manyYo' => [DataPiece::class]
    ];

    const DEFAULTS = [
        'admins' => 'all'
    ];
}