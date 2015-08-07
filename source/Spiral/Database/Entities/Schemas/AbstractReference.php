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
use Spiral\Database\Schemas\ReferenceInterface;

/**
 * Abstract foreign schema with read (see ReferenceInterface) and write abilities. Must be implemented
 * by driver to support DBMS specific syntax and creation rules.
 */
abstract class AbstractReference implements ReferenceInterface
{
    /**
     * Constraint name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Local column name (key name).
     *
     * @var string
     */
    protected $column = '';

    /**
     * Referenced table name (including prefix).
     *
     * @var string
     */
    protected $foreignTable = '';

    /**
     * Linked foreign key name (foreign column).
     *
     * @var string
     */
    protected $foreignKey = '';

    /**
     * Action on foreign column value deletion.
     *
     * @var string
     */
    protected $deleteRule = self::NO_ACTION;

    /**
     * Action on foreign column value update.
     *
     * @var string
     */
    protected $updateRule = self::NO_ACTION;

    /**
     * @invisible
     * @var AbstractTable
     */
    protected $table = null;

    /**
     * @param AbstractTable $table
     * @param string        $name
     * @param mixed         $schema
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
            $name = $this->table->getName() . '_foreign_' . $this->column . '_' . uniqid();
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
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignTable()
    {
        return $this->foreignTable;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeleteRule()
    {
        return $this->deleteRule;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdateRule()
    {
        return $this->updateRule;
    }

    /**
     * Set local column name foreign key relates to. Make sure column type is the same as foreign
     * column one.
     *
     * @param string $column
     * @return $this
     */
    public function column($column)
    {
        $this->column = $column;

        return $this;
    }

    /**
     * Set foreign table name and key local column must reference to. Make sure local and foreign
     * column types are identical.
     *
     * @param string $table  Foreign table name without database prefix (will be added automatically).
     * @param string $column Foreign key name (id by default).
     * @return $this
     */
    public function references($table, $column = 'id')
    {
        $this->foreignTable = $this->table->getTablePrefix() . $table;
        $this->foreignKey = $column;

        return $this;
    }

    /**
     * Set foreign key delete behaviour.
     *
     * @param string $rule Possible values: NO ACTION, CASCADE, etc (driver specific).
     * @return $this
     */
    public function onDelete($rule = self::NO_ACTION)
    {
        $this->deleteRule = strtoupper($rule);

        return $this;
    }

    /**
     * Set foreign key update behaviour.
     *
     * @param string $rule Possible values: NO ACTION, CASCADE, etc (driver specific).
     * @return $this
     */
    public function onUpdate($rule = self::NO_ACTION)
    {
        $this->updateRule = strtoupper($rule);

        return $this;
    }

    /**
     * Schedule foreign key drop when parent table schema will be saved.
     */
    public function drop()
    {
        $this->table->dropForeign($this->getColumn());
    }

    /**
     * Must compare two instances of AbstractReference.
     *
     * @param AbstractReference $original
     * @return bool
     */
    public function compare(AbstractReference $original)
    {
        return $this == $original;
    }

    /**
     * Foreign key creation syntax.
     *
     * @return string
     */
    public function sqlStatement()
    {
        $statement = [];

        $statement[] = 'CONSTRAINT';
        $statement[] = $this->getName(true);
        $statement[] = 'FOREIGN KEY';
        $statement[] = '(' . $this->table->driver()->identifier($this->column) . ')';

        $statement[] = 'REFERENCES ' . $this->table->driver()->identifier($this->foreignTable);
        $statement[] = '(' . $this->table->driver()->identifier($this->foreignKey) . ')';

        $statement[] = "ON DELETE {$this->deleteRule}";
        $statement[] = "ON UPDATE {$this->updateRule}";

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