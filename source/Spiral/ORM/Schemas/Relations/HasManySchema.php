<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Record;
use Spiral\ORM\Schemas\Relations\Traits\TablesTrait;
use Spiral\ORM\Schemas\Relations\Traits\TypecastTrait;
use Spiral\ORM\Schemas\SchemaBuilder;

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
class HasManySchema extends AbstractSchema
{
    use TablesTrait, TypecastTrait;

    /**
     * {@inheritdoc}
     */
    const OPTIONS_TEMPLATE = [
        //Let's use parent record primary key as default inner key
        Record::INNER_KEY         => '{source:primaryKey}',

        //Outer key will be based on parent record role and inner key name
        Record::OUTER_KEY         => '{source:role}_{option:innerKey}',

        //Set constraints (foreign keys) by default
        Record::CREATE_CONSTRAINT => true,

        //@link https://en.wikipedia.org/wiki/Foreign_key
        Record::CONSTRAINT_ACTION => 'CASCADE',

        //We are going to make all relations nullable by default, so we can add fields to existed
        //tables without raising an exceptions
        Record::NULLABLE          => true,

        //Relation allowed to create indexes in outer table
        Record::CREATE_INDEXES    => true,

        //HasMany allow us to define default WHERE statement for relation in a simplified array form
        Record::WHERE             => [],
    ];

    /**
     * {@inheritdoc}
     */
    public function declareTables(SchemaBuilder $builder): array
    {
        $source = $this->sourceTable($builder);
        $target = $this->targetTable($builder);

        //Column to be used as outer key
        $outerKey = $target->column($this->option(Record::OUTER_KEY));

        if (!$source->hasColumn($this->option(Record::INNER_KEY))) {
            throw new RelationSchemaException(sprintf("Inner key '%s'.'%s' (%s) does not exists",
                $source->getName(),
                $this->option(Record::INNER_KEY),
                $this->definition->getName()
            ));
        }

        //Column to be used as inner key
        $innerKey = $source->column($this->option(Record::INNER_KEY));

        //Syncing types
        $outerKey->setType($this->resolveType($innerKey));

        //If nullable
        $outerKey->nullable($this->option(Record::NULLABLE));

        //Do we need indexes?
        if ($this->option(Record::CREATE_INDEXES)) {
            $target->index([$outerKey->getName()]);
        }

        if ($this->option(Record::CREATE_CONSTRAINT)) {
            $foreignKey = $target->foreign($outerKey->getName())->references(
                $source->getName(),
                $innerKey->getName()
            );

            $foreignKey->onDelete($this->option(Record::CONSTRAINT_ACTION));
            $foreignKey->onUpdate($this->option(Record::CONSTRAINT_ACTION));
        }

        return [$target];
    }
}