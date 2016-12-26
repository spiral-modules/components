<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Entities;

use Spiral\Models\PublishableInterface;
use Spiral\Models\Traits\SolidableTrait;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\Exceptions\CompositorException;
use Spiral\ODM\ODMInterface;

/**
 * Provides ability to composite multiple documents in a form of array.
 */
class DocumentCompositor implements
    CompositableInterface,
    PublishableInterface,
    \Countable,
    \IteratorAggregate
{
    use SolidableTrait;

    /**
     * Lazy conversion from array to CompositableInterface (ie DocumentEntity).
     *
     * @var CompositableInterface[]
     */
    private $entities = [];

    /**
     * Set of atomic operation applied to whole composition set.
     *
     * @var array
     */
    protected $atomics = [];

    /**
     * Indication that composition state was changed directly (via setValue).
     *
     * @var bool
     */
    protected $changed = false;

    /**
     * @var string
     */
    protected $class;

    /**
     * @invisible
     * @var ODMInterface
     */
    protected $odm;

    /**
     * @param string                        $class
     * @param array|CompositableInterface[] $data
     * @param ODMInterface                  $odm
     */
    public function __construct(string $class, array $data, ODMInterface $odm)
    {
        $this->class = $class;
        $this->odm = $odm;

        //todo: construct entities here
        $this->entities = $this->instantiateEntities($data);
    }

    /**
     * Get primary composition class.
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    //atomics and other things :)

    //has
    //find
    //findOne
    //push
    //pull
    //add

    /**
     * {@inheritdoc}
     */
    public function mountValue($data)
    {
        //Manually altered compositions must always end in solid state
        $this->solidState = $this->changed = true;

        //Flushing existed entities
        //todo: double check
        $this->entities = $this->instantiateEntities($data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchValue(): array
    {
        $result = [];
        foreach ($this->entities as $entity) {
            $result[] = $entity->fetchValue();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function hasUpdates(): bool
    {
        if ($this->changed || !empty($this->atomics)) {
            return true;
        }

        foreach ($this->entities as $entity) {
            if ($entity->hasUpdates()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
        $this->changed = false;
        $this->atomics = [];

        foreach ($this->entities as $entity) {
            $entity->flushUpdates();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildAtomics(string $container = ''): array
    {
        return [];
    }

    /**
     * Packs only public values of all nested documents.
     *
     * @return array
     */
    public function publicFields(): array
    {
        $result = [];
        foreach ($this->entities as $entity) {
            if ($entity instanceof PublishableInterface) {
                $result[] = $entity->publicFields();
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->publicFields();
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->entities);
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entities);
    }

    /**
     * Cloning will be called when object will be embedded into another document.
     */
    public function __clone()
    {
        $this->solidState = true;
        $this->changed = false;
        $this->atomics = [];

        //De-serialize composition in order to ensure that all compositions are recreated
        $this->mountValue($this->fetchValue());
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            //todo: pack entities
            'entities' => $this->entities,
            'atomics'  => $this->buildAtomics('@compositor')
        ];
    }

    /**
     * Assert that given entity supported by composition.
     *
     * @param mixed $entity
     *
     * @throws CompositorException
     */
    protected function assertSupported($entity)
    {
        if (!is_object($entity) || !is_a($entity, $this->class)) {
            throw new CompositorException(sprintf(
                "Only instances of '%s' supported, %s given",
                $this->class,
                is_object($entity) ? get_class($entity) : gettype($entity)
            ));
        }
    }

    /**
     * Instantiate every entity in composition.
     *
     * @param array $data
     *
     * @return CompositableInterface[]
     *
     * @throws CompositorException
     */
    protected function instantiateEntities(array $data): array
    {
        return $data;
    }
}