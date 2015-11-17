<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\ORM\Entities\Loader;
use Spiral\ORM\Entities\Selector;
use Spiral\ORM\Entities\WhereDecorator;
use Spiral\ORM\LoaderInterface;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * ManyToMany loader will not only load related data, but will include pivot table data into record
 * property "@pivot". Loader support WHERE conditions for both related data and pivot table.
 *
 * It's STRONGLY recommended to load many-to-many data using postload method. However relation still
 * can be used to filter query.
 */
class ManyToManyLoader extends Loader
{
    /**
     * Relation type is required to correctly resolve foreign record class based on relation
     * definition.
     */
    const RELATION_TYPE = RecordEntity::MANY_TO_MANY;

    /**
     * Default load method (inload or postload).
     */
    const LOAD_METHOD = self::POSTLOAD;

    /**
     * Internal loader constant used to decide how to aggregate data tree, true for relations like
     * MANY TO MANY or HAS MANY.
     */
    const MULTIPLE = true;

    /**
     * We have to redefine default Loader deduplication as many to many dedup data based on pivot
     * table, not record data itself.
     *
     * @var array
     */
    protected $duplicates = [];

    /**
     * Set of pivot table columns has to be fetched from resulted query.
     *
     * @var array
     */
    protected $pivotColumns = [];

    /**
     * Pivot columns offset in resulted query row.
     *
     * @var int
     */
    protected $pivotOffset = 0;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        ORM $orm,
        $container,
        array $definition = [],
        LoaderInterface $parent = null
    ) {
        parent::__construct($orm, $container, $definition, $parent);
        $this->pivotColumns = $this->definition[RecordEntity::PIVOT_COLUMNS];
    }

    /**
     * Pivot table name.
     *
     * @return string
     */
    public function pivotTable()
    {
        return $this->definition[RecordEntity::PIVOT_TABLE];
    }

    /**
     * Pivot table alias, depends on relation table alias.
     *
     * @return string
     */
    public function pivotAlias()
    {
        if (!empty($this->options['pivotAlias'])) {
            return $this->options['pivotAlias'];
        }

        return $this->getAlias() . '_pivot';
    }

    /**
     * {@inheritdoc}
     *
     * @param string $parentRole Helps ManyToMany relation to force record role for morphed
     *                           relations.
     */
    public function createSelector($parentRole = '')
    {
        if (empty($selector = parent::createSelector())) {
            return null;
        }

        //Pivot table joining (INNER in post selection)
        $pivotOuterKey = $this->getPivotKey(RecordEntity::THOUGHT_OUTER_KEY);
        $selector->innerJoin($this->pivotTable() . ' AS ' . $this->pivotAlias(), [
            $pivotOuterKey => $this->getKey(RecordEntity::OUTER_KEY)
        ]);

        //Pivot table conditions
        $this->pivotConditions($selector, $parentRole);

        if (empty($this->parent)) {
            return $selector;
        }

        //Where and morph conditions
        $this->mountConditions($selector);

        if (empty($this->parent)) {
            //For Many-To-Many loader
            return $selector;
        }

        //Aggregated keys (example: all parent ids)
        if (empty($aggregatedKeys = $this->parent->aggregatedKeys($this->getReferenceKey()))) {
            //Nothing to postload, no parents
            return null;
        }

        //Adding condition
        $selector->where($this->getPivotKey(RecordEntity::THOUGHT_INNER_KEY), 'IN',
            $aggregatedKeys);

        return $selector;
    }


    /**
     * {@inheritdoc}
     */
    protected function clarifySelector(Selector $selector)
    {
        $selector->join(
            $this->joinType(),
            $this->pivotTable() . ' AS ' . $this->pivotAlias(),
            [$this->getPivotKey(RecordEntity::THOUGHT_INNER_KEY) => $this->getParentKey()]
        );

        $this->pivotConditions($selector);

        $pivotOuterKey = $this->getPivotKey(RecordEntity::THOUGHT_OUTER_KEY);
        $selector->join($this->joinType(), $this->getTable() . ' AS ' . $this->getAlias(), [
            $pivotOuterKey => $this->getKey(RecordEntity::OUTER_KEY)
        ]);

        $this->mountConditions($selector);
    }

    /**
     * {@inheritdoc}
     *
     * Pivot table columns will be included.
     */
    protected function configureColumns(Selector $selector)
    {
        if (!$this->isLoadable()) {
            return;
        }

        $this->dataOffset = $selector->generateColumns(
            $this->getAlias(),
            $this->dataColumns
        );

        $this->pivotOffset = $selector->generateColumns(
            $this->pivotAlias(),
            $this->pivotColumns
        );
    }

    /**
     * Key related to pivot table. Must include pivot table alias.
     *
     * @see getKey()
     * @param string $key
     * @return null|string
     */
    protected function getPivotKey($key)
    {
        if (!isset($this->definition[$key])) {
            return null;
        }

        return $this->pivotAlias() . '.' . $this->definition[$key];
    }

    /**
     * Mounting pivot table conditions including user defined and morph key.
     *
     * @param Selector $selector
     * @param string   $parentRole
     * @return Selector
     */
    protected function pivotConditions(Selector $selector, $parentRole = '')
    {
        //We have to route all conditions to ON statement
        $router = new WhereDecorator($selector, 'onWhere', $this->pivotAlias());

        if (!empty($morphKey = $this->getPivotKey(RecordEntity::MORPH_KEY))) {
            $router->where(
                $morphKey,
                !empty($parentRole) ? $parentRole : $this->parent->schema[ORM::M_ROLE_NAME]
            );
        }

        if (!empty($this->definition[RecordEntity::WHERE_PIVOT])) {
            //Relation WHERE_PIVOT conditions
            $router->where($this->definition[RecordEntity::WHERE_PIVOT]);
        }

        //User specified WHERE conditions
        !empty($this->options['wherePivot']) && $router->where($this->options['wherePivot']);
    }

    /**
     * Set relational and user conditions.
     *
     * @param Selector $selector
     * @return Selector
     */
    protected function mountConditions(Selector $selector)
    {
        //Let's use where decorator to set conditions, it will automatically route tokens to valid
        //destination (JOIN or WHERE)
        $decorator = new WhereDecorator(
            $selector,
            $this->isJoinable() ? 'onWhere' : 'where',
            $this->getAlias()
        );

        if (!empty($this->definition[RecordEntity::WHERE])) {
            //Relation WHERE conditions
            $decorator->where($this->definition[RecordEntity::WHERE]);
        }

        //User specified WHERE conditions
        !empty($this->options['where']) && $decorator->where($this->options['where']);
    }

    /**
     * {@inheritdoc}
     *
     * We must parse pivot data.
     */
    protected function fetchData(array $row)
    {
        $data = parent::fetchData($row);

        $data[ORM::PIVOT_DATA] = array_combine(
            $this->pivotColumns,
            array_slice($row, $this->pivotOffset, count($this->pivotColumns))
        );

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * Parent criteria located in pivot data, not in record itself.
     */
    protected function fetchCriteria(array $data)
    {
        if (!isset($data[ORM::PIVOT_DATA][$this->definition[RecordEntity::THOUGHT_INNER_KEY]])) {
            return null;
        }

        return $data[ORM::PIVOT_DATA][$this->definition[RecordEntity::THOUGHT_INNER_KEY]];
    }

    /**
     * {@inheritdoc}
     *
     * We have to redefine default Loader deduplication as many to many dedup data based on pivot
     * table, not record data itself.
     */
    protected function deduplicate(array &$data)
    {
        $criteria = $data[ORM::PIVOT_DATA][$this->definition[RecordEntity::THOUGHT_INNER_KEY]]
            . '.' . $data[ORM::PIVOT_DATA][$this->definition[RecordEntity::THOUGHT_OUTER_KEY]];

        if (!empty($this->definition[RecordEntity::MORPH_KEY])) {
            $criteria .= ':' . $data[ORM::PIVOT_DATA][$this->definition[RecordEntity::MORPH_KEY]];
        }

        if (isset($this->duplicates[$criteria])) {
            //Duplicate is presented, let's reduplicate
            $data = $this->duplicates[$criteria];

            //Duplicate is presented
            return false;
        }

        //Let's remember record to prevent future duplicates
        $this->duplicates[$criteria] = &$data;

        return true;
    }
}