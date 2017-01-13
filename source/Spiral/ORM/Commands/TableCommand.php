<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\Database\Entities\Driver;
use Spiral\Database\Entities\Table;
use Spiral\ORM\SQLCommandInterface;

class TableCommand extends AbstractCommand implements SQLCommandInterface
{
    /**
     * Table to be updated.
     *
     * @var Table
     */
    protected $table;

    /**
     * @param Table $table
     */
    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver(): Driver
    {
        return $this->table->getDatabase()->getDriver();
    }
}