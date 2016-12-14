<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Drivers\SQLServer\Schemas;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Schemas\Prototypes\AbstractColumn;

class SQLServerColumn extends AbstractColumn
{
    /**
     * {@inheritdoc}
     */
    protected $mapping = [
        //Primary sequences
        'primary'     => ['type' => 'int', 'identity' => true, 'nullable' => false],
        'bigPrimary'  => ['type' => 'bigint', 'identity' => true, 'nullable' => false],

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
        'json'        => ['type' => 'varchar', 'size' => 0],
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
     * @var bool
     */
    protected $constrainedDefault = false;

    /**
     * Name of default constraint.
     *
     * @var string
     */
    protected $defaultConstraint = '';

    /**
     * @var bool
     */
    protected $constrainedEnum = false;

    /**
     * Name of enum constraint.
     *
     * @var string
     */
    protected $enumConstraint = '';

    /**
     * {@inheritdoc}
     */
    public function getConstraints(): array
    {
        $constraints = parent::getConstraints();

        if ($this->constrainedDefault) {
            $constraints[] = $this->defaultConstraint;
        }

        if ($this->constrainedEnum) {
            $constraints[] = $this->enumConstraint;
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
    public function enum($values): AbstractColumn
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
     *                         helper. todo check it.
     */
    public function sqlStatement(Driver $driver, bool $ignoreEnum = false): string
    {
        if (!$ignoreEnum && $this->abstractType() == 'enum') {
            return "{$this->sqlStatement($driver, true)} {$this->enumStatement()}";
        }

        $statement = [$driver->identifier($this->getName()), $this->type];

        if (!empty($this->precision)) {
            $statement[] = "({$this->precision}, {$this->scale})";
        } elseif (!empty($this->size)) {
            $statement[] = "({$this->size})";
        } elseif ($this->type == 'varchar' || $this->type == 'varbinary') {
            $statement[] = '(max)';
        }

        if ($this->identity) {
            $statement[] = 'IDENTITY(1,1)';
        }

        $statement[] = $this->nullable ? 'NULL' : 'NOT NULL';

        if ($this->hasDefaultValue()) {
            $statement[] = "DEFAULT {$this->prepareDefault($driver)}";
        }

        return implode(' ', $statement);
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareDefault(Driver $driver): string
    {
        $defaultValue = parent::prepareDefault($driver);
        if ($this->abstractType() == 'boolean') {
            $defaultValue = (int)$this->defaultValue;
        }

        return $defaultValue;
    }

    /**
     * Normalize default value.
     */
    private function normalizeDefault()
    {
        if (!$this->hasDefaultValue()) {
            return;
        }

        if ($this->defaultValue[0] == '(' && $this->defaultValue[strlen($this->defaultValue) - 1] == ')') {
            //Cut braces
            $this->defaultValue = substr($this->defaultValue, 1, -1);
        }

        if (preg_match('/^[\'""].*?[\'"]$/', $this->defaultValue)) {
            $this->defaultValue = substr($this->defaultValue, 1, -1);
        }

        if (
            $this->phpType() != 'string'
            && ($this->defaultValue[0] == '(' && $this->defaultValue[strlen($this->defaultValue) - 1] == ')')
        ) {
            //Cut another braces
            $this->defaultValue = substr($this->defaultValue, 1, -1);
        }
    }

    /**
     * @param string $table  Table name.
     * @param array  $schema
     * @param Driver $driver SQLServer columns are bit more complex.
     *
     * @return SQLServerColumn
     */
    public static function createInstance(string $table, array $schema, Driver $driver): self
    {
        $column = new self($table, $schema['COLUMN_NAME']);

        $column->type = $schema['DATA_TYPE'];
        $column->nullable = strtoupper($schema['IS_NULLABLE']) == 'YES';
        $column->defaultValue = $schema['COLUMN_DEFAULT'];

        $column->identity = (bool)$schema['is_identity'];

        $column->size = (int)$schema['CHARACTER_MAXIMUM_LENGTH'];
        if ($column->size == -1) {
            $column->size = 0;
        }

        if ($column->type == 'decimal') {
            $column->precision = (int)$schema['NUMERIC_PRECISION'];
            $column->scale = (int)$schema['NUMERIC_SCALE'];
        }

        //Normalizing default value
        $column->normalizeDefault();

        /*
        * We have to fetch all column constrains cos default and enum check will be included into
        * them, plus column drop is not possible without removing all constraints.
        */

        if (!empty($schema['default_object_id'])) {
            //Looking for default constrain id
            $column->defaultConstraint = $driver->query(
                'SELECT [name] FROM [sys].[default_constraints] WHERE [object_id] = ?', [
                $schema['default_object_id'],
            ])->fetchColumn();

            if (!empty($column->defaultConstraint)) {
                $column->constrainedDefault = true;
            }
        }

        //Potential enum
//        if ($column->type == 'varchar' && !empty($column->size)) {
//            $column->resolveEnum($schema, $tableDriver);
//        }

        return $column;
    }

    /**
     * Check if column is enum.
     *
     * @param array  $schema
     * @param Driver $tableDriver
     */
//    private static function resolveEnum(array $schema, $tableDriver)
//    {
//        $query = 'SELECT object_definition(o.object_id) AS [definition], '
//            . "OBJECT_NAME(o.OBJECT_ID) AS [name]\nFROM sys.objects AS o\n"
//            . "JOIN sys.sysconstraints AS [c] ON o.object_id = [c].constid\n"
//            . "WHERE type_desc = 'CHECK_CONSTRAINT' AND parent_object_id = ? AND [c].colid = ?";
//
//        $constraints = $tableDriver->query($query, [$schema['object_id'], $schema['column_id']]);
//
//        foreach ($constraints as $checkConstraint) {
//            $this->enumConstraint = $checkConstraint['name'];
//
//            $name = preg_quote($this->getName(true));
//
//            //We made some assumptions here...
//            if (preg_match_all(
//                '/' . $name . '=[\']?([^\']+)[\']?/i',
//                $checkConstraint['definition'],
//                $matches
//            )) {
//                //Fetching enum values
//                $this->enumValues = $matches[1];
//                sort($this->enumValues);
//            }
//        }
//    }
}