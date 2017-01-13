<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\Database\Entities\Table;

class InsertCommand extends TableCommand
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * Set when command is executed.
     *
     * @var null|mixed
     */
    private $insertID = null;

    /**
     * @param Table $table
     * @param array $data
     */
    public function __construct(Table $table, array $data)
    {
        parent::__construct($table);
        $this->data = $data;
    }

    /**
     * Get inserted row id.
     *
     * @return mixed|null
     */
    public function getInsertID()
    {
        return $this->insertID;
    }

    /**
     * Inserting data into associated table.
     */
    public function execute()
    {
        $this->insertID = $this->table->insertOne($this->data);
        parent::execute();
    }
}
