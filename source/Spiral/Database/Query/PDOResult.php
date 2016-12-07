<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Query;

use PDOStatement;
use Spiral\Database\Helpers\QueryInterpolator;
use Spiral\Database\ResultInterface;

/**
 * Works as prepared PDOStatement.
 */
class PDOResult extends PDOStatement implements ResultInterface, \JsonSerializable
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
     * @param int|string $fieldID Column number (0 - first column)
     * @param mixed      $variable
     * @return self
     */
    public function bind($fieldID, &$variable): PDOResult
    {
        if (is_numeric($fieldID)) {
            //PDO columns are 1-indexed
            $fieldID = $fieldID + 1;
        }

        $this->bindColumn($fieldID, $variable);

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
    public function queryString(): string
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
    public function count(): int
    {
        return $this->rowCount();
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
    public function jsonSerialize(): array
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
