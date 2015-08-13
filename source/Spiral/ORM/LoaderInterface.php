<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\ORM\Exceptions\LoaderException;
use Spiral\ORM\Selector;

/**
 * ORM loaders responsible for loading nested and related data inside parent Selector. Every loader
 * must have defined container to describe where loaded data should be mounted in it's parent.
 *
 * Some loader implementations (see Loader) allows not only data loading, but manipulations with
 * parent Selector to create filters and joins.
 *
 * @see Selector
 * @see Loader
 * @see LoaderInterface::mount()
 */
interface LoaderInterface
{
    /**
     * @param ORM                  $orm
     * @param string               $container
     * @param array                $definition
     * @param LoaderInterface|null $parent
     * @throws LoaderException
     */
    public function __construct(
        ORM $orm,
        $container,
        array $definition = [],
        LoaderInterface $parent = null
    );

    /**
     * Is loader represent multiple records or one.
     *
     * @return bool
     */
    public function isMultiple();

    /**
     * Reference key (from parent object) required to speed up data normalization. In most of cases
     * this is primary key of parent model. Method must return name of field which parent will
     * pre-aggregate.
     *
     * @return string
     * @throws LoaderException
     */
    public function getReferenceKey();

    /**
     * Must return array of unique values of specified column by it's name (key). In order to optimize
     * loadings, LoaderInterface must declare such column name in getReferenceKey to it's parent before
     * requesting for aggregation. Keys like that can be used in IN statements for post loaders.
     *
     * @see getReferenceKey()
     * @param string $referenceKey
     * @return array
     * @throws LoaderException
     */
    public function aggregatedKeys($referenceKey);

    /**
     * Load data. Internal loader logic must mount every loader chunk of data into parent loader
     * using mount method, container name and data key.
     *
     * @see mount()
     * @throws LoaderException
     */
    public function loadData();

    /**
     * Mount model data into internal data storage under specified container using reference key
     * (inner key) and reference criteria (outer key value).
     *
     * Example (default ORM Loaders):
     * $this->parent->mount('profile', 'id', 1, [
     *      'id' => 100,
     *      'user_id' => 1,
     *      ...
     * ]);
     *
     * In this example "id" argument is inner key of "user" model and it's linked to outer key
     * "user_id" in "profile" model, which defines reference criteria as 1.
     *
     * @param string $container
     * @param string $key
     * @param mixed  $criteria
     * @param array  $data     Data must be referenced to existed set if it was registered
     *                         previously.
     * @param bool   $multiple If true all mounted records will added to array.
     * @throws LoaderException
     */
    public function mount($container, $key, $criteria, array &$data, $multiple = false);

    /**
     * Clean loader data.
     *
     * @throws LoaderException
     */
    public function clean();
}