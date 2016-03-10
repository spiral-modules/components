<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Schemas\Relations;

use Spiral\ORM\Entities\Schemas\MorphedSchema;
use Spiral\ORM\Exceptions\RelationSchemaException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;

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
    const RELATION_TYPE = RecordEntity::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //By default, we are looking for primary key in our outer records, outer key must present
        //in every outer record and be consistent
        RecordEntity::OUTER_KEY      => '{outer:primaryKey}',
        //Inner key name will be created based on singular relation name and outer key name
        RecordEntity::INNER_KEY      => '{name:singular}_{definition:outerKey}',
        //Morph key created based on singular relation name and postfix _type
        RecordEntity::MORPH_KEY      => '{name:singular}_type',
        //Relation allowed to create indexes in pivot table
        RecordEntity::CREATE_INDEXES => true,
        //Relation is nullable by default
        RecordEntity::NULLABLE       => true,
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
            !is_array($this->definition[RecordEntity::INVERSE])
            || !isset($this->definition[RecordEntity::INVERSE][1])
        ) {
            throw new RelationSchemaException(
                "Unable to revert BELONG_TO_MORPHED relation '{$this->record}'.'{$this}', " .
                'backward relation type is missing or invalid.'
            );
        }

        //We are going to inverse relation to every outer record
        $inversed = $this->definition[RecordEntity::INVERSE];
        foreach ($this->outerRecords() as $record) {
            if (!$record->hasRelation($inversed[1])) {
                $record->addRelation($inversed[1], [
                    $inversed[0]            => $this->record->getName(),
                    RecordEntity::OUTER_KEY => $this->definition[RecordEntity::INNER_KEY],
                    RecordEntity::INNER_KEY => $this->definition[RecordEntity::OUTER_KEY],
                    RecordEntity::MORPH_KEY => $this->definition[RecordEntity::MORPH_KEY],
                    RecordEntity::NULLABLE  => $this->definition[RecordEntity::NULLABLE],
                ]);
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
        $innerKey->setType($this->getOuterKeyType());
        $innerKey->nullable($innerKey->isNullable() || $this->isNullable());

        if ($this->isIndexed()) {
            //Compound index may help with performance
            $innerSchema->index($this->getMorphKey(), $this->getInnerKey());
        }
    }

    /**
     * Normalize schema definition into light cachable form.
     *
     * @return array
     */
    protected function normalizeDefinition()
    {
        $definition = parent::normalizeDefinition();
        if (empty($this->outerRecords())) {
            return $definition;
        }

        //We should only check first record since they all must follow same key
        if ($this->getOuterKey() == $this->outerRecords()[0]->getPrimaryKey()) {
            //Linked using primary key
            $definition[ORM::M_PRIMARY_KEY] = $this->getOuterKey();
        }

        return $definition;
    }
}
