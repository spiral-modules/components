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

        //Instantiating composed entities (no data filtering)
        $this->entities = $this->createEntities($data, false);
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

    /**
     *
     *
     * Be aware that any added entity will be cloned in order to detach it from passed object:
     * $user->addresses->push($address);
     * $address->city = 'Minsk'; //this will have no effect of $user->addresses
     *
     * @param CompositableInterface $entity
     *
     * @return DocumentCompositor
     */
    public function push(CompositableInterface $entity): DocumentCompositor
    {
        //todo: implement
        return $this;
    }

    /**
     *
     *
     * Be aware that any added entity will be cloned in order to detach it from passed object:
     * $user->addresses->add($address);
     * $address->city = 'Minsk'; //this will have no effect of $user->addresses
     *
     * @param $entity
     *
     * @return DocumentCompositor
     */
    public function add(CompositableInterface $entity): DocumentCompositor
    {
        //todo: implement
        return $this;
    }

    public function pull($query): DocumentCompositor
    {
        //todo: implement
        return $this;
    }

    /**
     * Check if composition contains desired document or document matching query.
     *
     * Example:
     * $user->cards->has(['active' => true]);
     * $user->cards->has(new Card(...));
     *
     * @param CompositableInterface|array $query
     *
     * @return bool
     */
    public function has($query): bool
    {
        return !empty($this->findOne($query));
    }

    /**
     * Find document in composition based on given entity or matching query.
     *
     * $user->cards->findOne(['active' => true]);
     * $user->cards->findOne(new Card(...));
     *
     * @param CompositableInterface|array $query
     *
     * @return CompositableInterface|null
     */
    public function findOne($query)
    {
        $entities = $this->find($query);
        if (empty($entities)) {
            return null;
        }

        return current($entities);
    }

    /**
     * Find all entities matching given query (query can be provided in a form of
     * CompositableInterface).
     *
     * $user->cards->find(['active' => true]);
     * $user->cards->find(new Card(...));     //Attention, this will likely to return only on match
     *
     * @param CompositableInterface|array $query
     *
     * @return CompositableInterface[]
     */
    public function find($query): array
    {
        if ($query instanceof CompositableInterface) {
            //Intersecting using values
            $query = $query->fetchValue();
        }

        $result = [];
        foreach ($this->entities as $entity) {
            //Looking for entities using key intersection
            if (empty($query) || (array_intersect_assoc($entity->fetchValue(), $query) == $query)) {
                $result[] = $entity;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * Be aware that any added entity will be cloned in order to detach it from passed object:
     * $user->addresses->mountValue([$address]);
     * $address->city = 'Minsk'; //this will have no effect of $user->addresses
     */
    public function stateValue($data)
    {
        //Manually altered compositions must always end in solid state
        $this->solidState = $this->changed = true;
        $this->entities = [];

        if (!is_array($data)) {
            //Unable to initiate
            return;
        }

        //Instantiating entities (with filtering enabled)
        $this->entities = $this->createEntities($data, true);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchValue(): array
    {
        return $this->fetchValues($this->entities);
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
        if (!$this->hasUpdates()) {
            return [];
        }

        //Mongo does not support multiple operations for one field, switching to $set (make sure it's
        //reasonable)
        if ($this->solidState || count($this->atomics) > 1) {
            //We don't care about atomics in solid state
            return ['$set' => [$container => $this->fetchValue()]];
        }

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
        $this->stateValue($this->fetchValue());
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
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
                "Only instances of '%s' supported, '%s' given",
                $this->class,
                is_object($entity) ? get_class($entity) : gettype($entity)
            ));
        }
    }

    /**
     * Instantiate every entity in composition.
     *
     * @param array $data
     * @param bool  $filter
     *
     * @return CompositableInterface[]
     *
     * @throws CompositorException
     */
    private function createEntities(array $data, bool $filter = true): array
    {
        $result = [];
        foreach ($data as $item) {
            if ($item instanceof CompositableInterface) {
                $this->assertSupported($item);

                //Always clone to detach from original value
                $result[] = clone $item;
            } else {
                $result[] = $this->odm->instantiate($this->class, $item, $filter);
            }
        }

        return $result;
    }

    /**
     * Pack multiple entities into array form.
     *
     * @param CompositableInterface[] $entities
     *
     * @return array
     */
    private function fetchValues(array $entities): array
    {
        $result = [];
        foreach ($entities as $entity) {
            $result[] = $entity->fetchValue();
        }

        return $result;
    }
}