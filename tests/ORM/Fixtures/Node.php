<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ORM\Fixtures;

class Node extends AbstactRecord
{
    const SCHEMA = [
        'id'    => 'primary',
        'name'  => 'string',
        'nodes' => [
            self::MANY_TO_MANY      => Node::class,
            self::THOUGHT_OUTER_KEY => 'parent_id',
            self::THOUGHT_INNER_KEY => 'child_id'
        ]
    ];
}