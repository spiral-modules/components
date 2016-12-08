<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Database\Schemas\Prototypes;

/**
 * Aggregates common functionality for columns, indexes and foreign key schemas.
 */
class AbstractElement
{
    /**
     * Element name.
     *
     * @var string
     */
    protected $name = '';

    /**
     * Parent table name.
     *
     * @var string
     */
    protected $table = '';

    /**
     * @param string $table
     * @param string $name
     */
    public function __construct(string $table, string $name)
    {
        $this->name = $name;
        $this->table = $table;
    }

    /**
     * Associated table name (full name).
     *
     * @return string
     */
    public function getTable(): string
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
     * Get element name (unquoted).
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}