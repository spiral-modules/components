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
use Spiral\ORM\Schemas\RelationSchema;

class ManyToManySchema extends RelationSchema
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Model::MANY_TO_MANY;

    /**
     * Equivalent relationship resolved based on definition and not schema, usually polymorphic.
     */
    const EQUIVALENT_RELATION = Model::MANY_TO_MORPHED;

    /**
     * Default definition parameters, will be filled if parameter skipped from definition by user.
     *
     * @invisible
     * @var array
     */
    protected $defaultDefinition = [
        Model::INNER_KEY         => '{record:primaryKey}',
        Model::OUTER_KEY         => '{outer:primaryKey}',
        Model::THOUGHT_INNER_KEY => '{record:roleName}_{definition:INNER_KEY}',
        Model::THOUGHT_OUTER_KEY => '{outer:roleName}_{definition:OUTER_KEY}',
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
        $this->outerModel()->addRelation(
            $this->definition[Model::INVERSE],
            [
                Model::MANY_TO_MANY      => $this->model->getClass(),
                Model::PIVOT_TABLE       => $this->definition[Model::PIVOT_TABLE],
                Model::OUTER_KEY         => $this->definition[Model::INNER_KEY],
                Model::INNER_KEY         => $this->definition[Model::OUTER_KEY],
                Model::THOUGHT_INNER_KEY => $this->definition[Model::THOUGHT_OUTER_KEY],
                Model::THOUGHT_OUTER_KEY => $this->definition[Model::THOUGHT_INNER_KEY],
                Model::CONSTRAINT        => $this->definition[Model::CONSTRAINT],
                Model::CONSTRAINT_ACTION => $this->definition[Model::CONSTRAINT_ACTION],
                Model::CREATE_PIVOT      => $this->definition[Model::CREATE_PIVOT],
                Model::PIVOT_COLUMNS     => $this->definition[Model::PIVOT_COLUMNS]
            ]
        );
    }

    /**
     * Mount default values to relation definition.
     */
    protected function clarifyDefinition()
    {
        parent::clarifyDefinition();
        if (empty($this->definition[Model::PIVOT_TABLE]))
        {
            $this->definition[Model::PIVOT_TABLE] = $this->getPivotTable();
        }

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
        if (isset($this->definition[Model::PIVOT_TABLE]))
        {
            return $this->definition[Model::PIVOT_TABLE];
        }

        //Generating pivot table name
        $names = [$this->model->getRoleName(), $this->outerModel()->getRoleName()];
        asort($names);

        return join('_', $names) . '_map';
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
        if (!$this->definition[Model::CREATE_PIVOT])
        {
            //We are working purely with pivot table in this relation
            return;
        }

        $pivotTable = $this->getPivotSchema();

        $outerKey = $pivotTable->column($this->definition[Model::THOUGHT_OUTER_KEY]);
        $outerKey->type($this->outerKeyType());

        if (!empty($this->definition[Model::MORPH_KEY]))
        {
            $morphKey = $pivotTable->column($this->definition[Model::MORPH_KEY]);
            $morphKey->string(static::MORPH_COLUMN_SIZE);
        }

        $innerKey = $pivotTable->column($this->definition[Model::THOUGHT_INNER_KEY]);
        $innerKey->type($this->innerKeyType());

        //Additional pivot columns
        foreach ($this->definition[Model::PIVOT_COLUMNS] as $column => $definition)
        {
            $this->castColumn($pivotTable->column($column), $definition);
        }

        if (!$this->isConstrained() || !empty($this->definition[Model::MORPH_KEY]))
        {
            //Either not need to create constraint or it was created in polymorphic relation
            return;
        }

        //Complex index
        $pivotTable->unique(
            $this->definition[Model::THOUGHT_INNER_KEY],
            $this->definition[Model::THOUGHT_OUTER_KEY]
        );

        $foreignKey = $innerKey->foreign(
            $this->model->getTable(),
            $this->model->getPrimaryKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());

        $foreignKey = $outerKey->foreign(
            $this->outerModel()->getTable(),
            $this->outerModel()->getPrimaryKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }

    /**
     * Normalize relation options.
     *
     * @return array
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();

        //Let's include pivot table columns
        $definition[Model::PIVOT_COLUMNS] = [];
        foreach ($this->getPivotSchema()->getColumns() as $column)
        {
            $definition[Model::PIVOT_COLUMNS][] = $column->getName();
        }

        return $definition;
    }
}