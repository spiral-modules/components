<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM\Entities;

use Spiral\Models\SourceInterface;
use Spiral\ORM\Exceptions\SelectorException;
use Spiral\ORM\Exceptions\SourceException;
use Spiral\ORM\ORM;
use Spiral\ORM\RecordEntity;
use Spiral\ORM\RecordInterface;

/**
 * Source class associated to one or multiple (default implementation) ORM models.
 */
class RecordSource extends Selector implements SourceInterface
{
    /**
     * Related record class name.
     */
    const RECORD = null;

    /**
     * @param string $class
     * @param ORM    $orm
     * @param Loader $loader
     */
    public function __construct($class = null, ORM $orm = null, Loader $loader = null)
    {
        if (empty($class)) {
            if (empty(static::RECORD)) {
                throw new SourceException("Unable to create source without associate class.");
            }

            $class = static::RECORD;
        }

        parent::__construct($class, $orm, $loader);
    }

    /**
     * Create new Record based on set of provided fields.
     *
     * @final Change static method of entity, not this one.
     * @param array $fields
     * @return RecordEntity
     */
    final public function create($fields = [])
    {
        //Letting entity to create itself (needed
        return call_user_func([$this->class, 'create'], $fields, $this->orm);
    }

    /**
     * Fetch one record from database using it's primary key. You can use INLOAD and JOIN_ONLY
     * loaders with HAS_MANY or MANY_TO_MANY relations with this method as no limit were used.
     *
     * @see findOne()
     * @param mixed $id Primary key value.
     * @return RecordEntity|null
     * @throws SelectorException
     */
    public function findByPK($id)
    {
        $primaryKey = $this->loader->getPrimaryKey();

        if (empty($primaryKey)) {
            throw new SelectorException(
                "Unable to fetch data by primary key, no primary key found."
            );
        }

        //No limit here
        return $this->findOne([$primaryKey => $id], false);
    }

    /**
     * Fetch one record from database. Attention, LIMIT statement will be used, meaning you can not
     * use loaders for HAS_MANY or MANY_TO_MANY relations with data inload (joins), use default
     * loading method.
     *
     * @see findByPK()
     * @param array $where     Selection WHERE statement.
     * @param bool  $withLimit Use limit 1.
     * @return RecordEntity|null
     */
    public function findOne(array $where = [], $withLimit = true)
    {
        if (!empty($where)) {
            $this->where($where);
        }

        $data = $this->limit($withLimit ? 1 : null)->fetchData();
        if (empty($data)) {
            return null;
        }

        //Letting ORM to do it's job
        return $this->orm->record($this->class, $data[0]);
    }
}