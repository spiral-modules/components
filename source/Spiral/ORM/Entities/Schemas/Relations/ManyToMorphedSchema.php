<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Schemas\Relations;

use Spiral\Database\Schemas\AbstractTable;
use Spiral\ORM\Model;
use Spiral\ORM\ORMException;
use Spiral\ORM\Schemas\MorphedRelationSchema;

class ManyTo80-MorphedSchema extends MorphedRelationSchema
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Model::MANY_TO_MORPHED;

    /**
     * Default definition parameters, will be filled if parameter skipped from definition by user.
     *
     * @invisible
     * @var array
     */
    protected $defaultDefinition = [
        Model::MORPHED_ALIASES   => [],
        Model::PIVOT_TABLE       => '{name:singular}_map',
        Model::INNER_KEY         => '{record:primaryKey}',
        Model::OUTER_KEY         => '{outer:primaryKey}',
        Model::THOUGHT_INNER_KEY => '{record:roleName}_{definition:INNER_KEY}',
        Model::THOUGHT_OUTER_KEY => '{name:singular}_{definition:OUTER_KEY}',
        Model::MORPH_KEY         => '{name:singular}_type',
        Model::CONSTRAINT        => true,
        Model::CONSTRAINT_ACTION => 'CASCADE',
        Model::CREATE_PIVOT      => true,
        Model::PIVOT_COLUMNS     => [],
        Model::WHERE_PIVOT       => [],
        Model::WHERE             => []
    ];

    /**
     * Inverse relation.
     *
     * @throws ORMException
     */
    public function inverseRelation()
    {
        foreach ($this->getOuterModels() as $record)
        {
            $record->addRelation(
                $this->definition[Model::INVERSE],
                [
                    Model::MANY_TO_MANY      => $this->model->getClass(),
                    Model::PIVOT_TABLE       => $this->definition[Model::PIVOT_TABLE],
                    Model::OUTER_KEY         => $this->definition[Model::INNER_KEY],
                    Model::INNER_KEY         => $this->definition[Model::OUTER_KEY],
                    Model::THOUGHT_INNER_KEY => $this->definition[Model::THOUGHT_OUTER_KEY],
                    Model::THOUGHT_OUTER_KEY => $this->definition[Model::THOUGHT_INNER_KEY],
                    Model::MORPH_KEY         => $this->definition[Model::MORPH_KEY],
                    Model::CREATE_PIVOT      => $this->definition[Model::CREATE_PIVOT],
                    Model::PIVOT_COLUMNS     => $this->definition[Model::PIVOT_COLUMNS],
                    Model::WHERE_PIVOT       => $this->definition[Model::WHERE_PIVOT]
                ]
            );
        }
    }

    /**
     * Mount default values to relation definition.
     */
    protected function clarifyDefinition()
    {
        parent::clarifyDefinition();
        if ($this->isOuterDatabase())
        {
            throw new ORMException("Many-to-Many relation can not point to outer database data.");
        }
    }

    /**
     * Pivot table name.
     *
     * @return string
     */
    public function getPivotTable()
    {
        return $this->definition[Model::PIVOT_TABLE];
    }

    /**
     * Pivot table schema.
     *
     * @return AbstractTable
     */
    public function getPivotSchema()
    {
        return $this->builder->table($this->model->getDatabase(), $this->getPivotTable());
    }

    /**
     * Create all required relation columns, indexes and constraints.
     */
    public function buildSchema()
    {
        if (!$this->getOuterModels() || !$this->definition[Model::CREATE_PIVOT])
        {
            //No targets found, no need to generate anything
            return;
        }

        $pivotTable = $this->getPivotSchema();

        $localKey = $pivotTable->column($this->definition[Model::THOUGHT_INNER_KEY]);
        $localKey->type($this->innerKeyType());
        $localKey->index();

        $morphKey = $pivotTable->column($this->getMorphKey());
        $morphKey->string(static::MORPH_COLUMN_SIZE);

        $outerKey = $pivotTable->column($this->definition[Model::THOUGHT_OUTER_KEY]);
        $outerKey->type($this->outerKeyType());

        //Additional pivot columns
        foreach ($this->definition[Model::PIVOT_COLUMNS] as $column => $definition)
        {
            $this->castColumn($pivotTable->column($column), $definition);
        }

        //Complex index
        $pivotTable->unique(
            $this->definition[Model::THOUGHT_INNER_KEY],
            $this->definition[Model::MORPH_KEY],
            $this->definition[Model::THOUGHT_OUTER_KEY]
        );

        if ($this->isConstrained())
        {
            $foreignKey = $localKey->foreign(
                $this->model->getTable(),
                $this->model->getPrimaryKey()
            );

            $foreignKey->onDelete($this->getConstraintAction());
            $foreignKey->onUpdate($this->getConstraintAction());
        }
    }

    /**
     * Normalize relation options.
     *
     * @return array
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();

        //Let's fill morphed aliases
        foreach ($this->getOuterModels() as $model)
        {
            if (!in_array($model->getRoleName(), $definition[Model::MORPHED_ALIASES]))
            {
                $definition[Model::MORPHED_ALIASES][$model->getTable()] = $model->getRoleName();
            }
        }

        //Let's include pivot table columns
        $definition[Model::PIVOT_COLUMNS] = [];
        foreach ($this->getPivotSchema()->getColumns() as $column)
        {
            $definition[Model::PIVOT_COLUMNS][] = $column->getName();
        }

        return $definition;
    }
}