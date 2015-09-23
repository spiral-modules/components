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
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

/**
 * Declares that parent record belongs to some parent based on value in [inner] key. Basically this
 * relation is mirror copy of HasOne relation.
 *
 * BelongsTo relations inversion requires user to specify backward connection type (HAS_ONE or
 * HAS_MANY), inversion may look like (based on example below) ["posts", self::HAS_MANY] (create
 * HAS_MANY relation in User record under name "posts").
 *
 * Example, [Post has one User, relation name "author"], user primary key is "id":
 * - relation will create inner key "author_id" in "posts" table (or other table name), nullable by
 *   default
 * - relation will create index on column "author_id" in "posts" table if allowed
 * - relation will create foreign key "posts"."author_id" => "users"."id" if allowed
 */
class BelongsToSchema extends RelationSchema
{
    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = RecordEntity::BELONGS_TO;

    /**
     * {@inheritdoc}
     *
     * When relation states that record belongs to interface relation will be switched to
     * BelongsToMorphed.
     */
    const EQUIVALENT_RELATION = RecordEntity::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Outer key is primary key of related record by default
        RecordEntity::OUTER_KEY         => '{outer:primaryKey}',
        //Inner key will be based on singular name of relation and outer key name
        RecordEntity::INNER_KEY         => '{name:singular}_{definition:outerKey}',
        //Set constraints (foreign keys) by default
        RecordEntity::CONSTRAINT        => true,
        //@link https://en.wikipedia.org/wiki/Foreign_key
        RecordEntity::CONSTRAINT_ACTION => 'CASCADE',
        //Relation allowed to create indexes in inner table
        RecordEntity::CREATE_INDEXES    => true,
        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        RecordEntity::NULLABLE          => true
    ];

    /**
     * {@inheritdoc}
     */
    public function inverseRelation()
    {
        /**
         * Unfortunately BelongsTo relation can not be inversed without specifying backward relation
         * type which can be either HAS_ONE or HAS_MANY.
         */
        if (
            !is_array($this->definition[RecordEntity::INVERSE])
            || !isset($this->definition[RecordEntity::INVERSE][1])
        ) {
            throw new RelationSchemaException(
                "Unable to revert BELONG_TO relation '{$this->record}'.'{$this}', " .
                "backward relation type is missing or invalid."
            );
        }

        //Inverting definition
        $this->outerRecord()->addRelation(
            $this->definition[RecordEntity::INVERSE][1],
            [
                $this->definition[RecordEntity::INVERSE][0] => $this->record->getName(),
                RecordEntity::OUTER_KEY                     => $this->definition[RecordEntity::INNER_KEY],
                RecordEntity::INNER_KEY                     => $this->definition[RecordEntity::OUTER_KEY],
                RecordEntity::CONSTRAINT                    => $this->definition[RecordEntity::CONSTRAINT],
                RecordEntity::CONSTRAINT_ACTION             => $this->definition[RecordEntity::CONSTRAINT_ACTION],
                RecordEntity::CREATE_INDEXES                => $this->definition[RecordEntity::CREATE_INDEXES],
                RecordEntity::NULLABLE                      => $this->definition[RecordEntity::NULLABLE]
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildSchema()
    {
        //We are going to modify table related to parent record
        $innerTable = $this->record->tableSchema();

        //Inner key type must match outer key type
        $innerKey = $innerTable->column($this->getInnerKey());
        $innerKey->setType($this->getOuterKeyType());

        //We are only adding nullable flag if that was declared, if column were already nullable
        //this behaviour will be kept
        $innerKey->nullable($innerKey->isNullable() || $this->isNullable());

        if ($this->isIndexed()) {
            //We can safely add index, it will not be created if outer record has passive schema
            $innerKey->index();
        }

        if (!$this->isConstrained()) {
            return;
        }

        //We are allowed to add foreign key, it will not be created if outer table has passive schema
        $foreignKey = $innerKey->references(
            $this->outerRecord()->getTable(),
            $this->getOuterKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }

    /**
     * Normalize schema definition into light cachable form.
     *
     * @return array
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();

        if ($this->getOuterKey() == $this->outerRecord()->getPrimaryKey()) {
            //Linked using primary key
            $definition[ORM::M_PRIMARY_KEY] = $this->getOuterKey();
        }

        return $definition;
    }
}