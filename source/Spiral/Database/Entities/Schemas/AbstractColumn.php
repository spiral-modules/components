<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Entities\Database;
use Spiral\Database\Exceptions\InvalidArgumentException;
use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Injections\SQLFragment;
use Spiral\Database\Injections\SQLFragmentInterface;
use Spiral\Database\Schemas\ColumnInterface;

/**
 * Abstract column schema with read (see ColumnInterface) and write abilities. Must be implemented
 * by driver to support DBMS specific syntax and creation rules.
 *
 * Shortcuts for various column types:
 * @method AbstractColumn|$this boolean()
 *
 * @method AbstractColumn|$this integer()
 * @method AbstractColumn|$this tinyInteger()
 * @method AbstractColumn|$this bigInteger()
 *
 * @method AbstractColumn|$this text()
 * @method AbstractColumn|$this tinyText()
 * @method AbstractColumn|$this longText()
 *
 * @method AbstractColumn|$this double()
 * @method AbstractColumn|$this float()
 *
 * @method AbstractColumn|$this datetime()
 * @method AbstractColumn|$this date()
 * @method AbstractColumn|$this time()
 * @method AbstractColumn|$this timestamp()
 *
 * @method AbstractColumn|$this binary()
 * @method AbstractColumn|$this tinyBinary()
 * @method AbstractColumn|$this longBinary()
 *
 * @method AbstractColumn|$this json()
 */
abstract class AbstractColumn implements ColumnInterface
{
    /**
     * Abstract type aliases (for consistency).
     *
     * @var array
     */
    private $aliases = [
        'int'            => 'integer',
        'bigint'         => 'bigInteger',
        'incremental'    => 'primary',
        'bigIncremental' => 'bigPrimary',
        'bool'           => 'boolean',
        'blob'           => 'binary'
    ];

    /**
     * Association list between abstract types and native PHP types. Every non listed type will be
     * converted into string.
     *
     * @invisible
     * @var array
     */
    private $phpMapping = [
        self::INT   => ['primary', 'bigPrimary', 'integer', 'tinyInteger', 'bigInteger'],
        self::BOOL  => ['boolean'],
        self::FLOAT => ['double', 'float', 'decimal']
    ];

    /**
     * Mapping between abstract type and internal database type with it's options. Multiple abstract
     * types can map into one database type, this implementation allows us to equalize two columns
     * if they have different abstract types but same database one. Must be declared by DBMS
     * specific implementation.
     *
     * Example:
     * integer => array('type' => 'int', 'size' => 1),
     * boolean => array('type' => 'tinyint', 'size' => 1)
     *
     * @invisible
     * @var array
     */
    protected $mapping = [
        //Primary sequences
        'primary'     => null,
        'bigPrimary'  => null,
        //Enum type (mapped via method)
        'enum'        => null,
        //Logical types
        'boolean'     => null,
        //Integer types (size can always be changed with size method), longInteger has method alias
        //bigInteger
        'integer'     => null,
        'tinyInteger' => null,
        'bigInteger'  => null,
        //String with specified length (mapped via method)
        'string'      => null,
        //Generic types
        'text'        => null,
        'tinyText'    => null,
        'longText'    => null,
        //Real types
        'double'      => null,
        'float'       => null,
        //Decimal type (mapped via method)
        'decimal'     => null,
        //Date and Time types
        'datetime'    => null,
        'date'        => null,
        'time'        => null,
        'timestamp'   => null,
        //Binary types
        'binary'      => null,
        'tinyBinary'  => null,
        'longBinary'  => null,
        //Additional types
        'json'        => null
    ];

    /**
     * Reverse mapping is responsible for generating abstact type based on database type and it's
     * options. Multiple database types can be mapped into one abstract type.
     *
     * @invisible
     * @var array
     */
    protected $reverseMapping = [
        'primary'     => [],
        'bigPrimary'  => [],
        'enum'        => [],
        'boolean'     => [],
        'integer'     => [],
        'tinyInteger' => [],
        'bigInteger'  => [],
        'string'      => [],
        'text'        => [],
        'tinyText'    => [],
        'longText'    => [],
        'double'      => [],
        'float'       => [],
        'decimal'     => [],
        'datetime'    => [],
        'date'        => [],
        'time'        => [],
        'timestamp'   => [],
        'binary'      => [],
        'tinyBinary'  => [],
        'longBinary'  => [],
        'json'        => []
    ];

    /**
     * Column name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * DBMS specific column type.
     *
     * @var string
     */
    protected $type = '';

    /**
     * Indicates that column can contain null values.
     *
     * @var bool
     */
    protected $nullable = true;

    /**
     * Default column value, may not be applied to some datatypes (for example to primary keys),
     * should follow type size and other options.
     *
     * @var mixed
     */
    protected $defaultValue = null;

    /**
     * Column type size, can have different meanings for different datatypes.
     *
     * @var int
     */
    protected $size = 0;

    /**
     * Precision of column, applied only for "decimal" type.
     *
     * @var int
     */
    protected $precision = 0;

    /**
     * Scale of column, applied only for "decimal" type.
     *
     * @var int
     */
    protected $scale = 0;

    /**
     * List of allowed enum values.
     *
     * @var array
     */
    protected $enumValues = [];

    /**
     * @invisible
     * @var AbstractTable
     */
    protected $table = null;

    /**
     * @param AbstractTable $table
     * @param string        $name
     * @param mixed         $schema Driver specific column information.
     */
    public function __construct(AbstractTable $table, $name, $schema = null)
    {
        $this->name = $name;
        $this->table = $table;

        !empty($schema) && $this->resolveSchema($schema);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $quoted Quote name.
     */
    public function getName($quoted = false)
    {
        return $quoted ? $this->table->driver()->identifier($this->name) : $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function phpType()
    {
        $schemaType = $this->abstractType();
        foreach ($this->phpMapping as $phpType => $candidates) {
            if (in_array($schemaType, $candidates)) {
                return $phpType;
            }
        }

        return self::STRING;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * {@inheritdoc}
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * {@inheritdoc}
     */
    public function isNullable()
    {
        return $this->nullable;
    }

    /**
     * {@inheritdoc}
     */
    public function hasDefaultValue()
    {
        return !is_null($this->defaultValue);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValue()
    {
        if (!$this->hasDefaultValue()) {
            return null;
        }

        if ($this->defaultValue instanceof SQLFragmentInterface) {
            return $this->defaultValue;
        }

        if (in_array($this->abstractType(), ['time', 'date', 'datetime', 'timestamp'])) {
            if (strtolower($this->defaultValue) == strtolower($this->table->driver()->timestampNow())) {
                return new SQLFragment($this->defaultValue);
            }
        }

        switch ($this->phpType()) {
            case 'int':
                return (int)$this->defaultValue;
            case 'float':
                return (float)$this->defaultValue;
            case 'bool':
                if (strtolower($this->defaultValue) == 'false') {
                    return false;
                }

                return (bool)$this->defaultValue;
        }

        return (string)$this->defaultValue;
    }

    /**
     * Get every associated column constraint names.
     *
     * @return array
     */
    public function getConstraints()
    {
        return [];
    }

    /**
     * Get allowed enum values.
     *
     * @return array
     */
    public function getEnumValues()
    {
        return $this->enumValues;
    }

    /**
     * DBMS specific reverse mapping must map database specific type into limited set of abstract
     * types.
     *
     * @return string
     */
    public function abstractType()
    {
        foreach ($this->reverseMapping as $type => $candidates) {
            foreach ($candidates as $candidate) {
                if (is_string($candidate)) {
                    if (strtolower($candidate) == strtolower($this->type)) {
                        return $type;
                    }

                    continue;
                }

                if (strtolower($candidate['type']) != strtolower($this->type)) {
                    continue;
                }

                foreach ($candidate as $option => $required) {
                    if ($option == 'type') {
                        continue;
                    }

                    if ($this->$option != $required) {
                        continue 2;
                    }
                }

                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Set column name. It's recommended to use AbstractTable->renameColumn() to safely rename
     * columns.
     *
     * @param string $name New column name.
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Give column new abstract type. DBMS specific implementation must map provided type into one
     * of internal database values.
     *
     * Attention, changing type of existed columns in some databases has a lot of restrictions like
     * cross type conversions and etc. Try do not change column type without a reason.
     *
     * @param string $abstract Abstract or virtual type declared in mapping.
     * @return $this
     * @throws SchemaException
     */
    public function type($abstract)
    {
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        if (!isset($this->mapping[$abstract])) {
            throw new SchemaException("Undefined abstract/virtual type '{$abstract}'.");
        }

        //Resetting all values to default state.
        $this->size = $this->precision = $this->scale = 0;
        $this->enumValues = [];

        if (is_string($this->mapping[$abstract])) {
            $this->type = $this->mapping[$abstract];

            return $this;
        }

        //Additional type options
        foreach ($this->mapping[$abstract] as $property => $value) {
            $this->$property = $value;
        }

        return $this;
    }

    /**
     * Set column nullable/not nullable.
     *
     * @param bool $nullable
     * @return $this
     */
    public function nullable($nullable = true)
    {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * Change column default value (can be forbidden for some column types).
     * Use Database::TIMESTAMP_NOW to use driver specific NOW() function.
     *
     * @param mixed $value
     * @return $this
     */
    public function defaultValue($value)
    {
        $this->defaultValue = $value;
        if (
            $this->abstractType() == 'timestamp'
            && strtolower($value) == strtolower(Database::TIMESTAMP_NOW)
        ) {
            $this->defaultValue = $this->table->driver()->timestampNow();
        }

        return $this;
    }

    /**
     * Set column as primary key and register it in parent table primary key list.
     *
     * @see TableSchema::setPrimaryKeys()
     * @return $this
     */
    public function primary()
    {
        $this->table->setPrimaryKeys([$this->name]);

        return $this->type('primary');
    }

    /**
     * Set column as big primary key and register it in parent table primary key list.
     *
     * @see TableSchema::setPrimaryKeys()
     * @return $this
     */
    public function bigPrimary()
    {
        $this->table->setPrimaryKeys([$this->name]);

        return $this->type('bigPrimary');
    }

    /**
     * Set column as enum type and specify set of allowed values. Most of drivers will emulate enums
     * using column constraints.
     *
     * Examples:
     * $table->status->enum(['active', 'disabled']);
     * $table->status->enum('active', 'disabled');
     *
     * @param string|array $values Enum values (array or comma separated). String values only.
     * @return $this
     */
    public function enum($values)
    {
        $this->type('enum');
        $this->enumValues = array_map('strval', is_array($values) ? $values : func_get_args());

        return $this;
    }

    /**
     * Set column type as string with limited size. Maximum allowed size is 255 bytes, use "text"
     * abstract types for longer strings.
     *
     * Strings are perfect type to store email addresses as it big enough to store valid address
     * and
     * can be covered with unique index.
     *
     * @link http://stackoverflow.com/questions/386294/what-is-the-maximum-length-of-a-valid-email-address
     * @param int $size Max string length.
     * @return $this
     * @throws InvalidArgumentException
     */
    public function string($size = 255)
    {
        $this->type('string');

        if ($size > 255) {
            throw new InvalidArgumentException(
                "String size can't exceed 255 characters. Use text instead."
            );
        }

        if ($size < 0) {
            throw new InvalidArgumentException("Invalid string length value.");
        }

        $this->size = (int)$size;

        return $this;
    }

    /**
     * Set column type as decimal with specific precision and scale.
     *
     * @param int $precision
     * @param int $scale
     * @return $this
     * @throws InvalidArgumentException
     */
    public function decimal($precision, $scale = 0)
    {
        $this->type('decimal');

        if (empty($precision)) {
            throw new InvalidArgumentException("Invalid precision value.");
        }

        $this->precision = (int)$precision;
        $this->scale = (int)$scale;

        return $this;
    }

    /**
     * Create/get table index associated with this column.
     *
     * @return AbstractIndex
     * @throws SchemaException
     */
    public function index()
    {
        return $this->table->index($this->name);
    }

    /**
     * Create/get table index associated with this column. Index type will be forced as UNIQUE.
     *
     * @return AbstractIndex
     * @throws SchemaException
     */
    public function unique()
    {
        return $this->table->unique($this->name);
    }

    /**
     * Create/get foreign key schema associated with column and referenced foreign table and column.
     * Make sure local and outer column types are identical.
     *
     * @param string $table  Foreign table name.
     * @param string $column Foreign column name (id by default).
     * @return AbstractReference
     * @throws SchemaException
     */
    public function references($table, $column = 'id')
    {
        if ($this->phpType() != self::INT) {
            throw new SchemaException(
                "Only numeric types can be defined with foreign key constraint."
            );
        }

        return $this->table->foreign($this->name)->references($table, $column);
    }

    /**
     * Schedule column drop when parent table schema will be saved.
     */
    public function drop()
    {
        $this->table->dropColumn($this->getName());
    }

    /**
     * Must compare two instances of AbstractColumn.
     *
     * @param AbstractColumn $original
     * @return bool
     */
    public function compare(AbstractColumn $original)
    {
        if ($this == $original) {
            return true;
        }

        $columnVars = get_object_vars($this);
        $dbColumnVars = get_object_vars($original);

        $difference = [];
        foreach ($columnVars as $name => $value) {
            //Default values has to compared using type-casted value
            if ($name == 'defaultValue') {
                if ($this->getDefaultValue() != $original->getDefaultValue()) {
                    $difference[] = $name;
                }

                continue;
            }

            if ($value != $dbColumnVars[$name]) {
                $difference[] = $name;
            }
        }

        return empty($difference);
    }

    /**
     * Compile column create statement.
     *
     * @return string
     */
    public function sqlStatement()
    {
        $statement = [$this->getName(true), $this->type];

        if ($this->abstractType() == 'enum') {
            //Enum specific column options
            if (!empty($enumDefinition = $this->prepareEnum())) {
                $statement[] = $enumDefinition;
            }
        } elseif (!empty($this->precision)) {
            $statement[] = "({$this->precision}, {$this->scale})";
        } elseif (!empty($this->size)) {
            $statement[] = "({$this->size})";
        }

        $statement[] = $this->nullable ? 'NULL' : 'NOT NULL';

        if ($this->defaultValue !== null) {
            $statement[] = "DEFAULT {$this->prepareDefault()}";
        }

        return join(' ', $statement);
    }

    /**
     * Shortcut for AbstractColumn->type() method.
     *
     * @param string $type      Abstract type.
     * @param array  $arguments Not used.
     * @return $this
     */
    public function __call($type, array $arguments = [])
    {
        return $this->type($type);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->sqlStatement();
    }

    /**
     * Simplified way to dump information.
     *
     * @return object
     */
    public function __debugInfo()
    {
        $column = [
            'name' => $this->name,
            'type' => [
                'database' => $this->type,
                'schema'   => $this->abstractType(),
                'php'      => $this->phpType()
            ]
        ];

        if (!empty($this->size)) {
            $column['size'] = $this->size;
        }

        if ($this->nullable) {
            $column['nullable'] = true;
        }

        if ($this->defaultValue !== null) {
            $column['defaultValue'] = $this->getDefaultValue();
        }

        if ($this->abstractType() == 'enum') {
            $column['enumValues'] = $this->enumValues;
        }

        if ($this->abstractType() == 'decimal') {
            $column['precision'] = $this->precision;
            $column['scale'] = $this->scale;
        }

        return (object)$column;
    }

    /**
     * Parse driver specific schema information and populate schema fields.
     *
     * @param mixed $schema
     * @throws SchemaException
     */
    abstract protected function resolveSchema($schema);

    /**
     * Get database specific enum type definition options.
     *
     * @return string.
     */
    protected function prepareEnum()
    {
        $enumValues = [];
        foreach ($this->enumValues as $value) {
            $enumValues[] = $this->table->driver()->getPDO()->quote($value);
        }

        if (!empty($enumValues)) {
            return '(' . join(', ', $enumValues) . ')';
        }

        return '';
    }

    /**
     * Must return driver specific default value.
     *
     * @return string
     */
    protected function prepareDefault()
    {
        if (($defaultValue = $this->getDefaultValue()) === null) {
            return 'NULL';
        }

        if ($defaultValue instanceof SQLFragmentInterface) {
            return $defaultValue->sqlStatement();
        }

        if ($this->phpType() == 'bool') {
            return $defaultValue ? 'TRUE' : 'FALSE';
        }

        if ($this->phpType() == 'float') {
            return sprintf('%F', $defaultValue);
        }

        if ($this->phpType() == 'int') {
            return $defaultValue;
        }

        return $this->table->driver()->getPDO()->quote($defaultValue);
    }
}