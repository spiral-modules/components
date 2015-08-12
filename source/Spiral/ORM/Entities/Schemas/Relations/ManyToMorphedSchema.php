<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas\Relations;

use Doctrine\Common\Inflector\Inflector;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Entities\Schemas\MorphedSchema;
use Spiral\ORM\Entities\Schemas\Relations\Traits\ColumnsTrait;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Model;
use Spiral\ORM\ORM;

/**
 * ManyToMorphed relation declares relation between parent model and set of outer models joined by
 * common interface. Relation allow to specify inner key (key in parent model), outer key (key in
 * outer models), morph key, pivot table name, names of pivot columns to store inner and outer key
 * values and set of additional columns. Relation DOES NOT to specify WHERE statement for outer
 * records. However you can specify where conditions for PIVOT table.
 *
 * You can declare this relation using same syntax as for ManyToMany except your target class
 * must be an interface.
 *
 * Attention, be very careful using morphing relations, you must know what you doing!
 * Attention #2, relation like that can not be preloaded!
 *
 * Example [Tag related to many TaggableInterface], relation name "tagged", relation requested to be
 * inversed using name "tags":
 * - relation will walk should every model implementing TaggableInterface to collect name and
 *   type of outer keys, if outer key is not consistent across models implementing this interface
 *   an exception will be raised, let's say that outer key is "id" in every model
 * - relation will create pivot table named "tagged_map" (if allowed), where table name generated
 *   based on relation name (you can change name)
 * - relation will create pivot key named "tag_ud" related to Tag primary key
 * - relation will create pivot key named "tagged_id" related to primary key of outer models,
 *   singular relation name used to generate key like that
 * - relation will create pivot key named "tagged_type" to store role of outer model
 * - relation will create unique index on "tag_id", "tagged_id" and "tagged_type" columns if allowed
 * - relation will create additional columns in pivot table if any requested
 *
 * Using in models:
 * You can use inversed relation as usual ManyToMany, however in Tag model relation access will be
 * little bit more complex - every linked model will create inner ManyToMany relation:
 * $tag->tagged->users->count(); //Where "users" is plural form of one outer models
 *
 * You can defined your own inner relation names by using MORPHED_ALIASES option when defining
 * relation.
 *
 * @see BelongsToMorhedSchema
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
        Model::THOUGHT_INNER_KEY => '{model:role}_{definition:innerKey}',
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
        //Relation allowed to create pivot table
        Model::CREATE_PIVOT      => true,
        //Additional set of columns to be added into pivot table, you can use same column definition
        //type as you using for your models
        Model::PIVOT_COLUMNS     => [],
        //WHERE statement in a form of simplified array definition to be applied to pivot table
        //data
        Model::WHERE_PIVOT       => []
    ];

    /**
     * {@inheritdoc}
     *
     * Relation will be inversed to every associated model.
     */
    public function inverseRelation()
    {
        //WHERE conditions can not be inversed
        foreach ($this->outerModels() as $model) {
            if (!$model->hasRelation($this->definition[Model::INVERSE])) {
                $model->addRelation(
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
        $innerKey->type($this->getInnerKeyType());

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
                $plural = Inflector::pluralize($model->getRole());
                $definition[Model::MORPHED_ALIASES][$plural] = $model->getRole();
            }

            //We must include pivot table database into data for easier access
            $definition[ORM::R_DATABASE] = $model->getDatabase();
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