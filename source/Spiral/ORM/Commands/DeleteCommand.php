<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\Database\Entities\Table;

class DeleteCommand extends TableCommand
{
    /**
     * Where conditions (short where format).
     *
     * @var array
     */
    private $where = [];

    /**
     * @param Table $table
     * @param mixed $where
     */
    public function __construct(Table $table, array $where)
    {
        parent::__construct($table);
        $this->where = $where;
    }

    /**
     * @param array $where
     */
    public function setWhere(array $where)
    {
        $this->where = $where;
    }

    /**
     * Inserting data into associated table.
     */
    public function execute()
    {
        $this->table->delete($this->where)->run();
        parent::execute();
    }
}