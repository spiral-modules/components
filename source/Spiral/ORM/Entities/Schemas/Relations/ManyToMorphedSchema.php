<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Schemas\Relations;

use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Entities\Schemas\MorphedSchema;
use Spiral\ORM\Entities\Schemas\Relations\Traits\ColumnsTrait;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Model;

/**
 *
 *
 * @see ManyToManySchema
 */
class ManyToMorphedSchema extends MorphedSchema
{
    /**
     * Relation may create custom columns in pivot table using Model schema format.
     */
    use ColumnsTrait;

    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = Model::MANY_TO_MORPHED;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Association list between tables and roles, internal
        Model::MORPHED_ALIASES   => [],
        //Pivot table name will be generated based on singular relation name and _map postfix
        Model::PIVOT_TABLE       => '{name:singular}_map',
        //Inner key points to primary key of parent model by default
        Model::INNER_KEY         => '{model:primaryKey}',
        //By default, we are looking for primary key in our outer models, outer key must present
        //in every outer model and be consistent
        Model::OUTER_KEY         => '{outer:primaryKey}',
        //Linking pivot table and parent model
        Model::THOUGHT_INNER_KEY => '{model:roleName}_{definition:innerKet}',
        //Linking pivot table and outer models
        Model::THOUGHT_OUTER_KEY => '{name:singular}_{definition:outerKey}',
        //Declares what specific model pivot record linking to
        Model::MORPH_KEY         => '{name:singular}_type',
        //Set constraints in pivot table (foreign keys)
        Model::CONSTRAINT        => true,
        //@link https://en.wikipedia.org/wiki/Foreign_key
        Model::CONSTRAINT_ACTION => 'CASCADE',
        //Relation allowed to create indexes in pivot table
        Model::CREATE_INDEXES    => true,
        //Name of pivot table to be declared, default value is not stated as it will be generated
        //based on roles of inner and outer models
        Model::PIVOT_TABLE       => null,
        //Relation allowed to create pivot table
        Model::CREATE_PIVOT      => true,
        //Additional set of columns to be added into pivot table, you can use same column definition
        //type as you using for your models
        Model::PIVOT_COLUMNS     => [],
        //WHERE statement in a form of simplified array definition to be applied to pivot table
        //data
        Model::WHERE_PIVOT       => [],
        //WHERE statement to be applied for data in outer data while loading relation data
        //can not be inversed
        Model::WHERE             => []
    ];

    /**
     * {@inheritdoc}
     *
     * Relation will be inversed to every associated model.
     */
    public function inverseRelation()
    {
        //WHERE conditions can not be inversed
        foreach ($this->outerModels() as $record) {
            $record->addRelation(
                $this->definition[Model::INVERSE],
                [
                    Model::MANY_TO_MANY      => $this->model->getName(),
                    Model::PIVOT_TABLE       => $this->definition[Model::PIVOT_TABLE],
                    Model::OUTER_KEY         => $this->definition[Model::INNER_KEY],
                    Model::INNER_KEY         => $this->definition[Model::OUTER_KEY],
                    Model::THOUGHT_INNER_KEY => $this->definition[Model::THOUGHT_OUTER_KEY],
                    Model::THOUGHT_OUTER_KEY => $this->definition[Model::THOUGHT_INNER_KEY],
                    Model::MORPH_KEY         => $this->definition[Model::MORPH_KEY],
                    Model::CREATE_INDEXES    => $this->definition[Model::CREATE_INDEXES],
                    Model::CREATE_PIVOT      => $this->definition[Model::CREATE_PIVOT],
                    Model::PIVOT_COLUMNS     => $this->definition[Model::PIVOT_COLUMNS],
                    Model::WHERE_PIVOT       => $this->definition[Model::WHERE_PIVOT]
                ]
            );
        }
    }

    /**
     * Generate name of pivot table or fetch if from schema.
     *
     * @return string
     */
    public function getPivotTable()
    {
        return $this->definition[Model::PIVOT_TABLE];
    }

    /**
     * Instance of AbstractTable associated with relation pivot table.
     *
     * @return AbstractTable
     */
    public function pivotSchema()
    {
        return $this->builder->declareTable(
            $this->model->getDatabase(),
            $this->getPivotTable()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildSchema()
    {
        if (!$this->definition[Model::CREATE_PIVOT]) {
            //No pivot table creation were requested, noting really to do
            return;
        }

        $pivotTable = $this->pivotSchema();

        //Inner key points to our parent model
        $innerKey = $pivotTable->column($this->definition[Model::THOUGHT_INNER_KEY]);
        $innerKey->type($this->getInnerKey());

        if ($this->isIndexed()) {
            $innerKey->index();
        }

        //Morph key will store role name of outer models
        $morphKey = $pivotTable->column($this->getMorphKey());
        $morphKey->string(static::MORPH_COLUMN_SIZE);

        //Points to inner key of our outer models (outer key)
        $outerKey = $pivotTable->column($this->definition[Model::THOUGHT_OUTER_KEY]);
        $outerKey->type($this->getOuterKeyType());

        foreach ($this->definition[Model::PIVOT_COLUMNS] as $column => $definition) {
            //Addition pivot columns must be defined same way as in Model schema
            $this->castColumn($pivotTable->column($column), $definition);
        }

        //Complex index
        if ($this->isIndexed()) {
            //Complex index including 3 columns from pivot table
            $pivotTable->unique(
                $this->definition[Model::THOUGHT_INNER_KEY],
                $this->definition[Model::MORPH_KEY],
                $this->definition[Model::THOUGHT_OUTER_KEY]
            );
        }

        if ($this->isConstrained()) {
            $foreignKey = $innerKey->references(
                $this->model->getTable(),
                $this->model->getPrimaryKey()
            );

            $foreignKey->onDelete($this->getConstraintAction());
            $foreignKey->onUpdate($this->getConstraintAction());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();

        foreach ($this->outerModels() as $model) {
            if (!in_array($model->getRole(), $definition[Model::MORPHED_ALIASES])) {
                //Let's remember associations between tables and roles
                $definition[Model::MORPHED_ALIASES][$model->getTable()] = $model->getRole();
            }
        }

        //Let's include pivot table columns
        $definition[Model::PIVOT_COLUMNS] = [];
        foreach ($this->pivotSchema()->getColumns() as $column) {
            $definition[Model::PIVOT_COLUMNS][] = $column->getName();
        }

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function clarifyDefinition()
    {
        parent::clarifyDefinition();
        if (!$this->isSameDatabase()) {
            throw new RelationSchemaException(
                "Many-to-Many morphed relation can create relations ({$this}) "
                . "only to entities from same database."
            );
        }
    }
}