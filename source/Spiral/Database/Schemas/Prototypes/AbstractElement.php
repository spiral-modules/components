<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Schemas\Prototypes;

use Spiral\Database\Schemas\AbstractTable;
use Spiral\Database\Exceptions\SchemaException;

/**
 * Aggregates common functionality for columns, indexes and foreign key schemas.
 */
abstract class AbstractElement
{
    /**
     * Declaration flag used to create full table diff.
     *
     * @var bool
     */
    protected $declared = false;

    /**
     * Element name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * @invisible
     *
     * @var AbstractTable
     */
    protected $table = null;

    /**
     * @param AbstractTable $table
     * @param string        $name
     * @param mixed         $schema Driver specific schema information.
     */
    public function __construct(AbstractTable $table, string $name, $schema = null)
    {
        $this->name = $name;
        $this->table = $table;

        if (!empty($schema)) {
            $this->resolveSchema($schema);
        }
    }

    /**
     * Associated table.
     *
     * @return AbstractTable
     */
    public function getTable(): AbstractTable
    {
        return $this->table;
    }

    /**
     * Set element name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get element name. Can automatically quote such name.
     *
     * @param bool $quoted
     *
     * @return string
     */
    public function getName(bool $quoted = false): string
    {
        if ($quoted) {
            return $this->table->getDriver()->identifier($this->name);
        }

        return $this->name;
    }

    /**
     * @return bool
     */
    public function isDeclared(): bool
    {
        return $this->declared;
    }

    /**
     * Mark schema entity as declared, it will be kept in final diff.
     *
     * @param bool $declared
     *
     * @return $this
     */
    public function declared(bool $declared = true)
    {
        $this->declared = $declared;

        return $this;
    }

    /**
     * Element creation/definition syntax.
     *
     * @return string
     */
    abstract public function sqlStatement(): string;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->sqlStatement();
    }

    /**
     * Parse driver specific schema information and populate element data.
     *
     * @param mixed $schema
     *
     * @throws SchemaException
     */
    abstract protected function resolveSchema($schema);
}
