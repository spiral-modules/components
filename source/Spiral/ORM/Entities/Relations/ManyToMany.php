<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Relations;

use Spiral\Database\Entities\Table;
use Spiral\ORM\Entities\Loaders\ManyToManyLoader;
use Spiral\ORM\Entities\Relation;
use Spiral\ORM\Exceptions\RelationException;
use Spiral\ORM\Model;
use Spiral\ORM\ORM;

class ManyToMany extends Relation
{
    /**
     * Relation type, required to fetch model class from relation definition.
     */
    const RELATION_TYPE = Model::MANY_TO_MANY;

    /**
     * Indication that relation represent multiple models (HAS_MANY relations).
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
     * Count method will work with pivot table directly.
     *
     * @return int
     */
    public function count()
    {
        return $this->pivotTable()->where($this->wherePivot(
            $this->parentKey(),
            null
        ))->count();
    }

    /**
     * Check if Model(s) associated with this relation. Method can accept one id, array of ids,
     * or instance of ActiveRecord. In case of multiple ids provided method will return true only
     * if every model is linked to relation.
     *
     * Attention, WHERE_PIVOT will not be used by default, you must force it.
     *
     * Examples:
     * $user->tags()->has($tag);
     * $user->tags()->has([$tagA, $tagB]);
     * $user->tags()->has(1);
     * $user->tags()->has([1, 2, 3, 4]);
     *
     * @param mixed $modelID
     * @param bool  $wherePivot Use conditions specified by WHERE_PIVOT, disabled by default.
     * @return bool
     */
    public function has($modelID, $wherePivot = false)
    {
        $selectQuery = $this->pivotTable()->where($this->wherePivot(
            $this->parentKey(),
            $this->prepareIDs($modelID),
            $wherePivot
        ));

        //We can use hasEach methods there, but this is more optimal way
        return $selectQuery->count() == count($modelID);
    }

    /**
     * Return only list of outer keys which are linked.
     * Attention, WHERE_PIVOT will not be used by default, you must force it.
     *
     * Examples:
     * $user->tags()->hasEach($tag);
     * $user->tags()->hasEach([$tagA, $tagB]);
     * $user->tags()->hasEach(1);
     * $user->tags()->hasEach([1, 2, 3, 4]);
     *
     * @param mixed $modelIDs
     * @param bool  $wherePivot Use conditions specified by WHERE_PIVOT, disabled by default.
     * @return array
     */
    public function hasEach($modelIDs, $wherePivot = false)
    {
        $selectQuery = $this->pivotTable()->where($this->wherePivot(
            $this->parentKey(),
            $this->prepareIDs($modelIDs),
            $wherePivot
        ));

        $selectQuery->columns($this->definition[Model::THOUGHT_OUTER_KEY]);

        $result = [];
        foreach ($selectQuery->run() as $row) {
            //Let's return outer key value as result
            $result[] = $row[$this->definition[Model::THOUGHT_OUTER_KEY]];
        }

        return $result;
    }

    /**
     * Link or update link for one of multiple related records. You can pass pivotData as additional
     * argument or associate it with model id.
     *
     * Attention!
     * This method will not follow WHERE_PIVOT conditions, you WILL have to specify them manually.
     *
     * Examples:
     * $user->tags->link(1);
     * $user->tags->link($tag);
     * $user->tags->link([1, 2], ['approved' => true]);
     * $user->tags->link([
     *      1 => ['approved' => true],
     *      2 => ['approved' => false]
     * ]);
     *
     * If record already linked it will be updated with provided pivot data, if you disable it by
     * providing third argument as true.
     *
     * Method will not affect state of pre-loaded data! Use reset() method to do that.
     *
     * @param mixed $modelID
     * @param array $pivotData
     * @param bool  $linkOnly If true no updates will be performed.
     * @return int
     */
    public function link($modelID, array $pivotData = [], $linkOnly = false)
    {
        //I need different method here
        $modelID = $this->prepareIDs($modelID, $pivotRows, $pivotData);
        $existedIDs = $this->hasEach($modelID);

        $result = 0;
        foreach ($pivotRows as $modelID => $pivotRow) {
            if (in_array($modelID, $existedIDs)) {
                if (!$linkOnly) {
                    //We can update
                    $result += $this->pivotTable()->update(
                        $pivotRow,
                        $this->wherePivot($this->parentKey(), $modelID)
                    )->run();
                }
            } else {
                /**
                 * In future this statement should be optimized to use batchInsert in cases when
                 * set of columns for every record is the same.
                 */
                $this->pivotTable()->insert($pivotRow);
                $result++;
            }
        }

        return $result;
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
     * @param mixed $modelID
     * @return int
     */
    public function unlink($modelID)
    {
        return $this->pivotTable()->delete($this->wherePivot(
            $this->parentKey(),
            $this->prepareIDs($modelID),
            false
        ))->run();
    }

    /**
     * Unlink every associated record, method will return amount of affected rows. Method will unlink
     * only records matched WHERE_PIVOT by default. Set wherePivot to false to unlink every record.
     *
     * Method will not affect state of pre-loaded data! Use reset() method to do that.
     *
     * @param bool $wherePivot Use conditions specified by WHERE_PIVOT, enabled by default.
     * @return int
     */
    public function unlinkAll($wherePivot = true)
    {
        return $this->pivotTable()->delete($this->wherePivot(
            $this->parentKey(),
            null,
            $wherePivot
        ))->run();
    }

    /**
     * {@inheritdoc}
     */
    protected function createSelector()
    {
        //For Many-to-Many relation we have to use custom loader to parse data, this is ONLY for
        //this type of relation
        $loader = new ManyToManyLoader($this->orm, '', $this->definition);

        return $loader->createSelector($this->parentRole())->where(
            $loader->getPivotAlias() . '.' . $this->definition[Model::THOUGHT_INNER_KEY],
            $this->parentKey()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function mountRelation(Model $model)
    {
        //Nothing to do, every fetched model should be already linked
        return $model;
    }

    /**
     * Helper method used to create valid WHERE query for deletes and updates in pivot table.
     *
     * @param mixed|array $innerKey
     * @param mixed|array $outerKey
     * @param bool        $wherePivot Use conditions specified by WHERE_PIVOT, disabled by default.
     * @return array
     */
    protected function wherePivot($innerKey, $outerKey, $wherePivot = false)
    {
        $query = [];
        if (!empty($this->definition[Model::MORPH_KEY])) {
            $query[$this->definition[Model::MORPH_KEY]] = $this->parentRole();
        }

        if (!empty($innerKey)) {
            $query[$this->definition[Model::THOUGHT_INNER_KEY]] = $innerKey;
        }

        if ($wherePivot && !empty($this->definition[Model::WHERE_PIVOT])) {
            //Custom where pivot conditions
            $query = $query + $this->definition[Model::WHERE_PIVOT];
        }

        if (!empty($outerKey)) {
            $query[$this->definition[Model::THOUGHT_OUTER_KEY]] = is_array($outerKey)
                ? ['IN' => $outerKey]
                : $outerKey;
        }

        return $query;
    }

    /**
     * Helper method to fetch outer key value from provided list.
     *
     * @param mixed $modelID
     * @param array $pivotRows Automatically constructed pivot rows will be available here for insertion
     *                         or update.
     * @param array $pivotData
     * @return mixed
     * @throws RelationException
     */
    protected function prepareIDs($modelID, array &$pivotRows = null, array $pivotData = [])
    {
        if (is_scalar($modelID)) {
            $pivotRows = [$modelID => $this->pivotRow($modelID, $pivotData)];

            return $modelID;
        }

        if (is_array($modelID)) {
            $result = [];
            foreach ($modelID as $key => $value) {
                if (is_scalar($value)) {
                    $pivotRows[$value] = $this->pivotRow($value, $pivotData);
                    $result[] = $value;
                } else {
                    //Specified in key => pivotData format.
                    $pivotRows[$key] = $this->pivotRow($key, $value + $pivotData);
                    $result[] = $key;
                }
            }

            return $result;
        }

        if (is_object($modelID) && get_class($modelID) != $this->getClass()) {
            throw new RelationException(
                "Relation can work only with instances of '{$this->getClass()}' model."
            );
        }

        $modelID = $modelID->getField($this->definition[Model::OUTER_KEY]);

        //To be inserted later
        $pivotRows = [$modelID => $this->pivotRow($modelID, $pivotData)];

        return $modelID;
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
            $this->definition[Model::THOUGHT_INNER_KEY] => $this->parentKey(),
            $this->definition[Model::THOUGHT_OUTER_KEY] => $outerKey
        ];

        if (!empty($this->definition[Model::MORPH_KEY])) {
            $data[$this->definition[Model::MORPH_KEY]] = $this->parentRole();
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
        return !empty($this->parentRole) ? $this->parentRole : $this->parent->modelRole();
    }

    /**
     * Parent model inner key value.
     *
     * @return mixed
     */
    protected function parentKey()
    {
        return $this->parent->getField($this->definition[Model::INNER_KEY]);
    }

    /**
     * Instance of DBAL\Table associated with relation pivot table.
     *
     * @return Table
     */
    protected function pivotTable()
    {
        return $this->orm->dbalDatabase($this->definition[ORM::R_DATABASE])->table(
            $this->definition[Model::PIVOT_TABLE]
        );
    }
}