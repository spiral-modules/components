<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models;

/**
 * Entity/object provides ability to invalidate it's state and nested models if any.
 */
interface InvalidatesInterface
{
    /**
     * Entity must re-validate data.
     *
     * @param bool $cascade Do not invalidate nested models (if such presented)
     *
     * @return $this
     */
    public function invalidate($cascade = false);
}