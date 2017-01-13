<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

class UpdateCommand extends TableCommand
{
    /**
     * Where conditions (short where format).
     *
     * @var array
     */
    private $where = [];

    private $context = [];

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
        $this->table->update($this->context, $this->where)->run();
        parent::execute();
    }
}