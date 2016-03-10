<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

use Spiral\Models\Exceptions\ExceptionInterface;

/**
 * Represents generic "ActiveRecord" like patten.
 */
interface ActiveEntityInterface extends IdentifiedInterface
{
    /**
     * Save entity content into it's primary storage and return true if operation went successfully.
     *
     * @param bool $validate
     *
     * @return bool
     *
     * @throws ExceptionInterface
     */
    public function save($validate = null);

    /**
     * Delete entity from it's primary storage, entity object must not be used anymore after that
     * operation.
     */
    public function delete();
}
