<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database;

use Spiral\Database\Exceptions\QueryException;

/**
 * TableInterface is table level abstraction linked to existed or not existed database table. You
 * can check if table really exist or not exist using "exists" method of table schema.
 *
 * @todo add interfaces for builders
 */
interface TableInterface extends \Countable
{
    /**
     * Associated database.
     *
     * @return DatabaseInterface
     */
    public function getDatabase(): DatabaseInterface;

    /**
     * Must return schema instance even if table does not exists.
     *
     * @return \Spiral\Database\Schemas\TableInterface
     */
    public function getSchema(): \Spiral\Database\Schemas\TableInterface;

    /**
     * Table name in a context of parent database (no prefix included).
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Truncate (clean) current table.
     */
    public function truncateData();

    /**
     * Must perform single rowset insertion into table. Method must return lastInsertID on success.
     *
     * Example:
     * $table->insert(["name" => "Bob"])
     *
     * @param array $rowset Associated array (key => value).
     *
     * @return mixed
     *
     * @throws QueryException
     */
    public function insert(array $rowset = []);
}
