<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities\Schemas;

use Spiral\Database\Entities\Schemas\Prototypes\AbstractElement;
use Spiral\Database\Schemas\ReferenceInterface;
use Spiral\ODM\Exceptions\SchemaException;

/**
 * Abstract foreign schema with read (see ReferenceInterface) and write abilities. Must be
 * implemented by driver to support DBMS specific syntax and creation rules.
 */
abstract class AbstractReference extends AbstractElement implements ReferenceInterface
{
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
     * Mark schema entity as declared, it will be kept in final diff.
     *
     * @param bool $declared
     *
     * @return $this
     */
    public function declared($declared = true)
    {
        if ($declared && $this->table->hasIndex([$this->column])) {
            //Some databases require index for each foreign key
            $this->table->index([$this->column])->declared(true);
        }

        return parent::declared($declared);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        if (!empty($this->name)) {
            throw new SchemaException('Changing reference name is not allowed');
        }

        return parent::setName($name);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $quoted Quote name.
     */
    public function getName($quoted = false)
    {
        if (empty($this->name)) {
            $this->setName($this->generateName());
        }

        return parent::getName($quoted);
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
     *
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
     * @param string $table  Foreign table name without database prefix (will be added
     *                       automatically).
     * @param string $column Foreign key name (id by default).
     *
     * @return $this
     */
    public function references($table, $column = 'id')
    {
        $this->foreignTable = $this->table->getPrefix() . $table;
        $this->foreignKey = $column;

        return $this;
    }

    /**
     * Set foreign key delete behaviour.
     *
     * @param string $rule Possible values: NO ACTION, CASCADE, etc (driver specific).
     *
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
     *
     * @return $this
     */
    public function onUpdate($rule = self::NO_ACTION)
    {
        $this->updateRule = strtoupper($rule);

        return $this;
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

        return implode(' ', $statement);
    }

    /**
     * Compare two elements together.
     *
     * @param self $initial
     *
     * @return bool
     */
    public function compare(self $initial)
    {
        $normalized = clone $initial;
        $normalized->declared = $this->declared;

        return $this == $normalized;
    }

    /**
     * Generate unique foreign key name.
     *
     * @return string
     */
    protected function generateName()
    {
        $name = $this->table->getName() . '_foreign_' . $this->column . '_' . uniqid();

        if (strlen($name) > 64) {
            //Many dbs has limitations on identifier length
            $name = md5($name);
        }

        return $name;
    }
}
