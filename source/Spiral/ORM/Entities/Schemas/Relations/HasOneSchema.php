<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas\Relations;

use Spiral\ORM\Entities\Schemas\RelationSchema;
use Spiral\ORM\Model;

/**
 * Declares simple has one relation. Relations like used when parent model has one child with
 * [outer] key linked to value of [inner] key of parent mode.
 *
 * Example, [User has one Profile], user primary key is "id":
 * relation will create outer key "user_id" in "profiles" table (or other table name), nullable by default
 * relation will create index on column "user_id" in "profiles" table if allowed
 * relation will create foreign key "profiles"."user_id" => "users"."id"
 */
class HasOneSchema extends RelationSchema
{
    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = Model::HAS_ONE;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Let's use parent model primary key as default inner key
        Model::INNER_KEY         => '{model:primaryKey}',
        //Outer key will be based on parent model role and inner key name
        Model::OUTER_KEY         => '{model:role}_{definition:innerKey}',
        //Set constraints (foreign keys) by default
        Model::CONSTRAINT        => true,
        //@link https://en.wikipedia.org/wiki/Foreign_key
        Model::CONSTRAINT_ACTION => 'CASCADE',
        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        Model::NULLABLE          => true,
        //Relation allowed to create indexes in outer table
        Model::CREATE_INDEXES    => true
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

        //Reverting definition
        $this->outerModel()->addRelation(
            $this->definition[Model::INVERSE],
            [
                Model::BELONGS_TO        => $this->model->getName(),
                Model::INNER_KEY         => $this->definition[Model::OUTER_KEY],
                Model::OUTER_KEY         => $this->definition[Model::INNER_KEY],
                Model::CONSTRAINT        => $this->definition[Model::CONSTRAINT],
                Model::CONSTRAINT_ACTION => $this->definition[Model::CONSTRAINT_ACTION],
                Model::NULLABLE          => $this->definition[Model::NULLABLE]
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildSchema()
    {
        //Outer (related) table schema
        $outerTable = $this->outerModel()->tableSchema();

        /**
         * Outer key creation, will include type casting and nullable flag.
         */
        $outerKey = $outerTable->column($this->getOuterKey());

        //Outer key type must much inner key type
        $outerKey->type($this->getInnerKeyType());
        $outerKey->nullable($this->isNullable());

        if ($this->hasMorphKey()) {
            //We should not create any indexes or constrains as outer model can be related
            //to multiple parents
            return;
        }

        if ($this->isIndexed()) {
            //We can safely add index, it will not be created if outer model has passive schema
            $outerKey->index();
        }

        if (!$this->isConstrained()) {
            return;
        }

        //We are allowed to add foreign key, it will not be created if outer table has passive schema
        $foreignKey = $outerKey->references(
            $this->model->getTable(),
            $this->getInnerKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }
}