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
 * Update data CAN be modified by parent commands using context.
 *
 * This is conditional command, it would not be executed when no fields are given!
 */
class UpdateCommand extends TableCommand implements ContextualCommandInterface
{
    use ContextTrait;

    /**
     * Where conditions (short where format).
     *
     * @var array
     */
    private $where = [];

    /**
     * Primary key value (from previous command), promised on execution!.
     *
     * @var mixed
     */
    private $primaryKey;

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
     * @param mixed $primaryKey
     */
    public function __construct(Table $table, array $where, array $values = [], $primaryKey = null)
    {
        parent::__construct($table);
        $this->where = $where;
        $this->values = $values;
        $this->primaryKey = $primaryKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver()
    {
        if (empty($this->context) && empty($this->values)) {
            //Nothing to do
            return null;
        }

        return $this->table->getDatabase()->getDriver();
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return empty($this->values) && empty($this->context);
    }

    /**
     * @param array $where
     */
    public function setWhere(array $where)
    {
        $this->where = $where;
    }

    /**
     * @return array
     */
    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * Promised on execute.
     *
     * @return mixed|null
     */
    public function primaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @param mixed $primaryKey
     */
    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }

    /**
     * Inserting data into associated table.
     */
    public function execute()
    {
        if (!$this->isEmpty()) {
            $this->table->update($this->context + $this->values, $this->where)->run();
        }

        parent::execute();
    }
}