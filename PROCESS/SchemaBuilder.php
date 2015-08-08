<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities;

use Spiral\Core\Component;
use Spiral\Database\Schemas\AbstractTable;
use Spiral\ORM\Schemas\ModelSchema;
use Spiral\ORM\Schemas\RelationSchemaInterface;

class SchemaBuilder2 extends Component
{
     /**
     * Get appropriate relation schema based on provided definition.
     *
     * @param ModelSchema $model
     * @param string      $name
     * @param array       $definition
     * @return RelationSchemaInterface
     */
    public function relationSchema(ModelSchema $model, $name, array $definition)
    {
        if (empty($definition)) {
            throw new ORMException("Relation definition can not be empty.");
        }

        reset($definition);
        $type = key($definition);

        $relation = $this->orm->relationSchema($type, $this, $model, $name, $definition);
        if ($relation->hasEquivalent()) {
            return $relation->createEquivalent();
        }

        return $relation;
    }

    /**
     * Perform schema reflection to database(s). All declared tables will created or altered. Only
     * tables linked to non abstract models and model with active schema parameter will be executed.
     *
     * Schema builder will thrown an exception if table linked to model with disabled schema has
     * changed columns, however indexes and foreign keys will not cause such exception.
     *
     * @throws ORMException
     */
    public function executeSchema()
    {
        foreach ($this->getTables(true) as $table) {
            foreach ($this->models as $model) {
                if ($model->tableSchema() != $table) {
                    continue;
                }

                if ($model->isAbstract()) {
                    //Model is abstract, meaning we are not going to perform any table related
                    //operation
                    continue 2;
                }

                if ($model->isActiveSchema()) {
                    //Model has active schema, we are good
                    break;
                }

                //We have to thrown an exception if model with ACTIVE_SCHEMA = false requested
                //any column change (for example via external relation)
                if (!empty($columns = $table->alteredColumns())) {
                    $names = [];
                    foreach ($columns as $column) {
                        $names[] = $column->getName(true);
                    }

                    $names = join(', ', $names);

                    throw new ORMException(
                        "Unable to alter '{$table->getName()}' columns ({$names}), "
                        . "associated model stated ACTIVE_SCHEMA = false."
                    );
                }

                continue 2;
            }

            $table->save();
        }
    }

    /**
     * Normalize ODM schema and export it to be used by ODM component and all documents.
     *
     * @return array
     */
    public function normalizeSchema()
    {
        $schema = [];

        foreach ($this->models as $model) {
            if ($model->isAbstract()) {
                continue;
            }

            $recordSchema = [];

            $recordSchema[ORM::M_ROLE_NAME] = $model->getRoleName();
            $recordSchema[ORM::M_TABLE] = $model->getTable();
            $recordSchema[ORM::M_DB] = $model->getDatabase();
            $recordSchema[ORM::M_PRIMARY_KEY] = $model->getPrimaryKey();

            $recordSchema[ORM::M_COLUMNS] = $model->getDefaults();
            $recordSchema[ORM::M_HIDDEN] = $model->getHidden();
            $recordSchema[ORM::M_SECURED] = $model->getSecured();
            $recordSchema[ORM::M_FILLABLE] = $model->getFillable();

            $recordSchema[ORM::M_MUTATORS] = $model->getMutators();
            $recordSchema[ORM::M_VALIDATES] = $model->getValidates();

            //Relations
            $recordSchema[ORM::M_RELATIONS] = [];
            foreach ($model->getRelations() as $name => $relation) {
                $recordSchema[ORM::M_RELATIONS][$name] = $relation->normalizeSchema();
            }

            ksort($recordSchema);
            $schema[$model->getClass()] = $recordSchema;
        }

        return $schema;
    }
}