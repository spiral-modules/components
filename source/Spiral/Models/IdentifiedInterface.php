<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\Models;

/**
 * Declares ability to be identified by primaryKey or any other scalar value.
 */
interface IdentifiedInterface extends EntityInterface
{
    /**
     * Primary entity key if any.
     *
     * @return mixed|null
     */
    public function primaryKey();
}