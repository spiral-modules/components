<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

use Spiral\ORM\Exceptions\InstantionException;
use Spiral\ORM\InstantiatorInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\RecordInterface;

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
     */
    public function identify($fields)
    {
        if (!is_array($fields)) {
            $fields = iterator_to_array($fields);
        }

        $primaryKeys = [];
        foreach ($this->schema[RecordEntity::SH_PRIMARY_KEY] as $primaryKey) {
            if (array_key_exists($primaryKey, $fields)) {
                $primaryKeys[] = $fields[$primaryKey];
            }
        }

        if (count($primaryKeys) === 0) {
            //Unable to create reliable identity
            return null;
        }

        return join('.', $primaryKeys);
    }

    /**
     * {@inheritdoc}
     *
     * @return RecordInterface
     *
     * @throws InstantionException
     */
    public function make($fields, int $state): RecordInterface
    {
        if (!is_array($fields)) {
            $fields = iterator_to_array($fields);
        }

        $class = $this->class;

        //Now we can construct needed class, in this case we are following DocumentEntity declaration
        if ($state == ORMInterface::STATE_LOADED) {
            //No need to filter values, passing directly in constructor
            return new $class($fields, $state, $this->orm, $this->schema);
        }

        if ($state != ORMInterface::STATE_NEW) {
            throw new InstantionException(
                "Undefined state {$state}, only NEW and LOADED are supported"
            );
        }

        /*
         * Filtering entity
         */

        $entity = new $class([], $state, $this->orm, $this->schema);
        if (!$entity instanceof RecordInterface) {
            throw new InstantionException(
                "Unable to set filtered values for '{$class}', must be instance of RecordInterface"
            );
        }

        //Must pass value thought all needed filters
        $entity->setFields($fields);

        return $entity;
    }

}
