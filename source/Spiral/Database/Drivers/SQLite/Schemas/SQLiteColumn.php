<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\SQLite\Schemas;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;

class SQLiteColumn extends AbstractColumn
{
    /**
     * {@inheritdoc}
     */
    protected $mapping = [
        //Primary sequences
        'primary'     => [
            'type'       => 'integer',
            'primaryKey' => true,
            'nullable'   => false,
        ],
        'bigPrimary'  => [
            'type'       => 'integer',
            'primaryKey' => true,
            'nullable'   => false,
        ],

        //Enum type (mapped via method)
        'enum'        => 'enum',

        //Logical types
        'boolean'     => 'boolean',

        //Integer types (size can always be changed with size method), longInteger has method alias
        //bigInteger
        'integer'     => 'integer',
        'tinyInteger' => 'tinyint',
        'bigInteger'  => 'bigint',

        //String with specified length (mapped via method)
        'string'      => 'text',

        //Generic types
        'text'        => 'text',
        'tinyText'    => 'text',
        'longText'    => 'text',

        //Real types
        'double'      => 'double',
        'float'       => 'real',

        //Decimal type (mapped via method)
        'decimal'     => 'numeric',

        //Date and Time types
        'datetime'    => 'datetime',
        'date'        => 'date',
        'time'        => 'time',
        'timestamp'   => 'timestamp',

        //Binary types
        'binary'      => 'blob',
        'tinyBinary'  => 'blob',
        'longBinary'  => 'blob',

        //Additional types
        'json'        => 'text',
    ];

    /**
     * {@inheritdoc}
     */
    protected $reverseMapping = [
        'primary'     => [['type' => 'integer', 'primaryKey' => true]],
        'enum'        => ['enum'],
        'boolean'     => ['boolean'],
        'integer'     => ['int', 'integer', 'smallint', 'mediumint'],
        'tinyInteger' => ['tinyint'],
        'bigInteger'  => ['bigint'],
        'text'        => ['text', 'string'],
        'double'      => ['double'],
        'float'       => ['real'],
        'decimal'     => ['numeric'],
        'datetime'    => ['datetime'],
        'date'        => ['date'],
        'time'        => ['time'],
        'timestamp'   => ['timestamp'],
        'binary'      => ['blob'],
    ];

    /**
     * Indication that column is primary key.
     *
     * @var bool
     */
    protected $primaryKey = false;

    /**
     * DBMS specific reverse mapping must map database specific type into limited set of abstract
     * types.
     *
     * @return string
     */
    public function abstractType(): string
    {
        if ($this->primaryKey) {
            return 'primary';
        }

        return parent::abstractType();
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(Driver $driver): string
    {
        $statement = parent::sqlStatement($driver);
        if ($this->abstractType() != 'enum') {
            return $statement;
        }

        $enumValues = [];
        foreach ($this->enumValues as $value) {
            $enumValues[] = $driver->quote($value);
        }

        $quoted = $driver->quote($this->name);

        return "$statement CHECK ({$quoted} IN (" . implode(', ', $enumValues) . '))';
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareEnum(Driver $driver): string
    {
        return '';
    }

    /**
     * @param string $table Table name.
     * @param array  $schema
     *
     * @return SQLiteColumn
     */
    public static function createInstance(string $table, array $schema): self
    {
        $column = new self($table, $schema['name']);

        $column->nullable = !$schema['notnull'];
        $column->type = $schema['type'];
        $column->primaryKey = (bool)$schema['pk'];

        /*
         * Normalizing default value.
         */
        $column->defaultValue = $schema['dflt_value'];

        if (preg_match('/^[\'""].*?[\'"]$/', $column->defaultValue)) {
            $column->defaultValue = substr($column->defaultValue, 1, -1);
        }

        if (!preg_match(
            '/^(?P<type>[a-z]+) *(?:\((?P<options>[^\)]+)\))?/',
            $schema['type'],
            $matches
        )
        ) {
            //No type definition included
            return $column;
        }

        //Reformatted type value
        $column->type = $matches['type'];

        //Fetching size options
        if (!empty($matches['options'])) {
            $options = explode(',', $matches['options']);

            if (count($options) > 1) {
                $column->precision = (int)$options[0];
                $column->scale = (int)$options[1];
            } else {
                $column->size = (int)$options[0];
            }
        }

        if ($column->type == 'enum') {
            //Quoted column name
            $quoted = $schema['identifier'];

            foreach ($schema['tableStatement'] as $column) {
                //Looking for enum values in column definition code
                if (preg_match(
                    "/{$quoted} +enum.*?CHECK *\\({$quoted} in \\((.*?)\\)\\)/i",
                    trim($column),
                    $matches
                )) {
                    $enumValues = explode(',', $matches[1]);
                    foreach ($enumValues as &$value) {
                        //Trimming values
                        if (preg_match("/^'?(.*?)'?$/", trim($value), $matches)) {
                            //In database: 'value'
                            $value = $matches[1];
                        }

                        unset($value);
                    }

                    $column->enumValues = $enumValues;
                }
            }
        }

        return $column;
    }
}