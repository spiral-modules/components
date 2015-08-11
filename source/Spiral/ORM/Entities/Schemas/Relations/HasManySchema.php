<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas\Relations;

use Spiral\ORM\Model;

/**
 * Declares simple has many relation. Relations like that used when parent model has many child with
 * [outer] key linked to value of [inner] key of parent mode. Relation allow specifying default
 * WHERE statement. Attention, WHERE statement will not be used in populating newly created model
 * fields.
 *
 * Example, [User has many Comments], user primary key is "id":
 * relation will create outer key "user_id" in "comments" table (or other table name), nullable by default
 * relation will create index on column "user_id" in "comments" table if allowed
 * relation will create foreign key "comments"."user_id" => "users"."id"
 */
class HasManySchema extends HasOneSchema
{
    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = Model::HAS_MANY;

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
        Model::CREATE_INDEXES    => true,
        //HasMany allow us to define default WHERE statement for relation in a simplified array form
        Model::WHERE             => []
    ];
}