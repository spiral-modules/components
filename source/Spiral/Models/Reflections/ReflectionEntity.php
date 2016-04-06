<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models\Reflections;

use Spiral\Models\AbstractEntity;

/**
 * Provides ability to generate entity schema based on given entity class and default property
 * values, support value inheritance!
 */
class ReflectionEntity
{
    /**
     * Required to validly merge parent and children attributes.
     */
    const BASE_CLASS = AbstractEntity::class;

    /**
     * Properties cache.
     *
     * @invisible
     *
     * @var array
     */
    private $cache = [];

    /**
     * @var \ReflectionClass
     */
    private $reflection = null;

    /**
     * Only support SchematicEntity classes!
     *
     * @param string $class
     */
    public function __construct($class)
    {
        $this->reflection = new \ReflectionClass($class);
    }
}