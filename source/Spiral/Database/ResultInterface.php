<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database;

/**
 * Must represent single query result. Has to decorate or extend methods of PDOStatement.
 */
interface ResultInterface extends \Countable, \Iterator
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
     * Returns a single column value from the next row of a result set.
     *
     * @param int $columnID Column number (0 - first column)
     * @return mixed
     */
    public function fetchColumn($columnID = 0);

    /**
     * Bind a column value to a PHP variable.
     *
     * @param integer|string $columnID Column number (0 - first column)
     * @param mixed          $variable
     */
    public function bind($columnID, &$variable);

    /**
     * Returns an array containing all of the result set rows, do not use this method on big
     * datasets.
     *
     * @return array
     */
    public function fetchAll();

    /**
     * Close result iterator. Must free as much memory as it can.
     */
    public function close();
}