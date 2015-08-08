<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Model;
use Spiral\ORM\ORMException;
use Spiral\ORM\Schemas\MorphedRelationSchema;

class BelongsToMorphedSchema extends MorphedRelationSchema
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Model::BELONGS_TO_MORPHED;

    /**
     * Default definition parameters, will be filled if parameter skipped from definition by user.
     *
     * @invisible
     * @var array
     */
    protected $defaultDefinition = [
        Model::OUTER_KEY => '{outer:primaryKey}',
        Model::INNER_KEY => '{name:singular}_{definition:OUTER_KEY}',
        Model::MORPH_KEY => '{name:singular}_type',
        Model::NULLABLE  => true
    ];

    /**
     * Inverse relation.
     *
     * @throws ORMException
     */
    public function inverseRelation()
    {
        if (
            !is_array($this->definition[Model::INVERSE])
            || !isset($this->definition[Model::INVERSE][1])
        )
        {
            throw new ORMException(
                "Unable to revert BELONG_TO_MORPHED relation ({$this->model}.{$this->name}), " .
                "back relation type is missing."
            );
        }

        $inversed = $this->definition[Model::INVERSE];
        foreach ($this->getOuterModels() as $record)
        {
            $record->addRelation(
                $inversed[1],
                [
                    $inversed[0]            => $this->model->getClass(),
                    Model::OUTER_KEY => $this->definition[Model::INNER_KEY],
                    Model::INNER_KEY => $this->definition[Model::OUTER_KEY],
                    Model::MORPH_KEY => $this->definition[Model::MORPH_KEY],
                    Model::NULLABLE  => $this->definition[Model::NULLABLE]
                ]
            );
        }
    }

    /**
     * Create all required relation columns, indexes and constraints.
     *
     * @throws ORMException
     */
    public function buildSchema()
    {
        if (!$this->getOuterModels())
        {
            //No targets found, no need to generate anything
            return;
        }

        $innerSchema = $this->model->tableSchema();

        /**
         * Morph key contains parent type, nullable by default.
         */
        $morphKey = $innerSchema->column($this->getMorphKey());
        $morphKey->string(static::MORPH_COLUMN_SIZE);
        $morphKey->nullable($this->isNullable());

        /**
         * Inner key contains link to parent outer key (usually id), nullable by default.
         */
        $innerKey = $innerSchema->column($this->getInnerKey());
        $innerKey->type($this->outerKeyType());
        $innerKey->nullable($this->isNullable());

        //Required index
        $innerSchema->index($this->getMorphKey(), $this->getInnerKey());
    }
}