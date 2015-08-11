<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas\Relations;

use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Entities\Schemas\Relations\Traits\ColumnsTrait;
use Spiral\ORM\Entities\Schemas\RelationSchema;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Model;

/**
 * ManyToMany relation declares that two models related to each other using pivot table data. Relation
 * allow to specify inner key (key in parent model), outer key (key in outer model), pivot table name,
 * names of pivot columns to store inner and outer key values and set of additional columns.
 * Relation allow specifying default WHERE statement for outer records and pivot table separately.
 *
 * Example (User related to many Tag models):
 * - relation will create pivot table named "tag_user_map" (if allowed), where table name generated
 *   based on roles of inner and outer tables sorted in ABC order (you can change name)
 * - relation will create pivot key named "user_id" related to User primary key
 * - relation will create pivot key named "tag_id" related to Tag primary key
 * - relation will create unique index on "user_id" and "tag_id" columns if allowed
 * - relation will create foreign key "tag_user_map"."user_id" => "users"."id" if allowed
 * - relation will create foreign key "tag_user_map"."tag_id" => "tags"."id" if allowed
 * - relation will create additional columns in pivot table if any requested
 */
class ManyToManySchema extends RelationSchema
{
    /**
     * Relation may create custom columns in pivot table using Model schema format.
     */
    use ColumnsTrait;

    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = Model::HAS_ONE;

    /**
     * {@inheritdoc}
     *
     * When relation states that relation defines connection to interface relation will be switched to
     * ManyToManyMorphed.
     */
    const EQUIVALENT_RELATION = Model::MANY_TO_MANY;

    /**
     * Default postfix for pivot tables.
     */
    const PIVOT_POSTFIX = '_map';

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Inner key of parent model will be used to fill "THOUGHT_INNER_KEY" in pivot table
        Model::INNER_KEY         => '{model:primaryKey}',
        //We are going to use primary key of outer table to fill "THOUGHT_OUTER_KEY" in pivot table
        //This is technically "inner" key of outer model, we will name it "outer key" for simplicity
        Model::OUTER_KEY         => '{outer:primaryKey}',
        //Name field where parent model inner key will be stored in pivot table, role + innerKey
        //by default
        Model::THOUGHT_INNER_KEY => '{model:role}_{definition:innerKey}',
        //Name field where inner key of outer model (outer key) will be stored in pivot table,
        //role + outerKey by default
        Model::THOUGHT_OUTER_KEY => '{outer:role}_{definition:outerKey}',
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
        Model::WHERE             => []
    ];

    /**
     * {@inheritdoc}
     */
    public function inverseRelation()
    {
        if ($this->outerModel()->hasRelation($this->definition[Model::INVERSE])) {
            //Already implemented by model itself, we may add warning here in future
            return;
        }

        //Many to many relation can be inversed pretty easily, we only have to swap inner keys
        //with outer keys
        $this->outerModel()->addRelation(
            $this->definition[Model::INVERSE],
            [
                Model::MANY_TO_MANY      => $this->model->getName(),
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
     * Generate name of pivot table or fetch if from schema.
     *
     * @return string
     */
    public function getPivotTable()
    {
        if (isset($this->definition[Model::PIVOT_TABLE])) {
            return $this->definition[Model::PIVOT_TABLE];
        }

        //Generating pivot table name
        $names = [$this->model->getRole(), $this->outerModel()->getRole()];
        asort($names);

        return join('_', $names) . static::PIVOT_POSTFIX;
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

        //Thought outer key points to inner key in outer model (outer key)
        $outerKey = $pivotTable->column($this->definition[Model::THOUGHT_OUTER_KEY]);
        $outerKey->type($this->getOuterKeyType());

        if ($this->hasMorphKey()) {
            //ManyToManyMorphed relation will cause creation set of ManyToMany relations
            //linking every possible morphed model with parent model
            $morphKey = $pivotTable->column($this->definition[Model::MORPH_KEY]);
            $morphKey->string(static::MORPH_COLUMN_SIZE);
        }

        //Thought inner key points to inner key in parent model
        $innerKey = $pivotTable->column($this->definition[Model::THOUGHT_INNER_KEY]);
        $innerKey->type($this->getInnerKeyType());

        foreach ($this->definition[Model::PIVOT_COLUMNS] as $column => $definition) {
            //Addition pivot columns must be defined same way as in Model schema
            $this->castColumn($pivotTable->column($column), $definition);
        }

        if (!$this->isConstrained() || $this->hasMorphKey()) {
            //Either not need to create constraint or it relation is polymorphic, we also
            //can't create indexes in this case
            return;
        }

        if ($this->isIndexed()) {
            //Unique index are added to pivot table keys, you can't link models multiple times
            //If you DO want to do that, please create necessary tables and indexes using migrations
            $pivotTable->unique(
                $this->definition[Model::THOUGHT_INNER_KEY],
                $this->definition[Model::THOUGHT_OUTER_KEY]
            );
        }

        //Inner pivot key = parent model inner key
        $foreignKey = $innerKey->references(
            $this->model->getTable(),
            $this->model->getPrimaryKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());

        //Outer pivot key = outer model inner key (outer key)
        $foreignKey = $outerKey->references(
            $this->outerModel()->getTable(),
            $this->outerModel()->getPrimaryKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }

    /**
     * {@inheritdoc}
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();

        $definition[Model::PIVOT_COLUMNS] = [];
        foreach ($this->pivotSchema()->getColumns() as $column) {
            //Let's include pivot table columns, it will help many to many loaded to map data correctly
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
        if (empty($this->definition[Model::PIVOT_TABLE])) {
            $this->definition[Model::PIVOT_TABLE] = $this->getPivotTable();
        }

        if (!$this->isSameDatabase()) {
            throw new RelationSchemaException(
                "Many-to-Many relation can create relations only to entities from same database."
            );
        }
    }
}