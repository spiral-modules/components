<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Exceptions\LoaderException;
use Spiral\ORM\LoaderInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

/**
 * ORM Loaders used to load an compile data tree based on results fetched from SQL databases,
 * loaders can communicate with parent selector by providing it's own set of conditions, columns
 * joins and etc. In some cases loader may create additional selector to load data using information
 * fetched from previous query.
 *
 * Attention, AbstractLoader can only work with ORM Records, you must implement LoaderInterface
 * in order to support external references (MongoDB and etc).
 *
 * Loaders can be used for both - loading and filtering of record data.
 *
 * Reference tree generation logic example:
 * User has many Posts (relation "posts"), user primary is ID, post inner key pointing to user
 * is USER_ID. Post loader must request User data loader to create references based on ID field
 * values. Once Post data were parsed we can mount it under parent user using mount method:
 *
 * @see RecordSelector::load()
 * @see RecordSelector::with()
 */
abstract class AbstractLoader implements LoaderInterface
{
    /**
     * Loading methods for data loaders.
     */
    const INLOAD    = 1;
    const POSTLOAD  = 2;
    const JOIN      = 3;
    const LEFT_JOIN = 4;

    /**
     * @var string
     */
    protected $class;

    /**
     * @invisible
     * @var ORMInterface
     */
    protected $orm;

    /**
     * Parent loader if any.
     *
     * @var AbstractLoader
     */
    protected $parent;

    /**
     * Loader options, can be altered on RecordSelector level.
     *
     * @var array
     */
    protected $options = [
        'method' => null,
        'join'   => 'INNER',
        'alias'  => null,
        'using'  => null,
        'where'  => null,
    ];

    /**
     * Associated record schema.
     *
     * @var array
     */
    protected $record = [];

    /**
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(string $class, ORMInterface $orm)
    {
        $this->class = $class;
        $this->orm = $orm;

        $this->record = $orm->define($class, ORMInterface::R_SCHEMA);
    }

    public function withParent(AbstractLoader $parent): self
    {
        $loader = clone $this;
        $loader->parent = $parent;

        return $loader;
    }

    public function withOptions(array $options): self
    {
        $loader = clone $this;
        $loader->parent = $parent;

        return $loader;
    }

    /**
     * Pre-load data on inner relation or relation chain. Method automatically called by Selector,
     * see load() method.
     *
     * @see RecordSelector::load()
     *
     * @param string $relation Relation name, or chain of relations separated by.
     * @param array  $options  Loader options (will be applied to last chain element only).
     *
     * @return LoaderInterface
     *
     * @throws LoaderException
     */
    public function loadRelation(string $relation, array $options)
    {

    }

    /**
     * Filter data on inner relation or relation chain. Method automatically called by Selector,
     * see with() method. Logic is identical to loader() method.
     *
     * Attention, you are only able to join ORM loaders!
     *
     * @see RecordSelector::load()
     *
     * @param string $relation Relation name, or chain of relations separated by.
     * @param array  $options  Loader options (will be applied to last chain element only).
     *
     * @return AbstractLoader
     *
     * @throws LoaderException
     */
    public function joinRelation(string $relation, array $options)
    {

    }

    protected function configureSelector()
    {
        //todo: implement
    }
}