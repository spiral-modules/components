<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Drivers\MySQL\Schemas;

use Spiral\Database\DatabaseManager;
use Spiral\Database\Drivers\MySQL\MySQLDriver;
use Spiral\Database\Entities\Schemas\AbstractColumn;
use Spiral\Database\Injections\Fragment;

/**
 * MySQL column schema.
 */
class ColumnSchema extends AbstractColumn
{
    /**
     * {@inheritdoc}
     */
    protected $mapping = [
        //Primary sequences
        'primary'     => [
            'type'          => 'int',
            'size'          => 11,
            'autoIncrement' => true,
            'nullable'      => false
        ],
        'bigPrimary'  => [
            'type'          => 'bigint',
            'size'          => 20,
            'autoIncrement' => true,
            'nullable'      => false
        ],
        //Enum type (mapped via method)
        'enum'        => 'enum',
        //Logical types
        'boolean'     => ['type' => 'tinyint', 'size' => 1],
        //Integer types (size can always be changed with size method), longInteger has method alias
        //bigInteger
        'integer'     => ['type' => 'int', 'size' => 11],
        'tinyInteger' => ['type' => 'tinyint', 'size' => 4],
        'bigInteger'  => ['type' => 'bigint', 'size' => 20],
        //String with specified length (mapped via method)
        'string'      => 'varchar',
        //Generic types
        'text'        => 'text',
        'tinyText'    => 'tinytext',
        'longText'    => 'longtext',
        //Real types
        'double'      => 'double',
        'float'       => 'float',
        //Decimal type (mapped via method)
        'decimal'     => 'decimal',
        //Date and Time types
        'datetime'    => 'datetime',
        'date'        => 'date',
        'time'        => 'time',
        'timestamp'   => [
            'type'         => 'timestamp',
            'defaultValue' => MySQLDriver::DEFAULT_DATETIME
        ],
        //Binary types
        'binary'      => 'blob',
        'tinyBinary'  => 'tinyblob',
        'longBinary'  => 'longblob',
        //Additional types
        'json'        => 'text'
    ];

    /**
     * {@inheritdoc}
     */
    protected $reverseMapping = [
        'primary'     => [['type' => 'int', 'autoIncrement' => true]],
        'bigPrimary'  => ['serial', ['type' => 'bigint', 'autoIncrement' => true]],
        'enum'        => ['enum'],
        'boolean'     => ['bool', 'boolean', ['type' => 'tinyint', 'size' => 1]],
        'integer'     => ['int', 'integer', 'smallint', 'mediumint'],
        'tinyInteger' => ['tinyint'],
        'bigInteger'  => ['bigint'],
        'string'      => ['varchar', 'char'],
        'text'        => ['text', 'mediumtext'],
        'tinyText'    => ['tinytext'],
        'longText'    => ['longtext'],
        'double'      => ['double'],
        'float'       => ['float', 'real'],
        'decimal'     => ['decimal'],
        'datetime'    => ['datetime'],
        'date'        => ['date'],
        'time'        => ['time'],
        'timestamp'   => ['timestamp'],
        'binary'      => ['blob', 'binary', 'varbinary'],
        'tinyBinary'  => ['tinyblob'],
        'longBinary'  => ['longblob']
    ];

    /**
     * List of types forbids default value set.
     *
     * @var array
     */
    protected $forbiddenDefaults = [
        'text',
        'mediumtext',
        'tinytext',
        'longtext',
        'blog',
        'tinyblob',
        'longblob'
    ];

    /**
     * Column is auto incremental.
     *
     * @var bool
     */
    protected $autoIncrement = false;

    /**
     * {@inheritdoc}
     */
    public function getDefaultValue()
    {
        $defaultValue = parent::getDefaultValue();

        if (in_array($this->type, $this->forbiddenDefaults)) {
            return null;
        }

        return $defaultValue;
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement()
    {
        $defaultValue = $this->defaultValue;
        if (in_array($this->type, $this->forbiddenDefaults)) {
            //Flushing default value for forbidden types
            $this->defaultValue = null;
        }

        $statement = parent::sqlStatement();

        $this->defaultValue = $defaultValue;
        if ($this->autoIncrement) {
            return "{$statement} AUTO_INCREMENT";
        }

        return $statement;
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveSchema($schema)
    {
        $this->type = $schema['Type'];
        $this->nullable = strtolower($schema['Null']) == 'yes';
        $this->defaultValue = $schema['Default'];
        $this->autoIncrement = stripos($schema['Extra'], 'auto_increment') !== false;

        if (!preg_match('/^(?P<type>[a-z]+)(?:\((?P<options>[^\)]+)\))?/', $this->type, $matches)) {
            return;
        }

        $this->type = $matches['type'];

        $options = null;
        if (!empty($matches['options'])) {
            $options = $matches['options'];
        }

        if ($this->abstractType() == 'enum') {
            $this->enumValues = array_map(function ($value) {
                return trim($value, $value[0]);
            }, explode(',', $options));

            return;
        }

        $options = array_map(function ($value) {
            return intval($value);
        }, explode(',', $options));

        if (count($options) > 1) {
            list($this->precision, $this->scale) = $options;
        } elseif (!empty($options)) {
            $this->size = $options[0];
        }

        //Default value conversions
        if ($this->type == 'bit' && $this->hasDefaultValue()) {
            //Cutting b\ and '
            $this->defaultValue = new Fragment($this->defaultValue);
        }

        if ($this->abstractType() == 'timestamp' && $this->defaultValue == '0000-00-00 00:00:00') {
            $this->defaultValue = MySqlDriver::DEFAULT_DATETIME;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareDefault()
    {
        if ($this->abstractType() == 'timestamp' && is_scalar($this->defaultValue)) {
            if (is_numeric($this->defaultValue)) {
                //Nothing to do
                return (int)$this->defaultValue;
            }

            $datetime = new \DateTime($this->defaultValue,
                new \DateTimeZone(DatabaseManager::DEFAULT_TIMEZONE));

            return $datetime->getTimestamp();
        }

        return parent::prepareDefault();
    }
}