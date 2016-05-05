<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\DocumentEntity;

class Data extends DocumentEntity
{
    /**
     * Entity schema.
     *
     * @var array
     */
    protected $odmSchema = [
        'name'     => 'string',
        'elements' => [Element::class]
    ];
}