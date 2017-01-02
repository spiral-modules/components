<?php
/**
 * Spiral Framework, Core Components
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
 *
 * Attention, composition will be saved as one big $set operation in case when multiple atomic
 * operations applied to it (not supported by Mongo).
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
     * Push new entity to end of set.
     *
     * Be aware that any added entity will be cloned in order to detach it from passed object:
     * $user->addresses->push($address);
     * $address->city = 'Minsk'; //this will have no effect of $user->addresses
     *
     * @param CompositableInterface $entity
     *
     * @return DocumentCompositor
     *
     * @throws CompositorException When entity is invalid type.
     */
    public function push(CompositableInterface $entity): DocumentCompositor
    {
        //Detaching entity
        $entity = clone $entity;

        $this->assertSupported($entity);

        $this->entities[] = $entity;
        $this->atomics['$push'][] = $entity;

        return $this;
    }

    /**
     * Add entity to set, only one instance of document must be presented.
     *
     * Be aware that any added entity will be cloned in order to detach it from passed object:
     * $user->addresses->add($address);
     * $address->city = 'Minsk'; //this will have no effect of $user->addresses
     *
     * @param CompositableInterface $entity
     *
     * @return DocumentCompositor
     *
     * @throws CompositorException When entity is invalid type.
     */
    public function add(CompositableInterface $entity): DocumentCompositor
    {
        //Detaching entity
        $entity = clone $entity;

        $this->assertSupported($entity);

        if (!$this->has($entity)) {
            $this->entities[] = $entity;
        }

        $this->atomics['$addToSet'][] = $entity;

        return $this;
    }

    /**
     * Pull mathced entities from composition.
     *
     * $user->addresses->pull($address);
     *
     * @param CompositableInterface $entity
     *
     * @return DocumentCompositor
     */
    public function pull(CompositableInterface $entity): DocumentCompositor
    {
        //Passing true to get all entity offsets
        $targets = $this->find($entity, true);

        foreach ($targets as $offset => $target) {
            unset($this->entities[$offset]);
        }

        $this->atomics['$pull'][] = clone $entity;

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
     * @param bool                        $preserveKeys Set to true to keep original offsets.
     *
     * @return CompositableInterface[]
     */
    public function find($query, bool $preserveKeys = false): array
    {
        if ($query instanceof CompositableInterface) {
            //Intersecting using values
            $query = $query->packValue();
        }

        $result = [];
        foreach ($this->entities as $offset => $entity) {
            //Looking for entities using key intersection
            if (empty($query) || (array_intersect_assoc($entity->packValue(), $query) == $query)) {
                $result[$offset] = $entity;
            }
        }

        if (!$preserveKeys) {
            return array_values($result);
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
        $this->solidState = true;
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
    public function packValue(): array
    {
        return $this->packValues($this->entities);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $changedEntities Reference, will be set to true if any of entities changed
     *                              internally.
     */
    public function hasUpdates(bool &$changedEntities = null): bool
    {
        foreach ($this->entities as $entity) {
            if ($entity->hasUpdates()) {
                $changedEntities = true;

                return true;
            }
        }

        return !empty($this->atomics);
    }

    /**
     * {@inheritdoc}
     */
    public function flushUpdates()
    {
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
        //$changedEntities will be set to true if any of internal entities were changed directly
        if (!$this->hasUpdates($changedEntities)) {
            return [];
        }

        //Mongo does not support multiple operations for one field, switching to $set (make sure it's
        //reasonable)
        if ($this->solidState || count($this->atomics) > 1 || $changedEntities) {
            //We don't care about atomics in solid state
            return ['$set' => [$container => $this->packValue()]];
        }

        //Aggregate composition specific atomics (pull, push, addToSet) and entity specific atomics
        $atomics = [];

        //If entity already presented in any of composition atomics we are not insluding it's own
        //offset specific operations into atomics
        $excluded = [];

        foreach ($this->atomics as $operation => $items) {
            //Collect all atomics handled on
            $excluded = array_merge($excluded, $items);

            //Into array form
            $atomics[$operation][$container][$operation == '$pull' ? '$in' : '$each'] = $this->packValues($items);
        }

        //Document specific atomic operations (excluding document which are colliding with composition
        //specific operations)
        foreach ($this->entities as $offset => $entity) {
            if (!$entity->hasUpdates() || in_array($entity, $excluded)) {
                //Handler on higher level
                continue;
            }

            $atomics = array_merge(
                $atomics,
                $entity->buildAtomics((!empty($container) ? $container . '.' : '') . $offset)
            );
        }

        return $atomics;
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
        $this->atomics = [];

        //De-serialize composition in order to ensure that all compositions are recreated
        $this->stateValue($this->packValue());
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'entities' => array_values($this->entities),
            'atomics'  => $this->buildAtomics('@compositor')
        ];
    }

    /**
     * Assert that given entity supported by composition.
     *
     * @param CompositableInterface $entity
     *
     * @throws CompositorException
     */
    protected function assertSupported(CompositableInterface $entity)
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
                $result[] = $this->odm->make($this->class, $item, $filter);
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
    private function packValues(array $entities): array
    {
        $result = [];
        foreach ($entities as $entity) {
            $result[] = $entity->packValue();
        }

        return $result;
    }
}