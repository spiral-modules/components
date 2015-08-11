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
use Spiral\ORM\Model;

/**
 * Declares that parent model belongs to some parent based on value in [inner] key. Basically this
 * relation is mirror copy of HasOne relation.
 *
 * BelongsTo relations inversion requires user to specify backward connection type (HAS_ONE or
 * HAS_MANY), inversion may look like (based on example below) ["posts", self::HAS_MANY] (create
 * HAS_MANY relation in User model under name "posts").
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
    const RELATION_TYPE = Model::BELONGS_TO;

    /**
     * {@inheritdoc}
     *
     * When relation states that model belongs to interface relation will be switched to
     * BelongsToMorphed.
     */
    const EQUIVALENT_RELATION = Model::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Outer key is primary key of related model by default
        Model::OUTER_KEY         => '{outer:primaryKey}',
        //Inner key will be based on singular name of relation and outer key name
        Model::INNER_KEY         => '{name:singular}_{definition:outerKey}',
        //Set constraints (foreign keys) by default
        Model::CONSTRAINT        => true,
        //@link https://en.wikipedia.org/wiki/Foreign_key
        Model::CONSTRAINT_ACTION => 'CASCADE',
        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        Model::NULLABLE          => true,
        //Relation allowed to create indexes in inner table
        Model::CREATE_INDEXES    => true
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
            !is_array($this->definition[Model::INVERSE])
            || !isset($this->definition[Model::INVERSE][1])
        ) {
            throw new RelationSchemaException(
                "Unable to revert BELONG_TO relation '{$this->model}'.'{$this}', " .
                "backward relation type is missing or invalid."
            );
        }

        //Inverting definition
        $this->outerModel()->addRelation(
            $this->definition[Model::INVERSE][1],
            [
                $this->definition[Model::INVERSE][0] => $this->model->getName(),
                Model::OUTER_KEY                     => $this->definition[Model::INNER_KEY],
                Model::INNER_KEY                     => $this->definition[Model::OUTER_KEY],
                Model::CONSTRAINT                    => $this->definition[Model::CONSTRAINT],
                Model::CONSTRAINT_ACTION             => $this->definition[Model::CONSTRAINT_ACTION],
                Model::NULLABLE                      => $this->definition[Model::NULLABLE]
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildSchema()
    {
        //We are going to modify table related to parent model
        $innerTable = $this->model->tableSchema();

        //Inner key type must match outer key type
        $innerKey = $innerTable->column($this->getInnerKey());
        $innerKey->type($this->getOuterKeyType());
        $innerKey->nullable($this->isNullable());

        if ($this->isIndexed()) {
            //We can safely add index, it will not be created if outer model has passive schema
            $innerKey->index();
        }

        if (!$this->isConstrained()) {
            return;
        }

        //We are allowed to add foreign key, it will not be created if outer table has passive schema
        $foreignKey = $innerKey->references(
            $this->outerModel()->getTable(),
            $this->getOuterKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }
}