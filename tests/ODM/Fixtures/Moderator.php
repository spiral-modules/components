<?php
/**
 * spiral-empty.dev
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

class Moderator extends User
{
    const COLLECTION = 'moderators';

    const SCHEMA = [
        'moderates' => 'string'
    ];

    const DEFAULTS = [
        'moderates' => 'forums'
    ];

    const INDEXES = [
        ['moderates']
    ];
}