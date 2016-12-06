<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

/**
 * Declares ability to be identified by primaryKey and it's loaded state. Probably not the best
 * name.
 */
interface IdentifiedInterface extends EntityInterface
{
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
}
