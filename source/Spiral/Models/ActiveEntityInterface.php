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
interface ActiveEntityInterface extends IdentifiedInterface
{
    /**
     * Save entity content into it's primary storage and return true if operation went successfully.
     *
     * @param bool $validate
     * @return bool
     * @throws EntityExceptionInterface
     */
    public function save($validate = null);

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