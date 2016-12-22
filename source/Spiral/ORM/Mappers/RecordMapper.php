<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Mappers;

use Spiral\ORM\MapperInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\RecordEntity;

class RecordMapper implements MapperInterface
{
    private $class;

    private $orm;

    public function __construct(string $class, ORMInterface $orm)
    {
        $this->class = $class;
        $this->orm = $orm;
    }

    /**
     * {@inheritdoc}
     *
     * @param int $state Initial entity state.
     *
     * @return RecordEntity
     */
    public function instance(array $fields, int $state = RecordEntity::STATE_NEW): RecordEntity
    {
        //In order to speed up entity mapping we are going to cache entity instances
        $entityCache = $this->orm->entityCache();

        $class = $this->class;

        $entity = new $class($fields, $state, $this->orm);

        //todo; chec cache

        if (!$this->cache->isEnabled() || !$cache) {

            //Entity cache is disabled, we can create record right now
            return new $class($data, !empty($data), $this, $schema);
        }

        //We have to find unique object criteria (will work for objects with primary key only)
        $primaryKey = null;

        if (
            !empty($schema[self::M_PRIMARY_KEY]) && !empty($data[$schema[self::M_PRIMARY_KEY]])
        ) {
            $primaryKey = $data[$schema[self::M_PRIMARY_KEY]];
        }

        if ($this->cache->has($class, $primaryKey)) {
            /**
             * @var RecordInterface $entity
             */
            return $this->cache->get($class, $primaryKey);
        }

        return $this->cache->remember(new $class($data, !empty($data), $this, $schema));
    }

    /**
     * {@inheritdoc}
     */
    public function save($entity): int
    {
        // TODO: Implement save() method.
    }

    /**
     * {@inheritdoc}
     */
    public function delete($entity)
    {
        // TODO: Implement delete() method.
    }
}