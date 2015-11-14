<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities;

use Spiral\Core\Component;
use Spiral\Database\Entities\Schemas\AbstractTable;

class TableBus extends Component
{
    /**
     * @var AbstractTable[]
     */
    private $tables = [];

    /**
     * @param AbstractTable[] $tables
     */
    public function __construct(array $tables)
    {
        $this->tables = $tables;
    }

    /**
     * @return AbstractTable[]
     */
    public function getTables()
    {

    }
}