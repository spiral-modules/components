<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

use Spiral\Core\Component;
use Spiral\Database\Entities\Table;
use Spiral\ORM\ORMInterface;

/**
 * Attention, RecordSelector DOES NOT extends QueryBuilder but mocks it!
 */
class RecordSelector extends Component
{
    /**
     * @var Table
     */
    private $table;

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
     * @param Table        $table
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(Table $table, string $class, ORMInterface $orm)
    {
        $this->table = $table;
        $this->class = $class;
        $this->orm = $orm;
    }

    /**
     * Get associated class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function fetchData()
    {
        $selectQuery = $this->table->select();

        dump($selectQuery);
    }

    /**
     * @return \Interop\Container\ContainerInterface|null
     */
    protected function iocContainer()
    {
        if ($this->orm instanceof Component) {
            //Working inside ORM container scope
            return $this->orm->iocContainer();
        }

        return parent::iocContainer();
    }
}