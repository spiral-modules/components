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

class HasOneSchema extends RelationSchema
{
    /**
     * Relation type.
     */
    const RELATION_TYPE = Model::HAS_ONE;

    /**
     * Default definition parameters, will be filled if parameter skipped from definition by user.
     *
     * @invisible
     * @var array
     */
    protected $defaultDefinition = [
        Model::INNER_KEY         => '{record:primaryKey}',
        Model::OUTER_KEY         => '{record:roleName}_{definition:INNER_KEY}',
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
        $this->outerModel()->addRelation(
            $this->definition[Model::INVERSE],
            [
                Model::BELONGS_TO        => $this->model->getName(),
                Model::INNER_KEY         => $this->definition[Model::OUTER_KEY],
                Model::OUTER_KEY         => $this->definition[Model::INNER_KEY],
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
        $outerTable = $this->outerModel()->tableSchema();

        //Outer key type should be matched with inner key type
        $outerKey = $outerTable->column($this->getOuterKey());
        $outerKey->type($this->innerKeyType());
        $outerKey->nullable($this->isNullable());

        if (!empty($this->definition[Model::MORPH_KEY]))
        {
            //We are not going to configure polymorphic relations here
            return;
        }

        //We can safely add index, it will not be created if outer model has passive schema
        $outerKey->index();

        if (!$this->isConstrained())
        {
            return;
        }

        //We are allowed to add foreign key, it will not be created if outer table has passive schema
        $foreignKey = $outerKey->foreign(
            $this->model->getTable(),
            $this->getInnerKey()
        );

        $foreignKey->onDelete($this->getConstraintAction());
        $foreignKey->onUpdate($this->getConstraintAction());
    }
}