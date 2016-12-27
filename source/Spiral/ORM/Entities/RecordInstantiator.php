<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

use Spiral\Models\ActiveEntityInterface;
use Spiral\ORM\Exceptions\InstantionException;
use Spiral\ORM\InstantiatorInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

/**
 * Default instantiator for records.
 */
class RecordInstantiator implements InstantiatorInterface
{
    /**
     * @invisible
     * @var ORMInterface
     */
    private $orm;

    /**
     * Record class.
     *
     * @var string
     */
    private $class = '';

    /**
     * Normalized schema delivered by RecordSchema.
     *
     * @var array
     */
    private $schema = [];

    /**
     * @param ORMInterface $orm
     * @param string       $class
     * @param array        $schema
     */
    public function __construct(ORMInterface $orm, string $class, array $schema)
    {
        $this->orm = $orm;
        $this->class = $class;
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     *
     * @return ActiveEntityInterface
     *
     * @throws InstantionException
     */
    public function instantiate($fields, bool $filter = true): ActiveEntityInterface
    {
        if (!is_array($fields)) {
            $fields = $this->normalizeFields($fields);
        }

        $class = $this->class;

        //Now we can construct needed class, in this case we are following DocumentEntity declaration
        if (!$filter) {
            //No need to filter values, passing directly in constructor
            return new $class($fields, $this->schema, $this->orm);
        }

        $entity = new $class($fields, $this->schema, $this->orm);
        if (!$entity instanceof Record) {
            throw new InstantionException(
                "Unable to set filtered values for '{$class}', must be instance of Record"
            );
        }

        //Must pass value thought all needed filters
        $entity->stateValue($fields);

        return $entity;
    }

    /**
     * @param \Traversable|\ArrayAccess $fields
     *
     * @return array
     */
    private function normalizeFields($fields): array
    {
        $result = [];
        if (!is_scalar($fields)) {
            //Trying to iterate over
            foreach ($fields as $name => $value) {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}