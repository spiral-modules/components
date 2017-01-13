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
    private $context = [];

    /**
     * Set when command is executed.
     *
     * @var null|mixed
     */
    private $insertID = null;

    /**
     * @param Table $table
     * @param array $context
     */
    public function __construct(Table $table, array $context)
    {
        parent::__construct($table);
        $this->context = $context;
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
        $this->insertID = $this->table->insertOne($this->context);
        parent::execute();
    }
}
