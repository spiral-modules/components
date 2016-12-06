<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Schemas\Relations;

use Spiral\ORM\Entities\Schemas\RelationSchema;
use Spiral\ORM\RecordEntity;

/**
 * Declares simple has one relation. Relations like that used when parent record has one child with
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
    const RELATION_TYPE = RecordEntity::HAS_ONE;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Let's use parent record primary key as default inner key
        RecordEntity::INNER_KEY         => '{record:primaryKey}',
        //Outer key will be based on parent record role and inner key name
        RecordEntity::OUTER_KEY         => '{record:role}_{definition:innerKey}',
        //Set constraints (foreign keys) by default
        RecordEntity::CONSTRAINT        => true,
        //@link https://en.wikipedia.org/wiki/Foreign_key
        RecordEntity::CONSTRAINT_ACTION => 'CASCADE',
        //Relation allowed to create indexes in outer table
        RecordEntity::CREATE_INDEXES    => true,
        //Has one counted as not nullable by default
        RecordEntity::NULLABLE          => false,
        //Embedded relations are validated and saved with parent model and can accept values using
        //setFields
        RecordEntity::EMBEDDED_RELATION => true,
    ];

    /**
     * {@inheritdoc}
     */
    public function inverseRelation()
    {
        //Inverting definition
        $this->outerRecord()->addRelation($this->definition[RecordEntity::INVERSE], [
            RecordEntity::BELONGS_TO        => $this->record->getName(),
            RecordEntity::INNER_KEY         => $this->definition[RecordEntity::OUTER_KEY],
            RecordEntity::OUTER_KEY         => $this->definition[RecordEntity::INNER_KEY],
            RecordEntity::CONSTRAINT        => $this->definition[RecordEntity::CONSTRAINT],
            RecordEntity::CONSTRAINT_ACTION => $this->definition[RecordEntity::CONSTRAINT_ACTION],
            RecordEntity::CREATE_INDEXES    => $this->definition[RecordEntity::CREATE_INDEXES],
            RecordEntity::NULLABLE          => $this->definition[RecordEntity::NULLABLE],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildSchema()
    {
        //Outer (related) table schema
        $outerTable = $this->outerRecord()->tableSchema();

        //Outer key type must much inner key type
        $outerKey = $outerTable->column($this->getOuterKey());
        $outerKey->setType($this->getInnerKeyType());

        //We are only adding nullable flag if that was declared, if column were already nullable
        //this behaviour will be kept
        $outerKey->nullable($outerKey->isNullable() || $this->isNullable());

        if ($this->hasMorphKey()) {
            //Morph key will store outer record role name
            $morphKey = $outerTable->column($this->getMorphKey());

            //We have predefined morphed key size
            $morphKey->string(static::MORPH_COLUMN_SIZE);
            $morphKey->nullable($morphKey->isNullable() || $this->isNullable());

            //No need to perform any other table operations, usually it's done by polymorphic
            //schemas already
            return;
        }

        if ($this->isIndexed()) {
            //We can safely add index, it will not be created if outer record has passive schema
            $outerKey->index();
        }

        if (!$this->isConstrained()) {
            return;
        }

        //We are allowed to add foreign key, it will not be created if outer table has passive schema
        $foreignKey = $outerKey->references(
            $this->record->getTable(),
            $this->getInnerKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }
}
