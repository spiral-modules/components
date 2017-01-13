<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\Database\Entities\Table;

class UpdateCommand extends TableCommand
{
    /**
     * Where conditions (short where format).
     *
     * @var array
     */
    private $where = [];

    /**
     * Columns to be updated.
     *
     * @var array
     */
    private $values = [];

    /**
     * UpdateCommand constructor.
     *
     * @param Table $table
     * @param array $where
     * @param array $values
     */
    public function __construct(Table $table, array $where, array $values)
    {
        parent::__construct($table);
        $this->where = $where;
        $this->values = $values;
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
        $this->table->update($this->values, $this->where)->run();
        parent::execute();
    }
}