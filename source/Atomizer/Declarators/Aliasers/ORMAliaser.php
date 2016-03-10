<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Atomizer\Declarators\Aliasers;

use Spiral\Atomizer\Declarators\AliaserInterface;
use Spiral\Database\Entities\Schemas\AbstractTable;
use Spiral\ORM\Entities\SchemaBuilder;

class ORMAliaser implements AliaserInterface
{
    /**
     * @var SchemaBuilder
     */
    private $builder;

    /**
     * @param SchemaBuilder $builder
     */
    public function __construct(SchemaBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function getTable(AbstractTable $table)
    {
        return $this->builder->tableAlias($table);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(AbstractTable $table)
    {
        return $this->builder->databaseAlias($table);
    }
}