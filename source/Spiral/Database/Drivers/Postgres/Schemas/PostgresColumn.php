<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\Database\Drivers\Postgres\Schemas;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Injections\Fragment;
use Spiral\Database\Schemas\ColumnInterface;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;

/**
 * @todo investigate potential issue with entity non handling enum correctly when multiple
 * @todo column changes happen in one session (who the hell will do that?)
 */
class PostgresColumn extends AbstractColumn
{
    /**
     * Default timestamp expression (driver specific).
     */
    const DATETIME_NOW = 'now()';

    /**
     * Private state related values.
     */
    const EXCLUDE_FROM_COMPARE = [
        'timezone',
        'constrained',
        'constrainName'
    ];

    /**
     * {@inheritdoc}
     */
    protected $mapping = [
        //Primary sequences
        'primary'     => ['type' => 'serial', 'autoIncrement' => true, 'nullable' => false],
        'bigPrimary'  => ['type' => 'bigserial', 'autoIncrement' => true, 'nullable' => false],

        //Enum type (mapped via method)
        'enum'        => 'enum',

        //Logical types
        'boolean'     => 'boolean',

        //Integer types (size can always be changed with size method), longInteger has method alias
        //bigInteger
        'integer'     => 'integer',
        'tinyInteger' => 'smallint',
        'bigInteger'  => 'bigint',

        //String with specified length (mapped via method)
        'string'      => 'character varying',

        //Generic types
        'text'        => 'text',
        'tinyText'    => 'text',
        'longText'    => 'text',

        //Real types
        'double'      => 'double precision',
        'float'       => 'real',

        //Decimal type (mapped via method)
        'decimal'     => 'numeric',

        //Date and Time types
        'datetime'    => 'timestamp without time zone',
        'date'        => 'date',
        'time'        => 'time without time zone',
        'timestamp'   => 'timestamp without time zone',

        //Binary types
        'binary'      => 'bytea',
        'tinyBinary'  => 'bytea',
        'longBinary'  => 'bytea',

        //Additional types
        'json'        => 'text',
    ];

    /**
     * {@inheritdoc}
     */
    protected $reverseMapping = [
        'primary'     => ['serial'],
        'bigPrimary'  => ['bigserial'],
        'enum'        => ['enum'],
        'boolean'     => ['boolean'],
        'integer'     => ['int', 'integer', 'int4'],
        'tinyInteger' => ['smallint'],
        'bigInteger'  => ['bigint', 'int8'],
        'string'      => ['character varying', 'character'],
        'text'        => ['text'],
        'double'      => ['double precision'],
        'float'       => ['real', 'money'],
        'decimal'     => ['numeric'],
        'date'        => ['date'],
        'time'        => ['time', 'time with time zone', 'time without time zone'],
        'timestamp'   => ['timestamp', 'timestamp with time zone', 'timestamp without time zone'],
        'binary'      => ['bytea'],
        'json'        => ['text'],
    ];

    /**
     * Field is auto incremental.
     *
     * @var bool
     */
    protected $autoIncrement = false;

    /**
     * Indication that column have enum constrain.
     *
     * @var bool
     */
    protected $constrained = false;

    /**
     * Name of enum constraint associated with field.
     *
     * @var string
     */
    protected $constrainName = '';

    /**
     * {@inheritdoc}
     */
    public function getConstraints(): array
    {
        $constraints = parent::getConstraints();

        if ($this->constrained) {
            $constraints[] = $this->constrainName;
        }

        return $constraints;
    }

    /**
     * {@inheritdoc}
     */
    public function abstractType(): string
    {
        if (!empty($this->enumValues)) {
            return 'enum';
        }

        return parent::abstractType();
    }

    /**
     * {@inheritdoc}
     */
    public function primary(): AbstractColumn
    {
        if (!empty($this->type) && $this->type != 'serial') {
            //Change type of already existed column (we can't use "serial" alias here)
            $this->type = 'integer';

            return $this;
        }

        return $this->setType('primary');
    }

    /**
     * {@inheritdoc}
     */
    public function bigPrimary(): AbstractColumn
    {
        if (!empty($this->type) && $this->type != 'bigserial') {
            //Change type of already existed column (we can't use "serial" alias here)
            $this->type = 'bigint';

            return $this;
        }

        return $this->setType('bigPrimary');
    }

    /**
     * {@inheritdoc}
     */
    public function enum($values): AbstractColumn
    {
        $this->enumValues = array_map('strval', is_array($values) ? $values : func_get_args());

        $this->type = 'character varying';
        foreach ($this->enumValues as $value) {
            $this->size = max((int)$this->size, strlen($value));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement(Driver $driver): string
    {
        $statement = parent::sqlStatement($driver);

        if ($this->abstractType() != 'enum') {
            //Nothing special
            return $statement;
        }

        //We have add constraint for enum type
        $enumValues = [];
        foreach ($this->enumValues as $value) {
            $enumValues[] = $driver->quote($value);
        }

        $constrain = $driver->identifier($this->enumConstraint());
        $column = $driver->identifier($this->getName());
        $values = implode(', ', $enumValues);

        return "{$statement} CONSTRAINT {$constrain} CHECK ($column IN ({$values}))";
    }

    /**
     * Generate set of operations need to change column.
     *
     * @param Driver         $driver
     * @param AbstractColumn $initial
     *
     * @return array
     */
    public function alterOperations(Driver $driver, AbstractColumn $initial): array
    {
        $operations = [];

        //To simplify comparation
        $currentType = [$this->type, $this->size, $this->precision, $this->scale];
        $initialType = [$initial->type, $initial->size, $initial->precision, $initial->scale];

        $identifier = $driver->identifier($this->getName());

        /*
         * This block defines column type and all variations.
         */
        if ($currentType != $initialType) {
            if ($this->abstractType() == 'enum') {
                //Getting longest value
                $enumSize = $this->size;
                foreach ($this->enumValues as $value) {
                    $enumSize = max($enumSize, strlen($value));
                }

                $type = "ALTER COLUMN {$identifier} TYPE character varying($enumSize)";
                $operations[] = $type;
            } else {
                $type = "ALTER COLUMN {$identifier} TYPE {$this->type}";

                if (!empty($this->size)) {
                    $type .= "($this->size)";
                } elseif (!empty($this->precision)) {
                    $type .= "($this->precision, $this->scale)";
                }

                //Required to perform cross conversion
                $operations[] = "{$type} USING {$identifier}::{$this->type}";
            }
        }

        //Dropping enum constrain before any operation
        if ($initial->abstractType() == 'enum' && $this->constrained) {
            $operations[] = 'DROP CONSTRAINT ' . $driver->identifier($this->enumConstraint());
        }

        //Default value set and dropping
        if ($initial->defaultValue != $this->defaultValue) {
            if (is_null($this->defaultValue)) {
                $operations[] = "ALTER COLUMN {$identifier} DROP DEFAULT";
            } else {
                $operations[] = "ALTER COLUMN {$identifier} SET DEFAULT {$this->quoteDefault($driver)}";
            }
        }

        //Nullable option
        if ($initial->nullable != $this->nullable) {
            $operations[] = "ALTER COLUMN {$identifier} " . (!$this->nullable ? 'SET' : 'DROP') . ' NOT NULL';
        }

        if ($this->abstractType() == 'enum') {
            $enumValues = [];
            foreach ($this->enumValues as $value) {
                $enumValues[] = $driver->quote($value);
            }

            $operations[] = "ADD CONSTRAINT {$driver->identifier($this->enumConstraint())} "
                . "CHECK ({$identifier} IN (" . implode(', ', $enumValues) . '))';
        }

        return $operations;
    }

    /**
     * {@inheritdoc}
     */
    protected function quoteEnum(Driver $driver): string
    {
        //Postgres enums are just constrained strings
        return '(' . $this->size . ')';
    }

    /**
     * Get/generate name for enum constraint.
     *
     * @return string
     */
    private function enumConstraint(): string
    {
        if (empty($this->constrainName)) {
            $this->constrainName = $this->table . '_' . $this->getName() . '_enum_' . uniqid();
        }

        return $this->constrainName;
    }

    /**
     * Normalize default value.
     */
    private function normalizeDefault()
    {
        if ($this->hasDefaultValue()) {
            if ($this->phpType() == self::FLOAT || $this->phpType() == self::INT) {
                if (preg_match('/^\(?(.*?)\)?(?!::(.+))?$/', $this->defaultValue, $matches)) {
                    //Negative numeric values
                    $this->defaultValue = $matches[1];
                }

                return;
            }

            if (preg_match('/^\'?(.*?)\'?::(.+)/', $this->defaultValue, $matches)) {
                //In database: 'value'::TYPE
                $this->defaultValue = $matches[1];
            } elseif ($this->type == 'bit') {
                $this->defaultValue = bindec(
                    substr($this->defaultValue, 2, strpos($this->defaultValue, '::') - 3)
                );
            } elseif ($this->type == 'boolean') {
                $this->defaultValue = (strtolower($this->defaultValue) == 'true');
            }
        }
    }

    /**
     * @param string $table  Table name.
     * @param array  $schema
     * @param Driver $driver Postgres columns are bit more complex.
     *
     * @return PostgresColumn
     */
    public static function createInstance(string $table, array $schema, Driver $driver): self
    {
        $column = new self($table, $schema['column_name'], $driver->getTimezone());

        $column->type = $schema['data_type'];
        $column->defaultValue = $schema['column_default'];
        $column->nullable = $schema['is_nullable'] == 'YES';

        if (
            in_array($column->type, ['int', 'bigint', 'integer'])
            && preg_match('/nextval(.*)/', $column->defaultValue)
        ) {
            $column->type = ($column->type == 'bigint' ? 'bigserial' : 'serial');
            $column->autoIncrement = true;

            $column->defaultValue = new Fragment($column->defaultValue);

            return $column;
        }

        if (strpos($column->type, 'char') !== false && $schema['character_maximum_length']) {
            $column->size = $schema['character_maximum_length'];
        }

        if ($column->type == 'numeric') {
            $column->precision = $schema['numeric_precision'];
            $column->scale = $schema['numeric_scale'];
        }

        if ($column->type == 'USER-DEFINED' && $schema['typtype'] == 'e') {
            $column->type = $schema['typname'];

            /**
             * Attention, this is not default spiral enum type emulated via CHECK. This is real
             * Postgres enum type.
             */
            self::resolveEnum($driver, $column);
        }

        if (strpos($column->type, 'char') !== false && !empty($column->size)) {
            //Potential enum with manually created constraint (check in)
            self::resolveConstrains($driver, $schema['tableOID'], $column);
        }

        $column->normalizeDefault();

        return $column;
    }

    /**
     * Resolving enum constrain and converting it into proper enum values set.
     *
     * @param Driver         $driver
     * @param string|int     $tableOID
     * @param PostgresColumn $column
     */
    private static function resolveConstrains(Driver $driver, $tableOID, PostgresColumn $column)
    {
        $query = "SELECT conname, consrc FROM pg_constraint WHERE conrelid = ? AND contype = 'c' AND "
            . "(consrc LIKE ? OR consrc LIKE ? OR consrc LIKE ? OR consrc LIKE ?)";

        $constraints = $driver->query($query, [
            $tableOID,
            '(' . $column->name . '%',
            '("' . $column->name . '%',
            //Postgres magic
            $column->name . '::text%',
            '%(' . $column->name . ')::text%'
        ]);

        foreach ($constraints as $constraint) {
            if (preg_match('/ARRAY\[([^\]]+)\]/', $constraint['consrc'], $matches)) {
                $enumValues = explode(',', $matches[1]);
                foreach ($enumValues as &$value) {
                    if (preg_match("/^'?(.*?)'?::(.+)/", trim($value, ' ()'), $matches)) {
                        //In database: 'value'::TYPE
                        $value = $matches[1];
                    }

                    unset($value);
                }

                $column->enumValues = $enumValues;
                $column->constrainName = $constraint['conname'];
                $column->constrained = true;
            }
        }
    }

    /**
     * Resolve native ENUM type if presented.
     *
     * @param Driver         $driver
     * @param PostgresColumn $column
     */
    private static function resolveEnum(Driver $driver, PostgresColumn $column)
    {
        $range = $driver->query('SELECT enum_range(NULL::' . $column->type . ')')->fetchColumn(0);

        $column->enumValues = explode(',', substr($range, 1, -1));

        if (!empty($column->defaultValue)) {
            //In database: 'value'::enumType
            $column->defaultValue = substr(
                $column->defaultValue,
                1,
                strpos($column->defaultValue, $column->type) - 4
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function compare(ColumnInterface $initial): bool
    {
        if (parent::compare($initial)) {
            return true;
        }

        if (
            in_array($this->abstractType(), ['primary', 'bigPrimary'])
            && $initial->getDefaultValue() != $this->getDefaultValue()
        ) {
            //PG adds default values to primary keys
            return true;
        }

        return false;
    }
}
