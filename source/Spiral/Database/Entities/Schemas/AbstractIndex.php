<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Schemas\IndexInterface;

/**
 * Abstract index schema with read (see IndexInterface) and write abilities. Must be implemented
 * by driver to support DBMS specific syntax and creation rules.
 */
abstract class AbstractIndex implements IndexInterface
{
    /**
     * Index types.
     */
    const NORMAL = 'INDEX';
    const UNIQUE = 'UNIQUE';

    /**
     * Index name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Index type, by default NORMAL and UNIQUE indexes supported, additional types can be implemented
     * on database driver level.
     *
     * @var int
     */
    protected $type = self::NORMAL;

    /**
     * Columns used to form index.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * @invisible
     * @var AbstractTable
     */
    protected $table = null;

    /**
     * @param AbstractTable $table
     * @param string        $name
     * @param mixed         $schema Driver specific index information.
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
        $name = $this->name;
        if (empty($this->name)) {
            $name = $this->table->getName() . '_index_' . join('_',
                    $this->columns) . '_' . uniqid();
        }

        if (strlen($name) > 64) {
            //Many dbs has limitations on identifier length
            $name = md5($name);
        }

        return $quoted ? $this->table->driver()->identifier($name) : $name;
    }

    /**
     * {@inheritdoc}
     */
    public function isUnique()
    {
        return $this->type == self::UNIQUE;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Set index name. It's recommended to use AbstractTable->renameIndex() to safely rename indexes.
     *
     * @param string $name New index name.
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Change index type and behaviour to unique/non-unique state.
     *
     * @param bool $unique
     * @return $this
     */
    public function unique($unique = true)
    {
        $this->type = $unique ? self::UNIQUE : self::NORMAL;

        return $this;
    }

    /**
     * Change set of index forming columns. Method must support both array and string parameters.
     *
     * Example:
     * $index->columns('key');
     * $index->columns('key', 'key2');
     * $index->columns(array('key', 'key2'));
     *
     * @param string|array $columns Columns array or comma separated list of parameters.
     * @return $this
     */
    public function columns($columns)
    {
        if (!is_array($columns)) {
            $columns = func_get_args();
        }

        $this->columns = $columns;

        return $this;
    }

    /**
     * Schedule index drop when parent table schema will be saved.
     */
    public function drop()
    {
        $this->table->dropIndex($this->getName());
    }

    /**
     * Must compare two instances of AbstractIndex.
     *
     * @param AbstractIndex $original
     * @return bool
     */
    public function compare(AbstractIndex $original)
    {
        return $this == $original;
    }

    /**
     * Index sql creation syntax.
     *
     * @param bool $includeTable Include table ON statement (not required for inline index creation).
     * @return string
     */
    public function sqlStatement($includeTable = true)
    {
        $statement = [];
        $statement[] = $this->type . ($this->type == self::UNIQUE ? ' INDEX' : '');
        $statement[] = $this->getName(true);

        if ($includeTable) {
            $statement[] = 'ON ' . $this->table->getName(true);
        }

        $statement[] = '(' . join(', ', array_map(
                [$this->table->driver(), 'identifier'],
                $this->columns
            )) . ')';

        return join(' ', $statement);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->sqlStatement();
    }

    /**
     * Parse driver specific schema information and populate schema fields.
     *
     * @param mixed $schema
     * @throws SchemaException
     */
    abstract protected function resolveSchema($schema);
}