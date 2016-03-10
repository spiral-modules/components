<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities;

use Spiral\Database\Entities\Database;
use Spiral\Database\Query\QueryResult;
use Spiral\ORM\Entities\Loaders\RootLoader;
use Spiral\ORM\Exceptions\LoaderException;
use Spiral\ORM\LoaderInterface;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * ORM Loaders used to load an compile data tree based on results fetched from SQL databases,
 * loaders can communicate with parent selector by providing it's own set of conditions, columns
 * joins and etc. In some cases loader may create additional selector to load data using information
 * fetched from previous query. Every loaded must be associated with specific record schema and
 * relation (except RootLoader).
 *
 * Loaders can be used for both - loading and filtering of record data.
 *
 * Reference tree generation logic example:
 * User has many Posts (relation "posts"), user primary is ID, post inner key pointing to user
 * is USER_ID. Post loader must request User data loader to create references based on ID field
 * values. Once Post data were parsed we can mount it under parent user using mount method:
 *
 * $this->parent->mount("posts", "ID", $data["USER_ID"], $data, true); //true = multiple
 *
 * @see Selector::load()
 * @see Selector::with()
 */
abstract class Loader implements LoaderInterface
{
    /**
     * Default loading methods for ORM loaders.
     */
    const INLOAD    = 1;
    const POSTLOAD  = 2;
    const JOIN      = 3;
    const LEFT_JOIN = 4;

    /**
     * Relation type is required to correctly resolve foreign record class based on relation
     * definition.
     */
    const RELATION_TYPE = null;

    /**
     * Default load method (inload or postload).
     */
    const LOAD_METHOD = null;

    /**
     * Internal loader constant used to decide how to aggregate data tree, true for relations like
     * MANY TO MANY or HAS MANY.
     */
    const MULTIPLE = false;

    /**
     * Count of Loaders requested data alias.
     *
     * @var int
     */
    private static $counter = 0;

    /**
     * Unique loader data alias (only for loaders, not joiners).
     *
     * @var string
     */
    private $alias = '';

    /**
     * Helper structure used to prevent data duplication when LEFT JOIN multiplies parent records.
     *
     * @invisible
     *
     * @var array
     */
    private $duplicates = [];

    /**
     * Loader configuration options, can be edited using setOptions method or while declaring loader
     * in Selector.
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
     * Result of data compilation, only populated in cases where loader is primary Selector loader.
     *
     * @var array
     */
    protected $result = [];

    /**
     * Container related to parent loader. Loaded data must be loaded using this container.
     *
     * @var string
     */
    protected $container = '';

    /**
     * Indication that loaded already set columns and conditions to parent Selector.
     *
     * @var bool
     */
    protected $configured = false;

    /**
     * Set of columns to be fetched from resulted query.
     *
     * @var array
     */
    protected $dataColumns = [];

    /**
     * Loader data offset in resulted query row provided by parent Selector or Loader.
     *
     * @var int
     */
    protected $dataOffset = 0;

    /**
     * Relation definition options if any.
     *
     * @var array
     */
    protected $definition = [];

    /**
     * Inner (nested) loaders.
     *
     * @var LoaderInterface[]
     */
    protected $loaders = [];

    /**
     * Loaders used purely for conditional purposes. Only ORM loaders can do that.
     *
     * @var Loader[]
     */
    protected $joiners = [];

    /**
     * Set of keys requested by inner loaders to be pre-aggregated while query parsing. This
     * structure if populated when new sub loaded registered.
     *
     * @var array
     */
    protected $referenceKeys = [];

    /**
     * Chunks of parsed data associated with their reference key name and it's value. Used to
     * compile data tree via php references.
     *
     * @var array
     */
    protected $references = [];

    /**
     * Related record schema.
     *
     * @invisible
     *
     * @var array
     */
    protected $schema = [];

    /**
     * ORM Loaders can only be nested into ORM Loaders.
     *
     * @invisible
     *
     * @var Loader|null
     */
    protected $parent = null;

    /**
     * @invisible
     *
     * @var ORM
     */
    protected $orm = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        ORM $orm,
        $container,
        array $definition = [],
        LoaderInterface $parent = null
    ) {
        $this->orm = $orm;

        //Related record schema
        $this->schema = $orm->schema($definition[static::RELATION_TYPE]);

        $this->container = $container;
        $this->definition = $definition;
        $this->parent = $parent;

        //Compiling options
        $this->options['method'] = static::LOAD_METHOD;

        if (!empty($parent)) {
            if (!$parent instanceof self || $parent->getDatabase() != $this->getDatabase()) {
                //We have to force post-load (separate query) if parent loader database is different
                $this->options['method'] = self::POSTLOAD;
            }
        }

        $this->dataColumns = array_keys($this->schema[ORM::M_COLUMNS]);
    }

    /**
     * Update loader options.
     *
     * @param array $options
     *
     * @return $this
     *
     * @throws LoaderException
     */
    public function setOptions(array $options = [])
    {
        $this->options = $options + $this->options;

        if (
            $this->isJoinable()
            && !empty($this->parent)
            && $this->parent->getDatabase() != $this->getDatabase()
        ) {
            throw new LoaderException('Unable to join tables located in different databases.');
        }

        return $this;
    }

    /**
     * Table name loader relates to.
     *
     * @return mixed
     */
    public function getTable()
    {
        return $this->schema[ORM::M_TABLE];
    }

    /**
     * Every loader declares an unique alias for it's source table based on options or based on
     * position in loaders chain. In addition, every loader responsible for data loading will add
     * "_data" postfix to it's alias.
     *
     * @return string
     */
    public function getAlias()
    {
        if (!empty($this->options['using'])) {
            //We are using another relation (presumably defined by with() to load data).
            return $this->options['using'];
        }

        if (!empty($this->options['alias'])) {
            return $this->options['alias'];
        }

        //We are not really worrying about default loader aliases, joiners more important
        if ($this->isLoadable()) {
            if (!empty($this->alias)) {
                //Alias was already created
                return $this->alias;
            }

            //New alias is pretty simple and short
            return $this->alias = 'd' . decoct(++self::$counter);
        }

        if (empty($this->parent)) {
            $alias = $this->getTable();
        } elseif ($this->parent instanceof RootLoader) {
            //This is first level of relation loading, we can use relation name by itself
            $alias = $this->container;
        } else {
            //Let's use parent alias to continue chain
            $alias = $this->parent->getAlias() . '_' . $this->container;
        }

        return $alias;
    }

    /**
     * Database name loader relates to.
     *
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->schema[ORM::M_DB];
    }

    /**
     * Instance of Dbal\Database data associated with loader instance, used as primary database
     * for selector is loader defined as primary selection loader.
     *
     * @return Database
     */
    public function dbalDatabase()
    {
        return $this->orm->database($this->schema[ORM::M_DB]);
    }

    /**
     * Get primary key name related to associated record.
     *
     * @return string|null
     */
    public function getPrimaryKey()
    {
        if (!isset($this->schema[ORM::M_PRIMARY_KEY])) {
            return;
        }

        return $this->getAlias() . '.' . $this->schema[ORM::M_PRIMARY_KEY];
    }

    /**
     * Pre-load data on inner relation or relation chain. Method automatically called by Selector,
     * see load() method.
     *
     * @see Selector::load()
     *
     * @param string $relation Relation name, or chain of relations separated by.
     * @param array  $options  Loader options (will be applied to last chain element only).
     *
     * @return LoaderInterface
     *
     * @throws LoaderException
     */
    public function loader($relation, array $options = [])
    {
        if (($position = strpos($relation, '.')) !== false) {
            //Chain of relations provided
            $nested = $this->loader(substr($relation, 0, $position), []);

            if (empty($nested) || !$nested instanceof self) {
                //todo: Think about the options
                throw new LoaderException(
                    'Only ORM loaders can be used to generate/configure chain of relation loaders.'
                );
            }

            //Recursively (will work only with ORM loaders).
            return $nested->loader(substr($relation, $position + 1), $options);
        }

        if (!isset($this->schema[ORM::M_RELATIONS][$relation])) {
            $container = $this->container ?: $this->schema[ORM::M_ROLE_NAME];

            throw new LoaderException(
                "Undefined relation '{$relation}' under '{$container}'."
            );
        }

        if (isset($this->loaders[$relation])) {
            $nested = $this->loaders[$relation];
            if (!$nested instanceof self) {
                throw new LoaderException(
                    'Only ORM loaders can be used to generate/configure chain of relation loaders.'
                );
            }

            //Updating existed loaded options
            $nested->setOptions($options);

            return $nested;
        }

        $relationOptions = $this->schema[ORM::M_RELATIONS][$relation];

        //Asking ORM for loader instance
        $loader = $this->orm->loader(
            $relationOptions[ORM::R_TYPE],
            $relation,
            $relationOptions[ORM::R_DEFINITION],
            $this
        );

        if (!empty($options) && !$loader instanceof self) {
            //todo: think about alternatives again
            throw new LoaderException(
                'Only ORM loaders can be used to generate/configure chain of relation loaders.'
            );
        }

        $loader->setOptions($options);
        $this->loaders[$relation] = $loader;

        if ($referenceKey = $loader->getReferenceKey()) {
            /*
             * Inner loader requests parent to pre-collect some keys so it can build tree using
             * references without looking up for correct record every time.
             */
            $this->referenceKeys[] = $referenceKey;
            $this->referenceKeys = array_unique($this->referenceKeys);
        }

        return $loader;
    }

    /**
     * Filter data on inner relation or relation chain. Method automatically called by Selector,
     * see with() method. Logic is identical to loader() method.
     *
     * @see Selector::load()
     *
     * @param string $relation Relation name, or chain of relations separated by.
     * @param array  $options  Loader options (will be applied to last chain element only).
     *
     * @return Loader
     *
     * @throws LoaderException
     */
    public function joiner($relation, array $options = [])
    {
        if (empty($options['method'])) {
            //We have to force joining method for full chain
            $options['method'] = self::JOIN;
        }

        if (($position = strpos($relation, '.')) !== false) {
            //Chain of relations provided
            $nested = $this->joiner(substr($relation, 0, $position), []);
            if (empty($nested) || !$nested instanceof self) {
                //todo: DRY
                throw new LoaderException(
                    'Only ORM loaders can be used to generate/configure chain of relation joiners.'
                );
            }

            //Recursively (will work only with ORM loaders).
            return $nested->joiner(substr($relation, $position + 1), $options);
        }

        if (!isset($this->schema[ORM::M_RELATIONS][$relation])) {
            $container = $this->container ?: $this->schema[ORM::M_ROLE_NAME];

            throw new LoaderException(
                "Undefined relation '{$relation}' under '{$container}'."
            );
        }

        if (isset($this->joiners[$relation])) {
            //Updating existed joiner options
            return $this->joiners[$relation]->setOptions($options);
        }

        $relationOptions = $this->schema[ORM::M_RELATIONS][$relation];

        $joiner = $this->orm->loader(
            $relationOptions[ORM::R_TYPE],
            $relation,
            $relationOptions[ORM::R_DEFINITION],
            $this
        );

        if (!$joiner instanceof self) {
            //todo: DRY
            throw new LoaderException(
                'Only ORM loaders can be used to generate/configure chain of relation joiners.'
            );
        }

        return $this->joiners[$relation] = $joiner->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function isMultiple()
    {
        return static::MULTIPLE;
    }

    /**
     * {@inheritdoc}
     */
    public function getReferenceKey()
    {
        //In most of cases reference key is inner key name (parent "ID" field name), don't be confused
        //by INNER_KEY, remember that we building relation from parent record point of view
        return $this->definition[RecordEntity::INNER_KEY];
    }

    /**
     * {@inheritdoc}
     */
    public function aggregatedKeys($referenceKey)
    {
        if (!isset($this->references[$referenceKey])) {
            return [];
        }

        return array_unique(array_keys($this->references[$referenceKey]));
    }

    /**
     * Create selector dedicated to load data for current loader.
     *
     * @return RecordSelector|null
     */
    public function createSelector()
    {
        if (!$this->isLoadable()) {
            return;
        }

        $selector = $this->orm->selector($this->definition[static::RELATION_TYPE], $this);

        //Setting columns to be loaded
        $this->configureColumns($selector);

        foreach ($this->loaders as $loader) {
            if ($loader instanceof self) {
                //Allowing sub loaders to configure required columns and conditions as well
                $loader->configureSelector($selector);
            }
        }

        foreach ($this->joiners as $joiner) {
            //Joiners must configure selector as well
            $joiner->configureSelector($selector);
        }

        return $selector;
    }

    /**
     * Configure provided selector with required joins, columns and conditions, in addition method
     * must pass configuration to sub loaders.
     *
     * Method called by Selector when loader set as primary selection loader.
     *
     * @param RecordSelector $selector
     */
    public function configureSelector(RecordSelector $selector)
    {
        if (!$this->isJoinable()) {
            //Loader can be used not only for loading but purely for filering
            if (empty($this->parent)) {
                foreach ($this->loaders as $loader) {
                    if ($loader instanceof self) {
                        $loader->configureSelector($selector);
                    }
                }

                foreach ($this->joiners as $joiner) {
                    //Nested joiners
                    $joiner->configureSelector($selector);
                }
            }

            return;
        }

        if (!$this->configured) {
            //We never configured loader columns before
            $this->configureColumns($selector);

            //Inload conditions and etc
            if (empty($this->options['using']) && !empty($this->parent)) {
                $this->clarifySelector($selector);
            }

            $this->configured = true;
        }

        foreach ($this->loaders as $loader) {
            if ($loader instanceof self) {
                $loader->configureSelector($selector);
            }
        }

        foreach ($this->joiners as $joiner) {
            $joiner->configureSelector($selector);
        }
    }

    /**
     * Implementation specific selector configuration, must create required joins, conditions and
     * etc.
     *
     * @param RecordSelector $selector
     */
    abstract protected function clarifySelector(RecordSelector $selector);

    /**
     * Parse QueryResult provided by parent loaders and populate data tree. Loader must pass parsing
     * to inner loaders also.
     *
     * @param QueryResult $result
     * @param int         $rowsCount
     *
     * @return array
     */
    public function parseResult(QueryResult $result, &$rowsCount)
    {
        foreach ($result as $row) {
            $this->parseRow($row);
            ++$rowsCount;
        }

        return $this->result;
    }

    /**
     * {@inheritdoc}
     *
     * Method will clarify Loader data tree result using nested loaders.
     */
    public function loadData()
    {
        foreach ($this->loaders as $loader) {
            if ($loader instanceof self && !$loader->isJoinable()) {
                if (!empty($selector = $loader->createSelector())) {
                    //Data will be automatically linked via references and mount method
                    $selector->fetchData();
                }
            } else {
                //Some other loader type or loader requested separate query to be created
                $loader->loadData();
            }
        }
    }

    /**
     * Get compiled data tree, method must be called only if loader were feeded with QueryResult
     * using parseResult() method. Attention, method must be called AFTER loadData() with additional
     * loaders were executed.
     *
     * @see loadData()
     * @see parseResult()
     *
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * {@inheritdoc}
     *
     * Data will be mounted using references.
     */
    public function mount($container, $key, $criteria, array &$data, $multiple = false)
    {
        foreach ($this->references[$key][$criteria] as &$subset) {
            if ($multiple) {
                if (isset($subset[$container]) && in_array($data, $subset[$container])) {
                    unset($subset);
                    continue;
                }

                $subset[$container][] = &$data;
                unset($subset);

                continue;
            }

            if (isset($subset[$container])) {
                $data = &$subset[$container];
            } else {
                $subset[$container] = &$data;
            }

            unset($subset);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $reconfigure Use this option to reset configured flag to force query
     *                          clarification on next query creation.
     */
    public function clean($reconfigure = false)
    {
        $this->duplicates = [];
        $this->references = [];
        $this->result = [];

        if ($reconfigure) {
            $this->configured = false;
        }

        foreach ($this->loaders as $loader) {
            if (!$loader instanceof self) {
                continue;
            }

            //POSTLOAD loaders create unique Selector every time, meaning we will have to flush flag
            //indicates that associated selector was configured
            $loader->clean($reconfigure || !$this->isLoadable());
        }
    }

    /**
     * Cloning selector presets.
     */
    public function __clone()
    {
        foreach ($this->loaders as $name => $loader) {
            $this->loaders[$name] = clone $loader;
        }

        foreach ($this->joiners as $name => $loader) {
            $this->joiners[$name] = clone $loader;
        }
    }

    /**
     * Destruct loader.
     */
    public function __destruct()
    {
        $this->clean();
        $this->loaders = [];
        $this->joiners = [];
    }

    /**
     * Indicates that loader columns must be included into query statement.
     *
     * @return bool
     */
    protected function isLoadable()
    {
        if (!empty($this->parent) && !$this->parent->isLoadable()) {
            //If parent not loadable we are no loadable also
            return false;
        }

        return $this->options['method'] !== self::JOIN
        && $this->options['method'] !== self::LEFT_JOIN;
    }

    /**
     * Indicated that loaded must generate JOIN statement.
     *
     * @return bool
     */
    protected function isJoinable()
    {
        if (!empty($this->options['using'])) {
            return true;
        }

        return in_array($this->options['method'], [self::INLOAD, self::JOIN, self::LEFT_JOIN]);
    }

    /**
     * If loader is joinable we can calculate join type based on way loader going to be used
     * (loading or filtering).
     *
     * @return string
     *
     * @throws LoaderException
     */
    protected function joinType()
    {
        if (!$this->isJoinable()) {
            throw new LoaderException('Unable to resolve Loader join type, Loader is not joinable.');
        }

        if ($this->options['method'] == self::JOIN) {
            return 'INNER';
        }

        return 'LEFT';
    }

    /**
     * Fetch record columns from query row, must use data offset to slice required part of query.
     *
     * @param array $row
     *
     * @return array
     */
    protected function fetchData(array $row)
    {
        //Combine column names with sliced piece of row
        return array_combine(
            $this->dataColumns,
            array_slice($row, $this->dataOffset, count($this->dataColumns))
        );
    }

    /**
     * In many cases (for example if you have inload of HAS_MANY relation) record data can be
     * replicated by many result rows (duplicated). To prevent wrong data linking we have to
     * deduplicate such records. This is only internal loader functionality and required due data
     * tree are built using php references.
     *
     * Method will return true if data is unique handled before and false in opposite case.
     * Provided data array will be automatically linked with it's unique state using references.
     *
     * @param array $data Reference to parsed record data, reference will be pointed to valid and
     *                    existed data segment if such data was already parsed.
     *
     * @return bool
     */
    protected function deduplicate(array &$data)
    {
        if (isset($this->schema[ORM::M_PRIMARY_KEY])) {
            //We can use record id as de-duplication criteria
            $criteria = $data[$this->schema[ORM::M_PRIMARY_KEY]];
        } else {
            //It is recommended to use primary keys in every record as it will speed up de-duplication.
            $criteria = serialize($data);
        }

        if (isset($this->duplicates[$criteria])) {
            //Duplicate is presented, let's reduplicate
            $data = $this->duplicates[$criteria];

            //Duplicate is presented
            return false;
        }

        //Let's force placeholders for every sub loaded
        foreach ($this->loaders as $container => $loader) {
            $data[$container] = $loader->isMultiple() ? [] : null;
        }

        //Remember record to prevent future duplicates
        $this->duplicates[$criteria] = &$data;

        return true;
    }

    /**
     * Generate sql identifier using loader alias and value from relation definition.
     *
     * Example:
     * $this->getKey(Record::OUTER_KEY);
     *
     * @param string $key
     *
     * @return string|null
     */
    protected function getKey($key)
    {
        if (!isset($this->definition[$key])) {
            return;
        }

        return $this->getAlias() . '.' . $this->definition[$key];
    }

    /**
     * SQL identified to parent record outer key (usually primary key).
     *
     * @return string
     *
     * @throws LoaderException
     */
    protected function getParentKey()
    {
        if (empty($this->parent)) {
            throw new LoaderException('Unable to get parent key, no parent loader provided.');
        }

        return $this->parent->getAlias() . '.' . $this->definition[RecordEntity::INNER_KEY];
    }

    /**
     * Configure columns required for loader data selection.
     *
     * @param RecordSelector $selector
     */
    protected function configureColumns(RecordSelector $selector)
    {
        if (!$this->isLoadable()) {
            return;
        }

        $this->dataOffset = $selector->generateColumns($this->getAlias(), $this->dataColumns);
    }

    /**
     * Reference criteria is value to be used to mount data into parent loader tree.
     *
     * Example:
     * User has many Posts (relation "posts"), user primary is ID, post inner key pointing to user
     * is USER_ID. Post loader must request User data loader to create references based on ID field
     * values. Once Post data were parsed we can mount it under parent user using mount method:
     *
     * $this->parent->mount("posts", "ID", $data["USER_ID"], $data, true); //true = multiple
     *
     * @see getReferenceKey()
     *
     * @param array $data
     *
     * @return mixed
     */
    protected function fetchCriteria(array $data)
    {
        if (!isset($data[$this->definition[RecordEntity::OUTER_KEY]])) {
            return;
        }

        return $data[$this->definition[RecordEntity::OUTER_KEY]];
    }

    /**
     * Parse single result row to generate data tree. Must pass parsing to every nested loader.
     *
     * @param array $row
     *
     * @return bool
     */
    private function parseRow(array $row)
    {
        if (!$this->isLoadable()) {
            //Nothing to parse, we are no waiting for any data
            return;
        }

        //Fetching only required part of resulted row
        $data = $this->fetchData($row);

        if (empty($this->parent)) {
            if ($this->deduplicate($data)) {
                //Yes, this is reference, i'm using this method to build data tree using nested parsers
                $this->result[] = &$data;

                //Registering references to simplify tree compilation for post and inner loaders
                $this->collectReferences($data);
            }

            $this->parseNested($row);

            return;
        }

        if (!$referenceCriteria = $this->fetchCriteria($data)) {
            //Relation not loaded
            return;
        }

        if ($this->deduplicate($data)) {
            //Registering references to simplify tree compilation for post and inner loaders
            $this->collectReferences($data);
        }

        //Mounting parsed data into parent under defined container
        $this->parent->mount(
            $this->container,
            $this->getReferenceKey(),
            $referenceCriteria,
            $data,
            static::MULTIPLE
        );

        $this->parseNested($row);
    }

    /**
     * Parse data using nested loaders.
     *
     * @param array $row
     */
    private function parseNested(array $row)
    {
        foreach ($this->loaders as $loader) {
            if ($loader instanceof self && $loader->isJoinable() && $loader->isLoadable()) {
                $loader->parseRow($row);
            }
        }
    }

    /**
     * Create internal references cache based on requested keys. For example, if we have request for
     * "id" as reference key, every record will create following structure:
     * $this->references[id][ID_VALUE] = ITEM.
     *
     * Only deduplicated data must be collected!
     *
     * @see deduplicate()
     *
     * @param array $data
     */
    private function collectReferences(array &$data)
    {
        foreach ($this->referenceKeys as $key) {
            //Adding reference(s)
            $this->references[$key][$data[$key]][] = &$data;
        }
    }
}
