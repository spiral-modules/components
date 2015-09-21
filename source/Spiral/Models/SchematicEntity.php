<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\Models;

use Spiral\Core\ContainerInterface;

/**
 * Entity which code follows external behaviour schema.
 */
class SchematicEntity extends DataEntity
{
    /**
     * Schema constants. Starts with 4, but why not?
     */
    const SH_HIDDEN    = 4;
    const SH_SECURED   = 5;
    const SH_FILLABLE  = 6;
    const SH_MUTATORS  = 7;
    const SH_VALIDATES = 8;

    /**
     * Behaviour schema.
     *
     * @var array
     */
    private $schema = [];

    /**
     * @param array $schema
     */
    public function __construct(array $schema)
    {
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     *
     * Include every composition public data into result.
     */
    public function publicFields()
    {
        $result = [];

        foreach ($this->fields as $field => $value) {
            if (in_array($field, $this->schema[self::SH_HIDDEN])) {
                //We might need to use isset in future, for performance
                continue;
            }

            $result[$field] = $value;
        }

        return $this->fire('publicFields', $result);
    }

    /**
     * {@inheritdoc}
     */
    public function validator(array $rules = [], ContainerInterface $container = null)
    {
        //Initiate validation using rules declared in odmSchema
        return parent::validator(
            !empty($rules) ? $rules : $this->schema[self::SH_VALIDATES],
            $container
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function isFillable($field)
    {
        if (!empty($this->schema[self::SH_FILLABLE])) {
            return in_array($field, $this->schema[self::SH_FILLABLE]);
        }

        if ($this->schema[self::SH_SECURED] === '*') {
            return false;
        }

        return !in_array($field, $this->schema[self::SH_SECURED]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getMutator($field, $mutator)
    {
        if (isset($this->schema[self::SH_MUTATORS][$mutator][$field])) {
            return $this->schema[self::SH_MUTATORS][$mutator][$field];
        }

        return null;
    }
}