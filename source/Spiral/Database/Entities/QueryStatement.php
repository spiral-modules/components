<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities;

use PDOStatement;

/**
 * Adds few quick methods to PDOStatement and fully compatible with it. By default uses
 * PDO::FETCH_ASSOC mode.
 */
class QueryStatement extends PDOStatement
{
    /**
     * Limits after which no records will be dumped in __debugInfo.
     */
    const DUMP_LIMIT = 500;

    /**
     * You are seeing completely valid PDO specific protected constructor.
     */
    protected function __construct()
    {
        $this->setFetchMode(\PDO::FETCH_ASSOC);
    }

    /**
     * Bind a column value to a PHP variable. Aliased to bindParam.
     *
     * @param int|string $columnID Column number (0 - first column)
     * @param mixed      $variable
     *
     * @return self|$this
     */
    public function bind($columnID, &$variable): QueryStatement
    {
        if (is_numeric($columnID)) {
            //PDO columns are 1-indexed
            $columnID = $columnID + 1;
        }

        $this->bindColumn($columnID, $variable);

        return $this;
    }

    /**
     * Just an alias.
     *
     * @return int
     */
    public function countColumns(): int
    {
        return $this->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->closeCursor();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Attention, this method WILL NOT work correctly with SQLite driver.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'query' => $this->queryString,
            'count' => $this->rowCount(),
            'rows'  => $this->rowCount() > static::DUMP_LIMIT ? '[TOO MANY ROWS]' : $this->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }
}
