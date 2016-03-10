<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entities\Schemas\Relations\Traits;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Exceptions\DefinitionException;

/**
 * Trait provides ability for relation to cast columns in specified table using format identical to
 * format used in record schema. Used by relations with pivot tables.
 */
trait ColumnsTrait
{
    /**
     * Cast table using set of columns and their default values.
     *
     * @param AbstractTable $table
     * @param array         $columns
     * @param array         $defaults
     */
    protected function castTable(AbstractTable $table, array $columns, array $defaults)
    {
        foreach ($columns as $column => $definition) {
            //Addition pivot columns must be defined same way as in Record schema
            $column = $this->castColumn($table, $table->column($column), $definition);

            if (!empty($defaults[$column->getName()])) {
                $column->defaultValue($defaults[$column->getName()]);
            }
        }
    }

    /**
     * Cast (specify) column schema based on provided column definition. Column definition are
     * compatible with database Migrations, AbstractColumn types and Record schema.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $column
     * @param string         $definition
     *
     * @return AbstractColumn
     *
     * @throws DefinitionException
     * @throws \Spiral\Database\Exceptions\SchemaException
     */
    private function castColumn(AbstractTable $table, AbstractColumn $column, $definition)
    {
        //Expression used to declare column type, easy to read
        $pattern = '/(?P<type>[a-z]+)(?: *\((?P<options>[^\)]+)\))?(?: *, *(?P<nullable>null(?:able)?))?/i';

        if (!preg_match($pattern, $definition, $type)) {
            throw new DefinitionException(
                "Invalid column type definition in '{$this}'.'{$column->getName()}'."
            );
        }

        if (!empty($type['options'])) {
            //Exporting and trimming
            $type['options'] = array_map('trim', explode(',', $type['options']));
        }

        //We are forcing every column to be NOT NULL by default, DEFAULT value should fix potential
        //problems, nullable flag must be applied before type was set (some types do not want
        //null values to be allowed)
        $column->nullable(!empty($type['nullable']));

        //Bypassing call to AbstractColumn->__call method (or specialized column method)
        call_user_func_array(
            [$column, $type['type']],
            !empty($type['options']) ? $type['options'] : []
        );

        //Default value
        if (!$column->hasDefaultValue() && !$column->isNullable()) {
            //Ouch, columns like that can break synchronization!
            $column->defaultValue($this->castDefault($table, $column));
        }

        return $column;
    }

    /**
     * Cast default value based on column type. Required to prevent conflicts when not nullable
     * column added to existed table with data in.
     *
     * @param AbstractTable  $table
     * @param AbstractColumn $column
     *
     * @return bool|float|int|mixed|string
     */
    private function castDefault(AbstractTable $table, AbstractColumn $column)
    {
        if ($column->abstractType() == 'timestamp' || $column->abstractType() == 'datetime') {
            $driver = $table->driver();

            return $driver::DEFAULT_DATETIME;
        }

        if ($column->abstractType() == 'enum') {
            //We can use first enum value as default
            return $column->getEnumValues()[0];
        }

        switch ($column->phpType()) {
            case 'int':
                return 0;
                break;
            case 'float':
                return 0.0;
                break;
            case 'bool':
                return false;
                break;
        }

        return '';
    }
}
