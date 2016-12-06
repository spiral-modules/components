<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database;

/**
 * Must represent single query result.
 *
 * Compatible with PDOStatement and decorates it in PDOResult.
 */
interface ResultInterface extends \Traversable, \Countable
{
    /**
     * The number of columns in the result set.
     *
     * @return int
     */
    public function countColumns();

    /**
     * Fetch one result row as array or return false.
     *
     * @return array|bool
     */
    public function fetch();

    /**
     * Returns an array containing all of the result set rows. Avoid using this method
     *
     * @return array
     */
    public function fetchAll();

    /**
     * Close result iterator. Must free as much memory as it can.
     */
    public function close();
}
