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
 * Declares simple has one relation. Relations like that used when parent model has one child with
 * [outer] key linked to value of [inner] key of parent mode.
 *
 * Example, [User has one Profile], user primary key is "id":
 * - relation will create outer key "user_id" in "profiles" table (or other table name), nullable
 *   by default
 * - relation will create index on column "user_id" in "profiles" table if allowed
 * - relation will create foreign key "profiles"."user_id" => "users"."id" if allowed
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
        //Relation allowed to create indexes in outer table
        Model::CREATE_INDEXES    => true,
        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        Model::NULLABLE          => true
    ];

    /**
     * {@inheritdoc}
     */
    public function inverseRelation()
    {
        //Inverting definition
        $this->outerModel()->addRelation(
            $this->definition[Model::INVERSE],
            [
                Model::BELONGS_TO        => $this->model->getName(),
                Model::INNER_KEY         => $this->definition[Model::OUTER_KEY],
                Model::OUTER_KEY         => $this->definition[Model::INNER_KEY],
                Model::CONSTRAINT        => $this->definition[Model::CONSTRAINT],
                Model::CONSTRAINT_ACTION => $this->definition[Model::CONSTRAINT_ACTION],
                Model::CREATE_INDEXES    => $this->definition[Model::CREATE_INDEXES],
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

        //Outer key type must much inner key type
        $outerKey = $outerTable->column($this->getOuterKey());
        $outerKey->type($this->getInnerKeyType());

        //We are only adding nullable flag if that was declared, if column were already nullable
        //this behaviour will be kept
        $outerKey->nullable($outerKey->isNullable() || $this->isNullable());

        if ($this->hasMorphKey()) {
            //Morph key will store outer model role name
            $morphKey = $outerTable->column($this->getMorphKey());

            //We have predefined morphed key size
            $morphKey->string(static::MORPH_COLUMN_SIZE);
            $morphKey->nullable($morphKey->isNullable() || $this->isNullable());

            //No need to perform any other table operations, usually it's done by polymorphic
            //schemas already
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