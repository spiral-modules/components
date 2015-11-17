<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\SQLServer\Schemas;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Entities\Schemas\AbstractColumn;

/**
 * SQL Server specific column schema.
 */
class ColumnSchema extends AbstractColumn
{
    /**
     * {@inheritdoc}
     */
    protected $mapping = [
        //Primary sequences
        'primary'     => [
            'type'     => 'int',
            'identity' => true,
            'nullable' => false
        ],
        'bigPrimary'  => [
            'type'     => 'bigint',
            'identity' => true,
            'nullable' => false
        ],

        //Enum type (mapped via method)
        'enum'        => 'enum',

        //Logical types
        'boolean'     => 'bit',

        //Integer types (size can always be changed with size method), longInteger has method alias
        //bigInteger
        'integer'     => 'int',
        'tinyInteger' => 'tinyint',
        'bigInteger'  => 'bigint',

        //String with specified length (mapped via method)
        'string'      => 'varchar',

        //Generic types
        'text'        => ['type' => 'varchar', 'size' => 0],
        'tinyText'    => ['type' => 'varchar', 'size' => 0],
        'longText'    => ['type' => 'varchar', 'size' => 0],

        //Real types
        'double'      => 'float',
        'float'       => 'real',

        //Decimal type (mapped via method)
        'decimal'     => 'decimal',

        //Date and Time types
        'datetime'    => 'datetime',
        'date'        => 'date',
        'time'        => 'time',
        'timestamp'   => 'datetime',

        //Binary types
        'binary'      => ['type' => 'varbinary', 'size' => 0],
        'tinyBinary'  => ['type' => 'varbinary', 'size' => 0],
        'longBinary'  => ['type' => 'varbinary', 'size' => 0],

        //Additional types
        'json'        => ['type' => 'varchar', 'size' => 0]
    ];

    /**
     * {@inheritdoc}
     */
    protected $reverseMapping = [
        'primary'     => [['type' => 'int', 'identity' => true]],
        'bigPrimary'  => [['type' => 'bigint', 'identity' => true]],
        'enum'        => ['enum'],
        'boolean'     => ['bit'],
        'integer'     => ['int'],
        'tinyInteger' => ['tinyint', 'smallint'],
        'bigInteger'  => ['bigint'],
        'text'        => [['type' => 'varchar', 'size' => 0]],
        'string'      => ['varchar', 'char'],
        'double'      => ['float'],
        'float'       => ['real'],
        'decimal'     => ['decimal'],
        'timestamp'   => ['datetime'],
        'date'        => ['date'],
        'time'        => ['time'],
        'binary'      => ['varbinary'],
    ];

    /**
     * Field is table identity.
     *
     * @var bool
     */
    protected $identity = false;

    /**
     * Name of default constraint.
     *
     * @var string
     */
    protected $defaultConstraint = '';

    /**
     * Name of enum constraint.
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

        if (!empty($this->defaultConstraint)) {
            $constraints[] = $this->defaultConstraint;
        }

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
    public function enum($values)
    {
        $this->enumValues = array_map('strval', is_array($values) ? $values : func_get_args());
        sort($this->enumValues);

        $this->type = 'varchar';
        foreach ($this->enumValues as $value) {
            $this->size = max((int)$this->size, strlen($value));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $ignoreEnum If true ENUM declaration statement will be returned only. Internal
     *                         helper.
     */
    public function sqlStatement($ignoreEnum = false)
    {
        if (!$ignoreEnum && $this->abstractType() == 'enum') {
            return "{$this->sqlStatement(true)} {$this->enumStatement()}";
        }

        $statement = [$this->getName(true), $this->type];

        if (!empty($this->precision)) {
            $statement[] = "({$this->precision}, {$this->scale})";
        } elseif (!empty($this->size)) {
            $statement[] = "({$this->size})";
        } elseif ($this->type == 'varchar' || $this->type == 'varbinary') {
            $statement[] = "(max)";
        }

        if ($this->identity) {
            $statement[] = 'IDENTITY(1,1)';
        }

        $statement[] = $this->nullable ? 'NULL' : 'NOT NULL';

        if ($this->hasDefaultValue()) {
            $statement[] = "DEFAULT {$this->prepareDefault()}";
        }

        return join(' ', $statement);
    }

    /**
     * Generate set of altering operations should be applied to column to change it's type, size,
     * default value or null flag.
     *
     * @param ColumnSchema $initial
     * @return array
     */
    public function alteringOperations(ColumnSchema $initial)
    {
        $operations = [];

        $currentDefinition = [
            $this->type,
            $this->size,
            $this->precision,
            $this->scale,
            $this->nullable
        ];

        $initialDefinition = [
            $initial->type,
            $initial->size,
            $initial->precision,
            $initial->scale,
            $initial->nullable
        ];

        if ($currentDefinition != $initialDefinition) {
            if ($this->abstractType() == 'enum') {
                //Getting longest value
                $enumSize = $this->size;
                foreach ($this->enumValues as $value) {
                    $enumSize = max($enumSize, strlen($value));
                }

                $type = "ALTER COLUMN {$this->getName(true)} varchar($enumSize)";
                $operations[] = $type . ' ' . ($this->nullable ? 'NULL' : 'NOT NULL');
            } else {
                $type = "ALTER COLUMN {$this->getName(true)} {$this->type}";

                if (!empty($this->size)) {
                    $type .= "($this->size)";
                } elseif ($this->type == 'varchar' || $this->type == 'varbinary') {
                    $type .= "(max)";
                } elseif (!empty($this->precision)) {
                    $type .= "($this->precision, $this->scale)";
                }

                $operations[] = $type . ' ' . ($this->nullable ? 'NULL' : 'NOT NULL');
            }
        }

        //Constraint should be already removed it this moment (see doColumnChange in TableSchema)
        if ($this->hasDefaultValue()) {
            $operations[] = \Spiral\interpolate(
                "ADD CONSTRAINT {constraint} DEFAULT {default} FOR {column}",
                [
                    'constraint' => $this->defaultConstrain(true),
                    'column'     => $this->getName(true),
                    'default'    => $this->prepareDefault()
                ]
            );
        }

        //Constraint should be already removed it this moment (see doColumnChange in TableSchema)
        if ($this->abstractType() == 'enum') {
            $operations[] = "ADD {$this->enumStatement()}";
        }

        return $operations;
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->type = $schema['DATA_TYPE'];
        $this->nullable = strtoupper($schema['IS_NULLABLE']) == 'YES';
        $this->defaultValue = $schema['COLUMN_DEFAULT'];

        $this->identity = (bool)$schema['is_identity'];

        $this->size = (int)$schema['CHARACTER_MAXIMUM_LENGTH'];
        if ($this->size == -1) {
            $this->size = 0;
        }

        if ($this->type == 'decimal') {
            $this->precision = (int)$schema['NUMERIC_PRECISION'];
            $this->scale = (int)$schema['NUMERIC_SCALE'];
        }

        //Normalizing default value
        $this->normalizeDefault();

        /**
         * We have to fetch all column constrains cos default and enum check will be included into
         * them, plus column drop is not possible without removing all constraints.
         */

        $tableDriver = $this->table->driver();
        if (!empty($schema['default_object_id'])) {
            //Looking for default constrain id
            $this->defaultConstraint = $tableDriver->query(
                "SELECT name FROM sys.default_constraints WHERE object_id = ?", [
                $schema['default_object_id']
            ])->fetchColumn();
        }

        //Potential enum
        if ($this->type == 'varchar' && !empty($this->size)) {
            $this->resolveEnum($schema, $tableDriver);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareDefault()
    {
        $defaultValue = parent::prepareDefault();
        if ($this->abstractType() == 'boolean') {
            $defaultValue = (int)$this->defaultValue;
        }

        return $defaultValue;
    }

    /**
     * Get name of enum constraint.
     *
     * @param bool $quoted True to quote identifier.
     * @return string
     */
    protected function enumConstraint($quoted = false)
    {
        if (empty($this->enumConstraint)) {
            $this->enumConstraint = $this->generateName('enum');
        }

        return $quoted
            ? $this->table->driver()->identifier($this->enumConstraint)
            : $this->enumConstraint;
    }

    /**
     * Default constrain name.
     *
     * @param bool $quoted
     * @return string
     */
    protected function defaultConstrain($quoted = false)
    {
        if (empty($this->defaultConstraint)) {
            $this->defaultConstraint = $this->generateName('default');
        }

        return $quoted
            ? $this->table->driver()->identifier($this->defaultConstraint)
            : $this->defaultConstraint;
    }

    /**
     * Enum constrain statement.
     *
     * @return string
     */
    private function enumStatement()
    {
        $enumValues = [];
        foreach ($this->enumValues as $value) {
            $enumValues[] = $this->table->driver()->getPDO()->quote($value);
        }

        $enumConstrain = $this->enumConstraint(true);
        $enumValues = join(', ', $enumValues);

        return "CONSTRAINT {$enumConstrain} CHECK ({$this->getName(true)} IN ({$enumValues}))";
    }

    /**
     * Normalizing default value.
     */
    private function normalizeDefault()
    {
        if (
            $this->defaultValue[0] == '('
            && $this->defaultValue[strlen($this->defaultValue) - 1] == ')'
        ) {
            //Cut braces
            $this->defaultValue = substr($this->defaultValue, 1, -1);
        }

        if (preg_match('/^[\'""].*?[\'"]$/', $this->defaultValue)) {
            $this->defaultValue = substr($this->defaultValue, 1, -1);
        }

        if (
            $this->phpType() != 'string'
            && (
                $this->defaultValue[0] == '('
                && $this->defaultValue[strlen($this->defaultValue) - 1] == ')'
            )
        ) {
            //Cut another braces
            $this->defaultValue = substr($this->defaultValue, 1, -1);
        }
    }

    /**
     * Check if column is enum.
     *
     * @param array  $schema
     * @param Driver $tableDriver
     */
    private function resolveEnum(array $schema, $tableDriver)
    {
        $query = "SELECT object_definition(o.object_id) AS [definition], "
            . "OBJECT_NAME(o.OBJECT_ID) AS [name]\nFROM sys.objects AS o\n"
            . "JOIN sys.sysconstraints AS [c] ON o.object_id = [c].constid\n"
            . "WHERE type_desc = 'CHECK_CONSTRAINT' AND parent_object_id = ? AND [c].colid = ?";

        $constraints = $tableDriver->query($query, [$schema['object_id'], $schema['column_id']]);

        foreach ($constraints as $checkConstraint) {
            $this->enumConstraint = $checkConstraint['name'];

            $name = preg_quote($this->getName(true));

            //We made some assumptions here...
            if (preg_match_all(
                '/' . $name . '=[\']?([^\']+)[\']?/i',
                $checkConstraint['definition'],
                $matches
            )) {
                //Fetching enum values
                $this->enumValues = $matches[1];
                sort($this->enumValues);
            }
        }
    }

    /**
     * Generate constrain name.
     *
     * @param string $type
     * @return string
     */
    private function generateName($type)
    {
        return $this->table->getName() . '_' . $this->getName() . "_{$type}_" . uniqid();
    }
}