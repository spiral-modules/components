<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Models;

use Spiral\Models\Exceptions\EntityExceptionInterface;

/**
 * Common interface to indicate that entity implements ActiveRecord pattern.
 */
interface ActiveEntityInterface
{
    const CREATED   = 1;
    const UPDATED   = 2;
    const UNCHANGED = 3;

    /**
     * Indication that entity was fetched from it's primary source (database usually) and not just
     * created. Flag must be set to true once entity will be successfully saved.
     *
     * @return bool
     */
    public function isLoaded(): bool;

    /**
     * Primary entity key if any.
     *
     * @return mixed|null
     */
    public function primaryKey();

    /**
     * Create entity or update entity state in database.
     *
     * @return int Must return one of constants self::CREATED, self::UPDATED, self::UNCHANGED
     * @throws EntityExceptionInterface
     */
    public function save(): int;

    /**
     * Delete entity from it's primary storage, entity object must not be used anymore after that
     * operation.
     */
    public function delete();
}