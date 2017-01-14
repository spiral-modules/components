<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\ORM\ORMInterface;
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
     * @var array
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
        $relation->data = $data;

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
}