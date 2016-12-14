<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Schemas\Prototypes;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Injections\Fragment;
use Spiral\Database\Injections\FragmentInterface;
use Spiral\Database\Schemas\ColumnInterface;

/**
 * Abstract column schema with read (see ColumnInterface) and write abilities. Must be implemented
 * by driver to support DBMS specific syntax and creation rules.
 *
 * Shortcuts for various column types:
 *
 * @method AbstractColumn|$this primary()
 * @method AbstractColumn|$this bigPrimary()
 * @method AbstractColumn|$this boolean()
 * @method AbstractColumn|$this integer()
 * @method AbstractColumn|$this tinyInteger()
 * @method AbstractColumn|$this bigInteger()
 * @method AbstractColumn|$this text()
 * @method AbstractColumn|$this tinyText()
 * @method AbstractColumn|$this longText()
 * @method AbstractColumn|$this double()
 * @method AbstractColumn|$this float()
 * @method AbstractColumn|$this datetime()
 * @method AbstractColumn|$this date()
 * @method AbstractColumn|$this time()
 * @method AbstractColumn|$this timestamp()
 * @method AbstractColumn|$this binary()
 * @method AbstractColumn|$this tinyBinary()
 * @method AbstractColumn|$this longBinary()
 * @method AbstractColumn|$this json()
 */
abstract class AbstractColumn extends AbstractElement implements ColumnInterface
{
    /**
     * Default datetime value.
     */
    const DATETIME_DEFAULT = '1970-01-01 00:00:00';

    /**
     * Default timestamp expression (driver specific).
     */
    const DATETIME_CURRENT = 'CURRENT_TIMESTAMP';

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
        'blob'           => 'binary',
    ];

    /**
     * Association list between abstract types and native PHP types. Every non listed type will be
     * converted into string.
     *
     * @invisible
     *
     * @var array
     */
    private $phpMapping = [
        self::INT   => ['primary', 'bigPrimary', 'integer', 'tinyInteger', 'bigInteger'],
        self::BOOL  => ['boolean'],
        self::FLOAT => ['double', 'float', 'decimal'],
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
     *
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
        'json'        => null,
    ];

    /**
     * Reverse mapping is responsible for generating abstact type based on database type and it's
     * options. Multiple database types can be mapped into one abstract type.
     *
     * @invisible
     *
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
        'json'        => [],
    ];

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
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function phpType(): string
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
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrecision(): int
    {
        return $this->precision;
    }

    /**
     * {@inheritdoc}
     */
    public function getScale(): int
    {
        return $this->scale;
    }

    /**
     * {@inheritdoc}
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * {@inheritdoc}
     */
    public function hasDefaultValue(): bool
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

        if ($this->defaultValue instanceof FragmentInterface) {
            return $this->defaultValue;
        }

        if (in_array($this->abstractType(), ['time', 'date', 'datetime', 'timestamp'])) {
            //Driver specific now expression
            if (strtolower($this->defaultValue) == strtolower(static::DATETIME_CURRENT)) {
                return new Fragment($this->defaultValue);
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
    public function getConstraints(): array
    {
        return [];
    }

    /**
     * Get allowed enum values.
     *
     * @return array
     */
    public function getEnumValues(): array
    {
        return $this->enumValues;
    }

    /**
     * DBMS specific reverse mapping must map database specific type into limited set of abstract
     * types.
     *
     * @return string
     */
    public function abstractType(): string
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

                    if ($this->{$option} != $required) {
                        continue 2;
                    }
                }

                return $type;
            }
        }

        return 'unknown';
    }

    //--- MODIFICATIONS IS HERE


    /**
     * Give column new abstract type. DBMS specific implementation must map provided type into one
     * of internal database values.
     *
     * Attention, changing type of existed columns in some databases has a lot of restrictions like
     * cross type conversions and etc. Try do not change column type without a reason.
     *
     * @param string $abstract Abstract or virtual type declared in mapping.
     *
     * @return self|$this
     *
     * @throws SchemaException
     */
    public function setType(string $abstract): AbstractColumn
    {
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        if (!isset($this->mapping[$abstract])) {
            throw new SchemaException("Undefined abstract/virtual type '{$abstract}'");
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
            $this->{$property} = $value;
        }

        return $this;
    }

    /**
     * Set column nullable/not nullable.
     *
     * @param bool $nullable
     *
     * @return self|$this
     */
    public function nullable(bool $nullable = true): AbstractColumn
    {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * Change column default value (can be forbidden for some column types).
     * Use Database::TIMESTAMP_NOW to use driver specific NOW() function.
     *
     * @param mixed $value
     *
     * @return self|$this
     */
    public function defaultValue($value): AbstractColumn
    {
        $this->defaultValue = $value;
        if (
            ($this->abstractType() == 'timestamp' || $this->abstractType() == 'datetime')
            && strtolower($value) == strtolower(self::DATETIME_DEFAULT)
        ) {
            //Making sure default value is driver specific
            $this->defaultValue = static::DATETIME_DEFAULT;
        }

        return $this;
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
     *
     * @return self
     */
    public function enum($values): AbstractColumn
    {
        $this->setType('enum');
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
     *
     * @param int $size Max string length.
     *
     * @return self|$this
     *
     * @throws SchemaException
     */
    public function string(int $size = 255): AbstractColumn
    {
        $this->setType('string');

        if ($size > 255) {
            throw new SchemaException("String size can't exceed 255 characters. Use text instead");
        }

        if ($size < 0) {
            throw new SchemaException('Invalid string length value');
        }

        $this->size = (int)$size;

        return $this;
    }

    /**
     * Set column type as decimal with specific precision and scale.
     *
     * @param int $precision
     * @param int $scale
     *
     * @return self|$this
     *
     * @throws SchemaException
     */
    public function decimal(int $precision, int $scale = 0): AbstractColumn
    {
        $this->setType('decimal');

        if (empty($precision)) {
            throw new SchemaException('Invalid precision value');
        }

        $this->precision = (int)$precision;
        $this->scale = (int)$scale;

        return $this;
    }

    /**
     * Shortcut for AbstractColumn->type() method.
     *
     * @param string $type      Abstract type.
     * @param array  $arguments Not used.
     *
     * @return self
     */
    public function __call(string $type, array $arguments = []): AbstractColumn
    {
        return $this->setType($type);
    }

    /**
     * {@inheritdoc}
     */
    public function compare(ColumnInterface $initial): bool
    {
        $normalized = clone $initial;

        if ($this == $normalized) {
            return true;
        }

        $columnVars = get_object_vars($this);
        $dbColumnVars = get_object_vars($normalized);

        $difference = [];
        foreach ($columnVars as $name => $value) {
            if ($name == 'defaultValue') {

                //Default values has to compared using type-casted value
                if ($this->getDefaultValue() != $initial->getDefaultValue()) {
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
     * {@inheritdoc}
     */
    public function sqlStatement(Driver $driver): string
    {
        $statement = [$driver->identifier($this->name), $this->type];

        if ($this->abstractType() == 'enum') {
            //Enum specific column options
            if (!empty($enumDefinition = $this->prepareEnum($driver))) {
                $statement[] = $enumDefinition;
            }
        } elseif (!empty($this->precision)) {
            $statement[] = "({$this->precision}, {$this->scale})";
        } elseif (!empty($this->size)) {
            $statement[] = "({$this->size})";
        }

        $statement[] = $this->nullable ? 'NULL' : 'NOT NULL';

        if ($this->defaultValue !== null) {
            $statement[] = "DEFAULT {$this->prepareDefault($driver)}";
        }

        return implode(' ', $statement);
    }


    /**
     * Simplified way to dump information.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $column = [
            'name' => $this->name,
            'type' => [
                'database' => $this->type,
                'schema'   => $this->abstractType(),
                'php'      => $this->phpType(),
            ],
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

        return $column;
    }

    /**
     * Get database specific enum type definition options.
     *
     * @param Driver $driver
     *
     * @return string
     */
    protected function prepareEnum(Driver $driver): string
    {
        $enumValues = [];
        foreach ($this->enumValues as $value) {
            $enumValues[] = $driver->quote($value);
        }

        if (!empty($enumValues)) {
            return '(' . implode(', ', $enumValues) . ')';
        }

        return '';
    }

    /**
     * Must return driver specific default value.
     *
     * @param Driver $driver
     *
     * @return string
     */
    protected function prepareDefault(Driver $driver): string
    {
        $defaultValue = $this->getDefaultValue();
        if ($defaultValue === null) {
            return 'NULL';
        }

        if ($defaultValue instanceof FragmentInterface) {
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

        return $driver->quote($defaultValue);
    }
}