<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Helpers;

use Spiral\Database\Schemas\Prototypes\AbstractColumn;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\ColumnRenderException;
use Spiral\ORM\Exceptions\DefinitionException;

/**
 * Implements ability to define column in AbstractSchema based on string representation and default
 * value (if defined).
 */
class ColumnRenderer
{
    /**
     * Render columns in table based on string definition.
     *
     * Example:
     * renderColumns([
     *    'id'     => 'primary',
     *    'time'   => 'datetime, nullable',
     *    'status' => 'enum(active, disabled)'
     * ], [
     *    'status' => 'active',
     *    'time'   => null, //idential as if define column as null
     * ], $table);
     *
     * Attention, new table instance will be returned!
     *
     * @param array         $fields
     * @param array         $defaults
     * @param AbstractTable $table
     *
     * @return AbstractTable
     *
     * @throws ColumnRenderException
     */
    public function renderColumns(
        array $fields,
        array $defaults,
        AbstractTable $table
    ): AbstractTable {
        foreach ($fields as $name => $definition) {
            $column = $table->column($name);

            //Declaring our column
            $this->declareColumn(
                $column,
                $definition,
                array_key_exists($name, $defaults),
                $defaults[$name] ?? null
            );
        }

        return clone $table;
    }

    /**
     * Cast (specify) column schema based on provided column definition and default value.
     * Spiral will force default values (internally) for every NOT NULL column except primary keys!
     *
     * Column definition are compatible with database Migrations and AbstractColumn types.
     *
     * Column definition examples (by default all columns has flag NULL!):
     * protected $schema = [
     *      'id'           => 'primary',
     *      'name'         => 'string',                          //Default length is 255 characters.
     *      'email'        => 'string(255), not null',           //Can be NULL
     *      'status'       => 'enum(active, pending, disabled)', //Enum values, trimmed
     *      'balance'      => 'decimal(10, 2)',
     *      'message'      => 'text, notnull',                  //Alias for NOT NULL
     *      'time_expired' => 'timestamp'
     * ];
     *
     * Attention, column state will be affected!
     *
     * @see AbstractColumn
     *
     * @param AbstractColumn $column
     * @param string         $definition
     * @param bool           $hasDefault Must be set to true if default value was set by user.
     * @param mixed          $default    Default value declared by record schema.
     *
     * @return mixed
     *
     * @throws DefinitionException
     * @throws \Spiral\Database\Exceptions\SchemaException
     */
    public function declareColumn(
        AbstractColumn $column,
        string $definition,
        bool $hasDefault,
        $default = null
    ) {
        //Expression used to declare column type, easy to read
        $pattern = '/(?P<type>[a-z]+)(?: *\((?P<options>[^\)]+)\))?(?: *, *(?P<notnull>not ?null))?/i';

        if (!preg_match($pattern, $definition, $type)) {
            throw new DefinitionException(
                "Invalid column type definition in '{$this}'.'{$column->getName()}'"
            );
        }

        if (!empty($type['options'])) {
            //Exporting and trimming
            $type['options'] = array_map('trim', explode(',', $type['options']));
        }

        //ORM force EVERY column to NULL state unless different is said
        $column->nullable(true);

        if (!empty($type['notnull'])) {
            //Indication that column is nullable
            $column->nullable(false);
        }

        //Bypassing call to AbstractColumn->__call method (or specialized column method)
        call_user_func_array(
            [$column, $type['type']],
            !empty($type['options']) ? $type['options'] : []
        );

        if (in_array($column->abstractType(), ['primary', 'bigPrimary'])) {
            //No default value can be set of primary keys
            return $column;
        }

        if (!$hasDefault && !$column->isNullable()) {
            //We have to come up with some default value
            return $column->defaultValue($this->castDefault($column));
        }

        if (is_null($default) && empty($type['notnull'])) {
            //Default value is stated and NULL, clear what to do
            $column->nullable(true);
        }

        return $column->defaultValue($default);
    }

    /**
     * Cast default value based on column type. Required to prevent conflicts when not nullable
     * column added to existed table with data in.
     *
     * @param AbstractColumn $column
     *
     * @return mixed
     */
    public function castDefault(AbstractColumn $column)
    {
        if (in_array($column->abstractType(), ['timestamp', 'datetime', 'time', 'date'])) {
            return 0;
        }

        if ($column->abstractType() == 'enum') {
            //We can use first enum value as default
            return $column->getEnumValues()[0];
        }

        switch ($column->phpType()) {
            case 'int':
                return 0;
            case 'float':
                return 0.0;
            case 'bool':
                return false;
        }

        return '';
    }
}