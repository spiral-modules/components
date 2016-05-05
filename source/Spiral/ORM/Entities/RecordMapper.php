<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities;

use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Table;
use Spiral\ORM\ORMInterface;

class RecordMapper
{
    /**
     * Related ODM model.
     *
     * @var string
     */
    private $class = '';

    /**
     * @var ORMInterface
     */
    protected $orm = null;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct($class, ORMInterface $orm)
    {
        $this->class = $class;
        $this->orm = $orm;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->orm->schema($this->class, ORMInterface::M_TABLE);
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->orm->schema($this->class, ORMInterface::M_DB);
    }

    /**
     * Insert new row into dbal table.
     *
     * @param array $data
     * @return bool|\MongoId|null Null if failure.
     */
    public function insert(array $data)
    {
        //todo: implement
    }

    /**
     * Update row in related table.
     *
     * @param array $updates DBAL updates, must be provided in a form of column => value and can
     *                       include expressions.
     * @return bool
     */
    public function update(array $updates)
    {
        //todo: implement
    }

    /**
     * Delete document from related collection.
     *
     * @return array|bool
     */
    public function delete()
    {
        //todo: implement
    }

    /**
     * @return Table
     */
    private function dbalTable()
    {
        return $this->dbalDatabase()->table($this->getTable());
    }

    /**
     * @return Database
     */
    private function dbalDatabase()
    {
        return $this->orm->database($this->getDatabase());
    }
}