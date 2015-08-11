<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Schemas;

use Psr\Log\LoggerAwareInterface;
use Spiral\Database\Schemas\AbstractColumn;
use Spiral\Database\Schemas\AbstractTable;
use Spiral\Database\SqlFragmentInterface;
use Spiral\Models\Schemas\ReflectionEntity;
use Spiral\ORM\Model;
use Spiral\ORM\ModelAccessorInterface;
use Spiral\ORM\ORMException;
use Spiral\ORM\SchemaBuilder;

class ModelSchem2a extends ReflectionEntity implements LoggerAwareInterface
{


    /**
     * Find all field mutators.
     *
     * @return mixed
     */
    protected function getMutators()
    {
        $mutators = parent::getMutators();

        //Default values.
        foreach ($this->tableSchema->getColumns() as $field => $column) {
            $type = $column->abstractType();

            $resolved = [];
            if ($filter = $this->builder->getMutators($type)) {
                $resolved += $filter;
            } elseif ($filter = $this->builder->getMutators('php:' . $column->phpType())) {
                $resolved += $filter;
            }

            if (isset($resolved['accessor'])) {
                //Ensuring type for accessor
                $resolved['accessor'] = [$resolved['accessor'], $type];
            }

            foreach ($resolved as $mutator => $filter) {
                if (!array_key_exists($field, $mutators[$mutator])) {
                    $mutators[$mutator][$field] = $filter;
                }
            }
        }

        foreach ($mutators as $mutator => &$filters) {
            foreach ($filters as $field => $filter) {
                $filters[$field] = $this->builder->processAlias($filter);

                if ($mutator == 'accessor' && is_string($filters[$field])) {
                    $type = null;
                    if (!empty($this->tableSchema->getColumns()[$field])) {
                        $type = $this->tableSchema->getColumns()[$field]->abstractType();
                    }

                    $filters[$field] = [$filters[$field], $type];
                }
            }
            unset($filters);
        }

        return $mutators;
    }

      /**
     * Prepare default value to be stored in models schema.
     *
     * @param string $name
     * @param mixed  $defaultValue
     * @return mixed|null
     */
    protected function prepareDefault($name, $defaultValue = null)
    {
        if (array_key_exists($name, $this->getAccessors())) {
            $accessor = $this->getAccessors()[$name];
            $option = null;
            if (is_array($accessor)) {
                list($accessor, $option) = $accessor;
            }

            /**
             * @var ModelAccessorInterface $accessor
             */
            $accessor = new $accessor($defaultValue, null, $option);

            //We have to pass default value thought accessor
            return $accessor->defaultValue($this->tableSchema->driver());
        }

        if (array_key_exists($name, $this->getSetters()) && $this->getSetters()[$name]) {
            $setter = $this->getSetters()[$name];

            //We have to pass default value thought accessor
            return call_user_func($setter, $defaultValue);
        }

        return $defaultValue;
    }
}