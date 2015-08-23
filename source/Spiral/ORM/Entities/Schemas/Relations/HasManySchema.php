<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas\Relations;

use Spiral\ORM\Record;

/**
 * Declares simple has many relation. Relations like that used when parent record has many child
 * with
 * [outer] key linked to value of [inner] key of parent mode. Relation allow specifying default
 * WHERE statement. Attention, WHERE statement will not be used in populating newly created record
 * fields.
 *
 * Example, [User has many Comments], user primary key is "id":
 * - relation will create outer key "user_id" in "comments" table (or other table name), nullable
 *   by default
 * - relation will create index on column "user_id" in "comments" table if allowed
 * - relation will create foreign key "comments"."user_id" => "users"."id" if allowed
 */
class HasManySchema extends HasOneSchema
{
    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = Record::HAS_MANY;

    /**
     * Relation represent multiple records.
     */
    const MULTIPLE = true;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //Let's use parent record primary key as default inner key
        Record::INNER_KEY         => '{record:primaryKey}',
        //Outer key will be based on parent record role and inner key name
        Record::OUTER_KEY         => '{record:role}_{definition:innerKey}',
        //Set constraints (foreign keys) by default
        Record::CONSTRAINT        => true,
        //@link https://en.wikipedia.org/wiki/Foreign_key
        Record::CONSTRAINT_ACTION => 'CASCADE',
        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        Record::NULLABLE          => true,
        //Relation allowed to create indexes in outer table
        Record::CREATE_INDEXES    => true,
        //HasMany allow us to define default WHERE statement for relation in a simplified array form
        Record::WHERE             => []
    ];
}