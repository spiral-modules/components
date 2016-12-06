<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models;

use Spiral\Models\Events\DescribeEvent;
use Spiral\Models\Reflections\ReflectionEntity;

/**
 * Entity which code follows external behaviour schema.
 */
class SchematicEntity extends AbstractEntity
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
     * @param array $fields
     * @param array $schema
     */
    public function __construct(array $fields, array $schema)
    {
        $this->schema = $schema;
        parent::__construct($fields);
    }

    /**
     * {@inheritdoc}
     *
     * Include every composition public data into result.
     */
    public function publicFields(): array
    {
        $result = [];

        foreach ($this->getKeys() as $field => $value) {
            if (in_array($field, $this->schema[self::SH_HIDDEN])) {
                //We might need to use isset in future, for performance
                continue;
            }

            if ($value instanceof PublishableInterface) {
                $result[$field] = $value->publicFields();
            } else {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function isFillable(string $field): bool
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
    protected function getMutator(string $field, string $mutator)
    {
        if (isset($this->schema[self::SH_MUTATORS][$mutator][$field])) {
            return $this->schema[self::SH_MUTATORS][$mutator][$field];
        }

        return null;
    }

    /**
     * Method used while entity static analysis to describe model related property using even
     * dispatcher and associated model traits.
     *
     * @param ReflectionEntity $reflection
     * @param string           $property
     * @param mixed            $value
     *
     * @return mixed Returns filtered value.
     * @event describe(DescribeEvent)
     */
    public static function describeProperty(ReflectionEntity $reflection, string $property, $value)
    {
        static::initialize(true);

        /**
         * Clarifying property value using traits or other listeners.
         *
         * @var DescribeEvent $event
         */
        $event = static::events()->dispatch(
            'describe',
            new DescribeEvent($reflection, $property, $value)
        );

        return $event->getValue();
    }
}