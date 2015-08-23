<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas\Relations;

use Spiral\ORM\Entities\Schemas\MorphedSchema;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\Record;

/**
 * BelongsToMorphed are almost identical to BelongsTo except it parent Record defined by role value
 * stored in [morph key] and parent key in [inner key].
 *
 * You can define BelongsToMorphed relation using syntax for BelongsTo but declaring outer class
 * as interface, meaning you should not only declare inversed relation name, but also it's type -
 * HAS_ONE or HAS_MANY.
 *
 * Example: 'parent' => [self::BELONGS_TO => 'Records\CommentableInterface']
 *
 * Attention, be very careful using morphing relations, you must know what you doing!
 * Attention #2, relation like that can not be preloaded!
 *
 * Example, [Comment can belong to any CommentableInterface record], relation name "parent",
 * relation requested to be inversed into HAS_MANY "comments":
 * - relation will walk should every record implementing CommentableInterface to collect name and
 *   type of outer keys, if outer key is not consistent across records implementing this interface
 *   an exception will be raised, let's say that outer key is "id" in every record
 * - relation will create inner key "parent_id" in "comments" table (or other table name), nullable
 *   by default
 * - relation will create "parent_type" morph key in "comments" table, nullable by default
 * - relation will create complex index index on columns "parent_id" and "parent_type" in
 * "comments"
 *   table if allowed
 * - due relation is inversable every record implementing CommentableInterface will receive
 * HAS_MANY
 *   relation "comments" pointing to Comment record using record role value
 *
 * @see BelongsToSchema
 */
class BelongsToMorphedSchema extends MorphedSchema
{
    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = Record::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //By default, we are looking for primary key in our outer records, outer key must present
        //in every outer record and be consistent
        Record::OUTER_KEY      => '{outer:primaryKey}',
        //Inner key name will be created based on singular relation name and outer key name
        Record::INNER_KEY      => '{name:singular}_{definition:outerKey}',
        //Morph key created based on singular relation name and postfix _type
        Record::MORPH_KEY      => '{name:singular}_type',
        //Relation allowed to create indexes in pivot table
        Record::CREATE_INDEXES => true,
        //Relation is nullable by default
        Record::NULLABLE       => true
    ];

    /**
     * {@inheritdoc}
     *
     * Relation will be inversed to every associated record.
     */
    public function inverseRelation()
    {
        //Same logic as in BelongsTo
        if (
            !is_array($this->definition[Record::INVERSE])
            || !isset($this->definition[Record::INVERSE][1])
        ) {
            throw new RelationSchemaException(
                "Unable to revert BELONG_TO_MORPHED relation '{$this->record}'.'{$this}', " .
                "backward relation type is missing or invalid."
            );
        }

        //We are going to inverse relation to every outer record
        $inversed = $this->definition[Record::INVERSE];
        foreach ($this->outerRecords() as $record) {
            if (!$record->hasRelation($inversed[1])) {
                $record->addRelation(
                    $inversed[1],
                    [
                        $inversed[0]      => $this->record->getName(),
                        Record::OUTER_KEY => $this->definition[Record::INNER_KEY],
                        Record::INNER_KEY => $this->definition[Record::OUTER_KEY],
                        Record::MORPH_KEY => $this->definition[Record::MORPH_KEY],
                        Record::NULLABLE  => $this->definition[Record::NULLABLE]
                    ]
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildSchema()
    {
        //Inner (parent) record table
        $innerSchema = $this->record->tableSchema();

        //Morph key contains parent role name
        $morphKey = $innerSchema->column($this->getMorphKey());

        //We have predefined morphed key size
        $morphKey->string(static::MORPH_COLUMN_SIZE);
        $morphKey->nullable($morphKey->isNullable() || $this->isNullable());

        //Points to inner key of outer records (outer key)
        $innerKey = $innerSchema->column($this->getInnerKey());
        $innerKey->type($this->getOuterKeyType());
        $innerKey->nullable($innerKey->isNullable() || $this->isNullable());

        if ($this->isIndexed()) {
            //Compound index may help with performance
            $innerSchema->index($this->getMorphKey(), $this->getInnerKey());
        }
    }
}