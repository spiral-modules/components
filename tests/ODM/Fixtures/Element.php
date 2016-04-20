<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\DocumentEntity;

class Element extends DocumentEntity
{
    /**
     * Entity schema.
     *
     * @var array
     */
    protected $schema = [
        'name' => 'string'
    ];
}