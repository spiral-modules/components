<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas\Definitions;

use Spiral\Database\Schemas\ColumnInterface;
use Spiral\Database\Schemas\Prototypes\AbstractTable;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Schemas\SchemaInterface;

/**
 * Defines set of properties relates to source or target model/record. Including class, location
 * in database, role name, primary key (if any).
 *
 * Attention, at this moment relations do not support multiple primary keys.
 */
final class RelationContext
{
    /**
     * Record class (source or target).
     *
     * @var string
     */
    private $class;

    /**
     * Role name.
     *
     * @var string
     */
    private $role;

    /**
     * @var string|null
     */
    private $database;

    /**
     * @var string
     */
    private $table;

    /**
     * @invisible
     *
     * @var AbstractTable
     */
    private $schema;

    /**
     * @param SchemaInterface $schema
     * @param AbstractTable   $table
     *
     * @return RelationContext
     *
     * @throws SchemaException
     */
    public static function createContent(SchemaInterface $schema, AbstractTable $table): self
    {
        $context = new self();
        $context->class = $schema->getClass();
        $context->database = $schema->getDatabase();
        $context->table = $schema->getTable();
        $context->role = $schema->getRole();

        $context->schema = $table;

        return $context;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return null|string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return AbstractTable
     */
    public function getSchema(): AbstractTable
    {
        return clone $this->schema;
    }

    /**
     * @return array
     */
    public function columnNames(): array
    {
        $names = [];
        foreach ($this->schema->getColumns() as $column) {
            $names[] = $column->getName();
        }

        return $names;
    }

    /**
     * @return null|ColumnInterface
     */
    public function getPrimary()
    {
        $primaryKeys = $this->schema->getPrimaryKeys();
        if (count($primaryKeys) == 1) {
            return clone $this->schema->column($primaryKeys[0]);
        }

        /*
         * Table either have complex primary key or no primary keys.
         */

        return null;
    }
}