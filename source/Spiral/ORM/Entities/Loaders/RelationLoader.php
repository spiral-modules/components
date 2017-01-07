<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

/**
 * Provides ability to load relation data in a form of JOIN or external query.
 */
abstract class RelationLoader extends AbstractLoader
{
    protected $options = [
        'method' => null,
        'join'   => 'INNER',
        'alias'  => null,
        'using'  => null,
        'where'  => null,
    ];
}