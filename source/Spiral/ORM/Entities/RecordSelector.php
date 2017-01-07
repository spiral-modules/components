<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

use Spiral\Core\Component;
use Spiral\ORM\Entities\Loaders\RootLoader;
use Spiral\ORM\ORMInterface;

/**
 * Attention, RecordSelector DOES NOT extends QueryBuilder but mocks it!
 */
class RecordSelector extends Component
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
     * @var RootLoader
     */
    private $loader;

    /**
     * @param string       $class
     * @param ORMInterface $orm
     */
    public function __construct(string $class, ORMInterface $orm)
    {
        $this->class = $class;
        $this->orm = $orm;

        $this->loader = new RootLoader($class, $orm);
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