<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

use Spiral\Models\Exceptions\EntityExceptionInterface;

/**
 * Represents generic "ActiveRecord" like patten.
 */
interface ActiveEntityInterface extends IdentifiedInterface
{
    /**
     * Save entity content into it's primary storage and return true if operation went successfully.
     *
     * @throws EntityExceptionInterface
     */
    public function save();

    /**
     * Delete entity from it's primary storage, entity object must not be used anymore after that
     * operation.
     */
    public function delete();
}
