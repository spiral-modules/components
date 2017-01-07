<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\Database\Builders\SelectQuery;
use Spiral\ORM\ORMInterface;

/**
 * Primary ORM loader. Loader wraps at top of select query in order to modify it's conditions, joins
 * and etc based on nested loaders.
 */
class RootLoader
{
    /**
     * @var string
     */
    private $class;

    /**
     * @invisible
     * @var ORMInterface
     */
    private $orm;

    /**
     * @var SelectQuery
     */
    private $select;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(string $class, ORMInterface $orm)
    {
        $this->class = $class;
        $this->orm = $orm;

        //Getting our initial select query
        $this->select = $orm->table($class)->select();
    }

    /**
     * @return SelectQuery
     */
    public function selectQuery(): SelectQuery
    {
        return $this->select;
    }

    public function fetchData(): array
    {
        return $this->select->fetchAll();
        return [];
    }
}