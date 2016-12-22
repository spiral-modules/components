<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Database\Exceptions\DBALException;
use Spiral\ORM\Exceptions\MapperException;
use Spiral\ORM\Exceptions\ORMException;

/**
 * Provides minimal set of mapping operations to support entity creation, creation and update,
 * deletion. Mappers are only responsible for push operations (see Selectors and Sources to find out
 * about selecting entities from database).
 */
interface MapperInterface
{
    const ENTITY_CREATED = 1;
    const ENTITY_UPDATED = 2;

    /**
     * Create object instance associated with given mapper.
     *
     * @param array $fields
     *
     * @return mixed
     */
    public function instance(array $fields);

    /**
     * Save entity state in database (or create new record), make sure wrapper received proper
     * entity type.
     *
     * @param mixed $entity
     *
     * @return int Returns ENTITY_CREATED or ENTITY_UPDATED.
     *
     * @throws MapperException
     * @throws ORMException
     * @throws DBALException
     */
    public function save($entity): int;

    /**
     * Delete entity record from database.
     *
     * @param mixed $entity
     *
     * @return mixed
     *
     * @throws MapperException
     */
    public function delete($entity);
}