<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\Database\Entities\Table;
use Spiral\Database\Exceptions\QueryException;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Models\EntityInterface;
use Spiral\ORM\Entities\Loaders\ManyToManyLoader;
use Spiral\ORM\Entities\Relation;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * Provides ability to load records related using pivot table, link, unlink and check such records.
 * Relation support WHERE_PIVOT conditions.
 */
class ManyToMany extends Relation
{
    /**
     * Connection errors.
     */
    use LoggerTrait;

    /**
     * Relation type, required to fetch record class from relation definition.
     */
    const RELATION_TYPE = RecordEntity::MANY_TO_MANY;

    /**
     * Indication that relation represent multiple records (HAS_MANY relations).
     */
    const MULTIPLE = true;

    /**
     * Forced value of parent role, used by morphed many to many.
     *
     * @var string
     */
    private $parentRole = '';

    /**
     * Force parent role name (for morphed relations only).
     *
     * @param string $role
     */
    public function setRole($role)
    {
        $this->parentRole = $role;
    }

    /**
     * Check if Record(s) associated with this relation. Method can accept one id, array of ids,
     * or instance of ActiveRecord. In case of multiple ids provided method will return true only
     * if every record is linked to relation.
     *
     * Attention, WHERE conditions are not involved in has, link and other methods!
     *
     * Examples:
     * $user->tags()->has($tag);
     * $user->tags()->has([$tagA, $tagB]);
     * $user->tags()->has(1);
     * $user->tags()->has([1, 2, 3, 4]);
     *
     * @param mixed $outer
     * @return bool
     */
    public function has($outer)
    {
        $selectQuery = $this->pivotTable()->select()->where($this->wherePivot(
            $this->parentKey(),
            $this->prepareRecords($outer)
        ));

        //We can use hasEach methods there, but this is more optimal way
        return $selectQuery->count() == count($outer);
    }

    /**
     * Link one of multiple related records. You can pass pivotData as additional argument or
     * associate it with record id. Connections will only be created not updated, check sync()
     * method. Do not forget to pre-populate pivot columns if you using WHERE_PIVOT conditions.
     *
     * Examples:
     * $user->tags()->link(1);
     * $user->tags()->link($tag);
     * $user->tags()->link([1, 2], ['approved' => true]);
     * $user->tags()->link([
     *      1 => ['approved' => true],
     *      2 => ['approved' => false]
     * ]);
     *
     * Method will not affect state of pre-loaded data! Use reset() method to do that.
     *
     * @see sync()
     * @param mixed $outer
     * @param array $pivotData
     */
    public function link($outer, array $pivotData = [])
    {
        //I need different method here
        $pivotRows = $this->prepareRecords($outer, $pivotData);
        $linkedIDs = $this->linkedIDs(array_keys($pivotRows));

        foreach ($pivotRows as $recordID => $pivotRow) {
            if (!in_array($recordID, $linkedIDs)) {
                /**
                 * In future this statement should be optimized to use batchInsert in cases when
                 * set of columns for every record is the same.
                 */
                try {
                    $this->pivotTable()->insert($pivotRow);
                } catch (QueryException $exception) {
                    $this->logger()->error($exception->getMessage());
                }
            }
        }
    }

    /**
     * Method is similar to link() except will update pivot columns of alread exists records and
     * unlink records not specified in argument. Do not forget to pre-populate pivot columns if you
     * using WHERE_PIVOT conditions.
     *
     * Examples:
     * $user->tags()->sync(1);
     * $user->tags()->sync($tag);
     * $user->tags()->sync([1, 2], ['approved' => true]);
     * $user->tags()->sync([
     *      1 => ['approved' => true],
     *      2 => ['approved' => false]
     * ]);
     *
     * Method will not affect state of pre-loaded data! Use reset() method to do that.
     *
     * @see link()
     * @param mixed $outer
     * @param array $pivotData
     */
    public function sync($outer, array $pivotData = [])
    {
        $pivotRows = $this->prepareRecords($outer, $pivotData);
        $linkedIDs = $this->linkedIDs([]);

        foreach ($pivotRows as $recordID => $pivotRow) {
            if (!in_array($recordID, $linkedIDs)) {
                try {
                    $this->pivotTable()->insert($pivotRow);
                } catch (QueryException $exception) {
                    $this->logger()->error($exception->getMessage());
                }
            } else {
                //Updating connection
                $this->pivotTable()->update(
                    $pivotRow,
                    $this->wherePivot($this->parentKey(), $recordID)
                )->run();
            }
        }

        foreach ($linkedIDs as $linkedID) {
            if (!isset($pivotRows[$linkedID])) {
                //Unlink
                $this->unlink($linkedID);
            }
        }
    }

    /**
     * Method used to unlink one of multiple associated ActiveRecords, method can accept id, list of
     * ids or instance of ActiveRecord. Method will return count of affected rows.
     *
     * Examples:
     * $user->tags()->unlink($tag);
     * $user->tags()->unlink([$tagA, $tagB]);
     * $user->tags()->unlink(1);
     * $user->tags()->unlink([1, 2, 3, 4]);
     *
     * Method will not affect state of pre-loaded data! Use reset() method to do that.
     *
     * @param mixed $recordID
     * @return int
     */
    public function unlink($recordID)
    {
        return $this->pivotTable()->delete($this->wherePivot(
            $this->parentKey(),
            array_keys($this->prepareRecords($recordID))
        ))->run();
    }

    /**
     * Unlink every associated record, method will return amount of affected rows. Method will not
     * affect state of pre-loaded data! Use reset() method to do that.
     *
     * @return int
     */
    public function unlinkAll()
    {
        return $this->pivotTable()->delete($this->wherePivot($this->parentKey(), null))->run();
    }

    /**
     * {@inheritdoc}
     */
    protected function createSelector()
    {
        //For Many-to-Many relation we have to use custom loader to parse data, this is ONLY for
        //this type of relation
        $loader = new ManyToManyLoader($this->orm, '', $this->definition);
        $selector = $loader->createSelector($this->parentRole())->where(
            $loader->pivotAlias() . '.' . $this->definition[RecordEntity::THOUGHT_INNER_KEY],
            $this->parentKey()
        );

        //Conditions
        if (!empty($this->definition[RecordEntity::WHERE_PIVOT])) {
            //Custom where pivot conditions
            $selector->onWhere($this->mountAlias(
                $loader->pivotAlias(),
                $this->definition[RecordEntity::WHERE_PIVOT]
            ));
        }

        if (!empty($this->definition[RecordEntity::WHERE])) {
            //Custom where pivot conditions
            $selector->where($this->mountAlias(
                $loader->getAlias(),
                $this->definition[RecordEntity::WHERE]
            ));
        }

        return $selector;
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(EntityInterface $record)
    {
        //Nothing to do, every fetched record should be already linked
        return $record;
    }

    /**
     * Helper method used to create valid WHERE query for deletes and updates in pivot table.
     *
     * @param mixed|array $innerKey
     * @param mixed|array $outerKey
     * @return array
     */
    protected function wherePivot($innerKey, $outerKey)
    {
        $query = [];
        if (!empty($this->definition[RecordEntity::MORPH_KEY])) {
            $query[$this->definition[RecordEntity::MORPH_KEY]] = $this->parentRole();
        }

        if (!empty($innerKey)) {
            $query[$this->definition[RecordEntity::THOUGHT_INNER_KEY]] = $innerKey;
        }

        if (!empty($this->definition[RecordEntity::WHERE_PIVOT])) {
            //Custom where pivot conditions
            $query = $query + $this->mountAlias(
                    $this->definition[RecordEntity::PIVOT_TABLE],
                    $this->definition[RecordEntity::WHERE_PIVOT]
                );
        }

        if (!empty($outerKey)) {
            $query[$this->definition[RecordEntity::THOUGHT_OUTER_KEY]] = is_array($outerKey)
                ? ['IN' => $outerKey]
                : $outerKey;
        }

        return $query;
    }

    /**
     * Return array of record ids associated with pivot columns.
     *
     * @param mixed $records
     * @param array $pivotData
     * @return array
     * @throws RelationException
     */
    protected function prepareRecords($records, array $pivotData = [])
    {
        if (is_scalar($records)) {
            return [
                $records => $this->pivotRow($records, $pivotData)
            ];
        }

        if (is_array($records)) {
            $result = [];
            foreach ($records as $key => $value) {
                if (is_scalar($value)) {
                    $result[$value] = $this->pivotRow($value, $pivotData);
                } else {
                    //Specified in key => pivotData format.
                    $result[$key] = $this->pivotRow($key, $value + $pivotData);
                }
            }

            return $result;
        }

        if (is_object($records) && get_class($records) != $this->getClass()) {
            throw new RelationException(
                "Relation can work only with instances of '{$this->getClass()}' record."
            );
        }

        $records = $records->getField($this->definition[RecordEntity::OUTER_KEY]);

        return [
            $records => $this->pivotRow($records, $pivotData)
        ];
    }

    /**
     * Create data set to be inserted/updated into pivot table.
     *
     * @param mixed $outerKey
     * @param array $pivotData
     * @return array
     */
    protected function pivotRow($outerKey, array $pivotData = [])
    {
        $data = [
            $this->definition[RecordEntity::THOUGHT_INNER_KEY] => $this->parentKey(),
            $this->definition[RecordEntity::THOUGHT_OUTER_KEY] => $outerKey
        ];

        if (!empty($this->definition[RecordEntity::MORPH_KEY])) {
            $data[$this->definition[RecordEntity::MORPH_KEY]] = $this->parentRole();
        }

        return $data + $pivotData;
    }

    /**
     * Get parent role. Role can be redefined by setRole method.
     *
     * @return string
     */
    protected function parentRole()
    {
        return !empty($this->parentRole) ? $this->parentRole : $this->parent->recordRole();
    }

    /**
     * Parent record inner key value.
     *
     * @return mixed
     */
    protected function parentKey()
    {
        return $this->parent->getField($this->definition[RecordEntity::INNER_KEY]);
    }

    /**
     * Instance of DBAL\Table associated with relation pivot table.
     *
     * @return Table
     */
    protected function pivotTable()
    {
        return $this->orm->database($this->definition[ORM::R_DATABASE])->table(
            $this->definition[RecordEntity::PIVOT_TABLE]
        );
    }

    /**
     * Fetch keys of linked records.
     *
     * @param array $outerIDs
     * @return array
     */
    protected function linkedIDs(array $outerIDs)
    {
        $selectQuery = $this->pivotTable()->where(
            $this->wherePivot($this->parentKey(), $outerIDs)
        );

        $selectQuery->columns($this->definition[RecordEntity::THOUGHT_OUTER_KEY]);

        $result = [];
        foreach ($selectQuery->run() as $row) {
            //Let's return outer key value as result
            $result[] = $row[$this->definition[RecordEntity::THOUGHT_OUTER_KEY]];
        }

        return $result;
    }

}