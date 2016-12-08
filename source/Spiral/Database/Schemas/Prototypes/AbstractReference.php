<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Schemas\Prototypes;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Exceptions\SchemaException;
use Spiral\Database\Schemas\ReferenceInterface;

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
     * {@inheritdoc}
     *
     * @param string $name
     */
    public function setName(string $name): AbstractElement
    {
        if (!empty($this->name)) {
            throw new SchemaException('Changing reference name is not allowed');
        }

        return parent::setName($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        if (empty($this->name)) {
            //Generating name on a fly
            $this->setName($this->generateName());
        }

        return parent::getName();
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

    //--- MODIFICATIONS

    /**
     * Foreign key creation syntax.
     *
     * @param Driver $driver
     *
     * @return string
     */
    public function sqlStatement(Driver $driver): string
    {
        $statement = [];

        $statement[] = 'CONSTRAINT';
        $statement[] = $driver->identifier($this->name);
        $statement[] = 'FOREIGN KEY';
        $statement[] = '(' . $driver->identifier($this->column) . ')';

        $statement[] = 'REFERENCES ' . $driver->identifier($this->foreignTable);
        $statement[] = '(' . $driver->identifier($this->foreignKey) . ')';

        $statement[] = "ON DELETE {$this->deleteRule}";
        $statement[] = "ON UPDATE {$this->updateRule}";

        return implode(' ', $statement);
    }

    /**
     * {@inheritdoc}
     */
    public function compare(ReferenceInterface $initial): bool
    {
        return $this == clone $initial;
    }

    /**
     * Generate unique foreign key name.
     *
     * @return string
     */
    protected function generateName(): string
    {
        $name = $this->table . '_foreign_' . $this->column . '_' . uniqid();

        if (strlen($name) > 64) {
            //Many dbs has limitations on identifier length
            $name = md5($name);
        }

        return $name;
    }
}