<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Schemas;

use Spiral\Database\Schemas\Prototypes\AbstractElement;
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
     * @return self
     */
    public function declared(bool $declared = true): AbstractReference
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
     * @return self
     */
    public function setName(string $name): AbstractReference
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
    public function getName(bool $quoted = false): string
    {
        if (empty($this->name)) {
            $this->setName($this->generateName());
        }

        return parent::getName($quoted);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignTable(): string
    {
        return $this->foreignTable;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeleteRule(): string
    {
        return $this->deleteRule;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdateRule(): string
    {
        return $this->updateRule;
    }

    /**
     * Set local column name foreign key relates to. Make sure column type is the same as foreign
     * column one.
     *
     * @param string $column
     *
     * @return self
     */
    public function column(string $column): AbstractReference
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
     * @return self
     */
    public function references(string $table, string $column = 'id'): AbstractReference
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
     * @return self
     */
    public function onDelete(string $rule = self::NO_ACTION): AbstractReference
    {
        $this->deleteRule = strtoupper($rule);

        return $this;
    }

    /**
     * Set foreign key update behaviour.
     *
     * @param string $rule Possible values: NO ACTION, CASCADE, etc (driver specific).
     *
     * @return self
     */
    public function onUpdate(string $rule = self::NO_ACTION): AbstractReference
    {
        $this->updateRule = strtoupper($rule);

        return $this;
    }

    /**
     * Foreign key creation syntax.
     *
     * @return string
     */
    public function sqlStatement(): string
    {
        $statement = [];

        $statement[] = 'CONSTRAINT';
        $statement[] = $this->getName(true);
        $statement[] = 'FOREIGN KEY';
        $statement[] = '(' . $this->table->getDriver()->identifier($this->column) . ')';

        $statement[] = 'REFERENCES ' . $this->table->getDriver()->identifier($this->foreignTable);
        $statement[] = '(' . $this->table->getDriver()->identifier($this->foreignKey) . ')';

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
    public function compare(self $initial): bool
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
    protected function generateName(): string
    {
        $name = $this->table->getName() . '_foreign_' . $this->column . '_' . uniqid();

        if (strlen($name) > 64) {
            //Many dbs has limitations on identifier length
            $name = md5($name);
        }

        return $name;
    }
}
