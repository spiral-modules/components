<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\Database\Builders\SelectQuery;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Record;

/**
 * Primary ORM loader. Loader wraps at top of select query in order to modify it's conditions, joins
 * and etc based on nested loaders.
 *
 * Note, in RootLoader schema represent record schema since there is no self to self relation.
 */
class RootLoader extends AbstractLoader
{
    /**
     * Root loader always define primary SelectQuery.
     *
     * @var SelectQuery
     */
    private $select;

    /**
     * @param string       $class
     * @param array        $schema Record schema for root loader.
     * @param ORMInterface $orm
     */
    public function __construct(string $class, array $schema, ORMInterface $orm)
    {
        //Constructing with truncated schema
        parent::__construct(
            $class,
            [
                Record::SH_PRIMARIES     => $schema[Record::SH_PRIMARIES],
                Record::RELATION_COLUMNS => array_keys($schema[Record::SH_DEFAULTS])
            ],
            $orm
        );

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