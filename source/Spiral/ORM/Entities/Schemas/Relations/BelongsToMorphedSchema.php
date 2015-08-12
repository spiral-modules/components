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
use Spiral\ORM\Model;

/**
 * BelongsToMorphed are almost identical to BelongsTo except it parent Model defined by role value
 * stored in [morph key] and parent key in [inner key].
 *
 * You can define BelongsToMorphed relation using syntax for BelongsTo but declaring outer class
 * as interface, meaning you should not only declare inversed relation name, but also it's type -
 * HAS_ONE or HAS_MANY.
 *
 * Example: 'parent' => [self::BELONGS_TO => 'Models\CommentableInterface']
 *
 * Attention, be very careful using morphing relations, you must know what you doing!
 * Attention #2, relation like that can not be preloaded!
 *
 * Example, [Comment can belong to any CommentableInterface model], relation name "parent", relation
 * requested to be inversed into HAS_MANY "comments":
 * - relation will walk should every model implementing CommentableInterface to collect name and
 *   type of outer keys, if outer key is not consistent across models implementing this interface
 *   an exception will be raised, let's say that outer key is "id" in every model
 * - relation will create inner key "parent_id" in "comments" table (or other table name), nullable
 *   by default
 * - relation will create "parent_type" morph key in "comments" table, nullable by default
 * - relation will create complex index index on columns "parent_id" and "parent_type" in "comments"
 *   table if allowed
 * - due relation is inversable every model implementing CommentableInterface will receive HAS_MANY
 *   relation "comments" pointing to Comment model using model role value
 *
 * @see BelongsToSchema
 */
class BelongsToMorphedSchema extends MorphedSchema
{
    /**
     * {@inheritdoc}
     */
    const RELATION_TYPE = Model::BELONGS_TO_MORPHED;

    /**
     * {@inheritdoc}
     *
     * @invisible
     */
    protected $defaultDefinition = [
        //By default, we are looking for primary key in our outer models, outer key must present
        //in every outer model and be consistent
        Model::OUTER_KEY => '{outer:primaryKey}',
        //Inner key name will be created based on singular relation name and outer key name
        Model::INNER_KEY => '{name:singular}_{definition:outerKey}',
        //Morph key created based on singular relation name and postfix _type
        Model::MORPH_KEY => '{name:singular}_type',
        //Relation is nullable by default
        Model::NULLABLE  => true
    ];

    /**
     * {@inheritdoc}
     *
     * Relation will be inversed to every associated model.
     */
    public function inverseRelation()
    {
        //Same logic as in BelongsTo
        if (
            !is_array($this->definition[Model::INVERSE])
            || !isset($this->definition[Model::INVERSE][1])
        ) {
            throw new RelationSchemaException(
                "Unable to revert BELONG_TO_MORPHED relation '{$this->model}'.'{$this}', " .
                "backward relation type is missing or invalid."
            );
        }

        //We are going to inverse relation to every outer model
        $inversed = $this->definition[Model::INVERSE];
        foreach ($this->outerModels() as $model) {
            if (!$model->hasRelation($inversed[1])) {
                $model->addRelation(
                    $inversed[1],
                    [
                        $inversed[0]     => $this->model->getName(),
                        Model::OUTER_KEY => $this->definition[Model::INNER_KEY],
                        Model::INNER_KEY => $this->definition[Model::OUTER_KEY],
                        Model::MORPH_KEY => $this->definition[Model::MORPH_KEY],
                        Model::NULLABLE  => $this->definition[Model::NULLABLE]
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
        //Inner (parent) model table
        $innerSchema = $this->model->tableSchema();

        //Morph key contains parent role name
        $morphKey = $innerSchema->column($this->getMorphKey());

        //We have predefined morphed key size
        $morphKey->string(static::MORPH_COLUMN_SIZE);
        $morphKey->nullable($morphKey->isNullable() || $this->isNullable());

        //Points to inner key of outer models (outer key)
        $innerKey = $innerSchema->column($this->getInnerKey());
        $innerKey->type($this->getOuterKeyType());
        $innerKey->nullable($innerKey->isNullable() || $this->isNullable());

        if ($this->isIndexed()) {
            //Compound index may help with performance
            $innerSchema->index($this->getMorphKey(), $this->getInnerKey());
        }
    }
}