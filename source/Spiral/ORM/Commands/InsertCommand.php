<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Commands;

use Spiral\Database\Entities\Table;
use Spiral\ORM\Commands\Traits\ContextTrait;
use Spiral\ORM\ContextualCommandInterface;

/**
 * Inserted data CAN be modified by parent commands using context.
 */
class InsertCommand extends TableCommand implements ContextualCommandInterface
{
    use ContextTrait;

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
     * @return mixed|null
     */
    public function primaryKey()
    {
        return $this->insertID;
    }

    /**
     * Inserting data into associated table.
     */
    public function execute()
    {
        $this->insertID = $this->table->insertOne($this->context + $this->data);
        parent::execute();
    }
}
