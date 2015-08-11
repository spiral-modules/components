<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM;

use Spiral\ORM\Selector;


interface LoaderInterface
{
    /**
     * New instance of ORM Loader. Loader can always load additional components using
     * ORM->getContainer().
     *
     * @param ORM    $orm
     * @param string $container  Location in parent loaded where data should be attached.
     * @param array  $definition Definition compiled by relation relation schema and stored in ORM
     *                           cache.
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
     * this is primary key of parent model.
     *
     * @return string
     */
    public function getReferenceKey();

    /**
     * Get list of unique keys aggregated by loader while data parsing. This list used by sub-loaders
     * in situations where data has to be loader with POSTLOAD method (usually this value will go
     * directly to WHERE IN statement).
     *
     * @param string $referenceKey
     * @return array
     */
    public function getAggregatedKeys($referenceKey);


    /**
     * Run post selection queries to clarify fetched model data. Usually many conditions will be
     * fetched from there. Additionally this method may be used to create relations to external
     * source of data (ODM, elasticSearch and etc).
     */
    public function loadData();

    /**
     * Mount model data to parent loader under specified container, using reference key (inner key)
     * and reference criteria (outer key value).
     *
     * Example:
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
     * @param array  $data
     * @param bool   $multiple If true all mounted records will added to array.
     */
    public function mount($container, $key, $criteria, array &$data, $multiple = false);

    /**
     * Clean loader data.
     */
    public function clean();
}