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
use Spiral\ORM\Schemas\RelationSchema;

class BelongsToSchema extends RelationSchema
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Model::BELONGS_TO;

    /**
     * Equivalent relationship resolved based on definition and not schema, usually polymorphic.
     */
    const EQUIVALENT_RELATION = Model::BELONGS_TO_MORPHED;

    /**
     * Default definition parameters, will be filled if parameter skipped from definition by user.
     *
     * @invisible
     * @var array
     */
    protected $defaultDefinition = [
        Model::OUTER_KEY         => '{outer:primaryKey}',
        Model::INNER_KEY         => '{name:singular}_{definition:OUTER_KEY}',
        Model::CONSTRAINT        => true,
        Model::CONSTRAINT_ACTION => 'CASCADE',
        Model::NULLABLE          => true
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
                "Unable to revert BELONG_TO relation ({$this->model}.{$this->name}), " .
                "back relation type is missing."
            );
        }

        $inversed = $this->definition[Model::INVERSE];

        $this->outerModel()->addRelation(
            $inversed[1],
            [
                $inversed[0]                    => $this->model->getClass(),
                Model::OUTER_KEY         => $this->definition[Model::INNER_KEY],
                Model::INNER_KEY         => $this->definition[Model::OUTER_KEY],
                Model::CONSTRAINT        => $this->definition[Model::CONSTRAINT],
                Model::CONSTRAINT_ACTION => $this->definition[Model::CONSTRAINT_ACTION],
                Model::NULLABLE          => $this->definition[Model::NULLABLE]
            ]
        );
    }

    /**
     * Create all required relation columns, indexes and constraints.
     */
    public function buildSchema()
    {
        $innerTable = $this->model->tableSchema();

        //Inner key type should match outer key type
        $innerKey = $innerTable->column($this->getInnerKey());
        $innerKey->type($this->outerKeyType());
        $innerKey->nullable($this->isNullable());

        //We can safely add index, it will not be created if outer model has passive schema
        $innerKey->index();

        if (!$this->isConstrained())
        {
            return;
        }

        //We are allowed to add foreign key, it will not be created if outer table has passive schema
        $foreignKey = $innerKey->foreign(
            $this->outerModel()->getTable(),
            $this->getOuterKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }
}