<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Entities;

use PDOStatement;
use Spiral\Database\Helpers\QueryInterpolator;

/**
 * Works as prepared PDOStatement.
 */
class QueryResult extends PDOStatement
{
    /**
     * Limits after which no records will be dumped in __debugInfo.
     */
    const DUMP_LIMIT = 500;

    /**
     * @var array
     */
    private $parameters = [];

    /**
     * @param array $parameters
     */
    protected function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Bind a column value to a PHP variable. Aliased to bindParam.
     *
     * @param int|string $columnID Column number (0 - first column)
     * @param mixed      $variable
     *
     * @return self|$this
     */
    public function bind($columnID, &$variable): QueryResult
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
     *
     * Attention: DO NOT USE THIS METHOD FOR ANYTHING DIFFERENT THAN DEBUGGING.
     */
    public function queryString(): string
    {
        return QueryInterpolator::interpolate($this->queryString, $this->parameters);
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
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'query' => $this->queryString(),
            'count' => $this->count(),
            'rows'  => $this->count() > static::DUMP_LIMIT ? '[TOO MANY ROWS]' : $this->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }
}
