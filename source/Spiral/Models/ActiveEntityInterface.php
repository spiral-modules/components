<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Models;

use Spiral\Models\Exceptions\EntityExceptionInterface;

/**
 * Represents generic "ActiveRecord" patten.
 */
interface ActiveEntityInterface extends EntityInterface
{
    /**
     * Indication that entity was fetched from it's primary source (database usually) and not just
     * created. Flag must be set to true once entity will be successfully saved.
     *
     * @return bool
     */
    public function isLoaded();

    /**
     * Save entity content into it's primary storage and return true if operation went successfully.
     *
     * @return bool
     * @throws EntityExceptionInterface
     */
    public function save();

    /**
     * Delete entity from it's primary storage, entity object must not be used anymore after that
     * operation.
     */
    public function delete();

    /**
     * Create instance of specific DataEntity and set it's fields (safely). Resulted entity must
     * not be saved into it's storage automatically.
     *
     * @param array $fields Entity fields to be set.
     * @return static
     */
    public static function create($fields = []);
}