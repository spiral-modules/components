<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Query;

use PDOStatement;
use Spiral\Database\Entities\QueryInterpolator;
use Spiral\Database\Query\Traits\InstantiationTrait;
use Spiral\Database\ResultInterface;

/**
 * Works as prepared PDOStatement.
 *
 * @todo bad namespace choice?
 * @todo withParameters!
 */
class PDOQuery extends PDOStatement implements ResultInterface, \JsonSerializable
{
    use InstantiationTrait;

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
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Just an alias.
     *
     * @return int
     */
    public function countColumns()
    {
        return $this->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function queryString()
    {
        return QueryInterpolator::interpolate($this->queryString, $this->parameters);
    }

    /**
     * {@inheritdoc}
     *
     * Attention, this method will return 0 for SQLite databases.
     *
     * @link http://php.net/manual/en/pdostatement.rowcount.php
     * @link http://stackoverflow.com/questions/15003232/pdo-returns-wrong-rowcount-after-select-statement
     *
     * @return int
     */
    public function count()
    {
        return $this->rowCount();
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->fetchAll(\PDO::FETCH_ASSOC);
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
    public function __debugInfo()
    {
        return [
            'query' => $this->queryString(),
            'count' => $this->count(),
            'rows'  => $this->count() > static::DUMP_LIMIT ? '[TOO MANY ROWS]' : $this->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }
}
