<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;
use Spiral\ORM\RecordInterface;
use Spiral\ORM\RelationInterface;

abstract class AbstractRelation implements RelationInterface
{
    /**
     * Indicates that relation commands must be executed prior to parent command.
     */
    const LEADING_RELATION = false;

    /**
     * Indication that relation have been loaded.
     *
     * @var bool
     */
    protected $loaded;

    /**
     * Parent record. Only read operations!
     *
     * @invisible
     * @var RecordInterface
     */
    protected $parent;

    /**
     * Class name relation points to.
     *
     * @var string
     */
    protected $class;

    /**
     * Relation schema, defined when ORM being compiled. Check relation config to find out what
     * schema producer been used for this relation accessor.
     *
     * @var array
     */
    protected $schema;

    /**
     * Related data.
     *
     * @invisible
     * @var array|null
     */
    protected $data = null;

    /**
     * Provides ability for lazy-loading model initialization and inner selects.
     *
     * @invisible
     * @var ORMInterface
     */
    protected $orm;

    /**
     * @param string       $class Owner model class name.
     * @param array        $schema
     * @param ORMInterface $orm
     */
    public function __construct(string $class, array $schema, ORMInterface $orm)
    {
        $this->class = $class;
        $this->schema = $schema;
        $this->orm = $orm;
    }

    /**
     * {@inheritdoc}
     */
    public function isLeading(): bool
    {
        return static::LEADING_RELATION;
    }

    /**
     * {@inheritdoc}
     */
    public function withContext(
        RecordInterface $parent,
        bool $loaded = false,
        array $data = null
    ): RelationInterface {
        $relation = clone $this;
        $relation->parent = $parent;
        $relation->loaded = $loaded;
        $relation->data = is_null($data) ? [] : $data;

        return $relation;
    }

    /**
     * {@inheritdoc}
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * {@inheritdoc}
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Get value from record based on schema key.
     *
     * @param RecordInterface $record
     * @param string          $key
     *
     * @return mixed
     */
    protected function value(RecordInterface $record, string $key)
    {
        return $record->getField($this->key($key));
    }

    /**
     * Key name from schema.
     *
     * @param int $key
     *
     * @return string
     */
    protected function key(int $key): string
    {
        return $this->schema[$key];
    }

    /**
     * Get primary key column
     *
     * @param RecordInterface $record
     *
     * @return string
     */
    protected function primaryColumnOf(RecordInterface $record): string
    {
        return $this->orm->define(get_class($record), ORMInterface::R_PRIMARY_KEY);
    }

    /**
     * @param $value
     */
    protected function assertValid($value)
    {
        if (is_null($value)) {
            if (!$this->schema[Record::NULLABLE]) {
                throw new RelationException("Relation is not nullable");
            }
        } elseif (!is_object($value)) {
            throw new RelationException(
                "Must be an instance of '{$this->class}', '" . gettype($value) . "' given"
            );
        } elseif (!is_a($value, $this->class, false)) {
            throw new RelationException(
                "Must be an instance of '{$this->class}', '" . get_class($value) . "' given"
            );
        }
    }

    /**
     * If record not synced or can't be synced. Only work for PK based relations.
     *
     * @param RecordInterface $inner
     * @param RecordInterface $outer
     *
     * @return bool
     */
    protected function isSynced(RecordInterface $inner, RecordInterface $outer): bool
    {
        if (empty($outer->primaryKey())) {
            //Parent not saved
            return false;
        }

        //Comparing FK values
        return $outer->getField(
                $this->key(Record::OUTER_KEY)
            ) == $inner->getField(
                $this->key(Record::INNER_KEY)
            );
    }
}