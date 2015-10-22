<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)

 */
namespace Spiral\Database\Drivers\Postgres\Schemas;

use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Injections\SQLFragment;

/**
 * Postgres column schema.
 */
class ColumnSchema extends AbstractColumn
{
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
        'time'        => 'time',
        'timestamp'   => 'timestamp without time zone',
        //Binary types
        'binary'      => 'bytea',
        'tinyBinary'  => 'bytea',
        'longBinary'  => 'bytea',
        //Additional types
        'json'        => 'json'
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
        'json'        => ['json']
    ];

    /**
     * Field is auto incremental.
     *
     * @var bool
     */
    protected $autoIncrement = false;

    /**
     * Name of enum constraint associated with field.
     *
     * @var string
     */
    protected $enumConstraint = '';

    /**
     * {@inheritdoc}
     */
    public function getConstraints()
    {
        $constraints = parent::getConstraints();

        if (!empty($this->enumConstraint)) {
            $constraints[] = $this->enumConstraint;
        }

        return $constraints;
    }

    /**
     * {@inheritdoc}
     */
    public function abstractType()
    {
        if (!empty($this->enumValues)) {
            return 'enum';
        }

        return parent::abstractType();
    }

    /**
     * {@inheritdoc}
     */
    public function primary()
    {
        $this->autoIncrement = true;

        //Changing type of already created primary key (we can't use "serial" alias here)
        if (!empty($this->type) && $this->type != 'serial') {
            $this->type = 'integer';

            return $this;
        }

        return parent::primary();
    }

    /**
     * {@inheritdoc}
     */
    public function bigPrimary()
    {
        $this->autoIncrement = true;

        //Changing type of already created primary key (we can't use "serial" alias here)
        if (!empty($this->type) && $this->type != 'bigserial') {
            $this->type = 'bigint';

            return $this;
        }

        return parent::bigPrimary();
    }

    /**
     * {@inheritdoc}
     */
    public function enum($values)
    {
        $this->enumValues = array_map('strval', is_array($values) ? $values : func_get_args());

        $this->type = 'character';
        foreach ($this->enumValues as $value) {
            $this->size = max((int)$this->size, strlen($value));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement()
    {
        $statement = parent::sqlStatement();

        if ($this->abstractType() != 'enum') {
            return $statement;
        }

        //We have add constraint for enum type
        $enumValues = [];
        foreach ($this->enumValues as $value) {
            $enumValues[] = $this->table->driver()->getPDO()->quote($value);
        }

        return "$statement CONSTRAINT {$this->enumConstraint(true, true)} "
        . "CHECK ({$this->getName(true)} IN (" . join(', ', $enumValues) . "))";
    }

    /**
     * Generate set of altering operations should be applied to column to change it's type, size,
     * default value or null flag.
     *
     * @param AbstractColumn $original
     * @return array
     */
    public function alterOperations(AbstractColumn $original)
    {
        $operations = [];

        $typeDefinition = [$this->type, $this->size, $this->precision, $this->scale];
        $originalType = [$original->type, $original->size, $original->precision, $original->scale];

        if ($typeDefinition != $originalType) {
            if ($this->abstractType() == 'enum') {
                //Getting longest value
                $enumSize = $this->size;
                foreach ($this->enumValues as $value) {
                    $enumSize = max($enumSize, strlen($value));
                }

                $type = "ALTER COLUMN {$this->getName(true)} TYPE character($enumSize)";
                $operations[] = $type;
            } else {
                $type = "ALTER COLUMN {$this->getName(true)} TYPE {$this->type}";

                if (!empty($this->size)) {
                    $type .= "($this->size)";
                } elseif (!empty($this->precision)) {
                    $type .= "($this->precision, $this->scale)";
                }

                //Required to perform cross conversion
                $operations[] = "{$type} USING {$this->getName(true)}::{$this->type}";
            }
        }

        if ($original->abstractType() == 'enum' && !empty($this->enumConstraint)) {
            $operations[] = 'DROP CONSTRAINT ' . $this->enumConstraint(true);
        }

        if ($original->defaultValue != $this->defaultValue) {
            if (is_null($this->defaultValue)) {
                $operations[] = "ALTER COLUMN {$this->getName(true)} DROP DEFAULT";
            } else {
                $operations[] = "ALTER COLUMN {$this->getName(true)} SET DEFAULT {$this->prepareDefault()}";
            }
        }

        if ($original->nullable != $this->nullable) {
            $operations[] = "ALTER COLUMN {$this->getName(true)} "
                . (!$this->nullable ? 'SET' : 'DROP') . " NOT NULL";
        }

        if ($this->abstractType() == 'enum') {
            $enumValues = [];
            foreach ($this->enumValues as $value) {
                $enumValues[] = $this->table->driver()->getPDO()->quote($value);
            }

            $operations[] = "ADD CONSTRAINT {$this->enumConstraint(true)} "
                . "CHECK ({$this->getName(true)} IN (" . join(', ', $enumValues) . "))";
        }

        return $operations;
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->type = $schema['data_type'];
        $this->defaultValue = $schema['column_default'];
        $this->nullable = $schema['is_nullable'] == 'YES';

        if (
            in_array($this->type, ['int', 'bigint', 'integer'])
            && preg_match("/nextval(.*)/", $this->defaultValue)
        ) {
            $this->type = ($this->type == 'bigint' ? 'bigserial' : 'serial');
            $this->autoIncrement = true;

            $this->defaultValue = new SQLFragment($this->defaultValue);

            return;
        }

        if (
            ($this->type == 'character varying' || $this->type == 'character')
            && $schema['character_maximum_length']
        ) {
            $this->size = $schema['character_maximum_length'];
        }

        if ($this->type == 'numeric') {
            $this->precision = $schema['numeric_precision'];
            $this->scale = $schema['numeric_scale'];
        }

        /**
         * Attention, this is not default spiral enum type emulated via CHECK. This is real Postgres
         * enum type.
         */
        if ($this->type == 'USER-DEFINED' && $schema['typtype'] == 'e') {
            $this->type = $schema['typname'];
            $this->resolveNativeEnum();
        }

        //Potential enum with manually created constraint (check in)
        if (
            ($this->type == 'character' || $this->type == 'character varying')
            && !empty($this->size)
        ) {
            $this->checkCheckConstrain($schema['tableOID']);
        }

        $this->normalizeDefault();
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareEnum()
    {
        return '(' . $this->size . ')';
    }

    /**
     * Get name of enum constraint.
     *
     * @param bool $quote
     * @param bool $temporary If true enumConstraint identifier will be generated only for visual
     *                        purposes only.
     * @return string
     */
    private function enumConstraint($quote = false, $temporary = false)
    {
        if (empty($this->enumConstraint)) {
            if ($temporary) {
                return $this->table->getName() . '_' . $this->getName() . '_enum';
            }

            $this->enumConstraint = $this->table->getName() . '_' . $this->getName() . '_enum_' . uniqid();
        }

        return $quote ? $this->table->driver()->identifier($this->enumConstraint) : $this->enumConstraint;
    }

    /**
     * Normalize default value.
     */
    private function normalizeDefault()
    {
        if ($this->hasDefaultValue()) {
            if (preg_match("/^'?(.*?)'?::(.+)/", $this->defaultValue, $matches)) {
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
     * Resolve native enum type.
     **/
    private function resolveNativeEnum()
    {
        $range = $this->table->driver()->query(
            'SELECT enum_range(NULL::' . $this->type . ')'
        )->fetchColumn(0);

        $this->enumValues = explode(',', substr($range, 1, -1));

        if (!empty($this->defaultValue)) {
            //In database: 'value'::enumType
            $this->defaultValue = substr(
                $this->defaultValue,
                1,
                strpos($this->defaultValue, $this->type) - 4
            );
        }
    }

    /**
     * Check if column was declared with check constrain. I love name of this method.
     *
     * @param string $tableOID
     */
    private function checkCheckConstrain($tableOID)
    {
        $query = "SELECT conname, consrc FROM pg_constraint WHERE conrelid = ? AND contype = 'c' "
            . "AND (consrc LIKE ? OR consrc LIKE ?)";

        $constraints = $this->table->driver()->query(
            $query,
            [$tableOID, '(' . $this->name . '%', '("' . $this->name . '%',]
        );

        foreach ($constraints as $constraint) {
            if (preg_match('/ARRAY\[([^\]]+)\]/', $constraint['consrc'], $matches)) {
                $enumValues = explode(',', $matches[1]);
                foreach ($enumValues as &$value) {
                    if (preg_match("/^'?(.*?)'?::(.+)/", trim($value), $matches)) {
                        //In database: 'value'::TYPE
                        $value = $matches[1];
                    }

                    unset($value);
                }

                $this->enumValues = $enumValues;
                $this->enumConstraint = $constraint['conname'];
            }
        }
    }
}