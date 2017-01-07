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
class RecordLoader extends AbstractLoader
{
    /**
     * Root loader always define primary SelectQuery.
     *
     * @var SelectQuery
     */
    private $select;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(string $class, ORMInterface $orm)
    {
        parent::__construct($class, $orm);

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

    /**
     * {@inheritdoc}
     */
    public function fetchData(): array
    {
        dump($this->select);

        return $this->select->fetchAll();
    }
}