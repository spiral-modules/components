<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ORM;

use Spiral\Database\Injections\FragmentInterface;
use Spiral\Models\AccessorInterface;

/**
 * Declares requirement for every ORM field accessor to control it's updates and state.
 */
interface SQLAccessorInterface extends AccessorInterface
{
    /**
     * Check if object has any update.
     *
     * @return bool
     */
    public function hasUpdates();

    /**
     * Mark object as successfully updated and flush all existed atomic operations and updates.
     */
    public function flushUpdates();

    /**
     * Create update value or statement to be used in DBAL update builder. May return SQLFragments
     * and expressions.
     *
     * @param string $field Name of field where accessor associated to.
     *
     * @return mixed|FragmentInterface
     */
    public function compileUpdates(string $field = '');
}