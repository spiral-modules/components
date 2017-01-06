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
     * @var string|null
     */
    private $database;

    /**
     * @var string
     */
    private $table;

    /**
     * Role name.
     *
     * @var string
     */
    private $role;

    /**
     * Default column used to identify model.
     *
     * @var ColumnInterface|null
     */
    private $primary;

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

        $primaryKeys = $table->getPrimaryKeys();
        if (count($primaryKeys) == 1) {
            $context->primary = clone $table->column($primaryKeys[0]);
        }

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
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return null|ColumnInterface
     */
    public function getPrimary()
    {
        if (empty($this->primary)) {
            return null;
        }

        return $this->primary;
    }
}