<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Query;

use PDOStatement;
use Spiral\Database\Entities\QueryCompiler;
use Spiral\Database\ResultInterface;

/**
 * Query result iteration class.
 */
class QueryResult implements ResultInterface, \JsonSerializable
{
    /**
     * Limits after which no records will be dumped in __debugInfo.
     */
    const DUMP_LIMIT = 500;

    /**
     * Cursor position, used to determinate current data index.
     *
     * @var int
     */
    protected $cursor = null;

    /**
     * The number of rows selected by SQL statement.
     *
     * @var int
     */
    protected $count = 0;

    /**
     * Last selected row array. This value is required to correctly emulate Iterator methods.
     *
     * @var mixed
     */
    protected $rowData = null;

    /**
     * @invisible
     * @var PDOStatement
     */
    protected $statement = null;

    /**
     * PDOStatement prepare parameters.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * @link http://php.net/manual/en/class.pdostatement.php
     * @param PDOStatement $statement
     * @param array        $parameters
     */
    public function __construct(PDOStatement $statement, array $parameters = [])
    {
        $this->statement = $statement;
        $this->parameters = $parameters;

        $this->count = $this->statement->rowCount();

        //Forcing default fetch mode
        $this->statement->setFetchMode(\PDO::FETCH_ASSOC);
    }

    /**
     * Query string associated with PDOStatement.
     *
     * @return string
     */
    public function queryString()
    {
        return QueryCompiler::interpolate($this->statement->queryString, $this->parameters);
    }

    /**
     * {@inheritdoc}
     *
     * Attention, this method will return 0 for SQLite databases.
     *
     * @link http://php.net/manual/en/pdostatement.rowcount.php
     * @link http://stackoverflow.com/questions/15003232/pdo-returns-wrong-rowcount-after-select-statement
     * @return int
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * {@inheritdoc}
     *
     * @link http://php.net/manual/en/pdostatement.columncount.php
     */
    public function countColumns()
    {
        return $this->statement->columnCount();
    }

    /**
     * Change fetching mode, use PDO::FETCH_ constants to specify required mode. If you want to keep
     * compatibility with CachedQuery do not use other modes than PDO::FETCH_ASSOC and PDO::FETCH_NUM.
     *
     * @link http://php.net/manual/en/pdostatement.setfetchmode.php
     * @param int $mode The fetch mode must be one of the PDO::FETCH_* constants.
     * @return $this
     */
    public function fetchMode($mode)
    {
        $this->statement->setFetchMode($mode);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($mode = null)
    {
        if (!empty($mode)) {
            $this->fetchMode($mode);
        }

        return $this->statement->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnID = 0)
    {
        return $this->statement->fetchColumn($columnID);
    }

    /**
     * {@inheritdoc}
     *
     * @link http://www.php.net/manual/en/function.PDOStatement-bindColumn.php
     * @return $this
     */
    public function bind($columnID, &$variable)
    {
        if (is_numeric($columnID)) {
            //PDO columns are 1-indexed
            $columnID = $columnID + 1;
        }

        $this->statement->bindColumn($columnID, $variable);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($mode = null)
    {
        if (!empty($mode)) {
            $this->fetchMode($mode);
        }

        return $this->statement->fetchAll();
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->rowData;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->rowData = $this->fetch();
        $this->cursor++;

        return $this->rowData;
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->cursor;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        //We can't use cursor or any other method to walk though data as SQLite will return 0 for count.
        return $this->rowData !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->rowData = $this->fetch();
        $this->cursor = 0;
    }

    /**
     * {@inheritdoc}
     *
     * @link http://php.net/manual/en/pdostatement.closecursor.php
     * @return bool
     */
    public function close()
    {
        return $this->statement && $this->statement->closeCursor();
    }

    /**
     * Destruct associated statement to free used memory.
     */
    public function __destruct()
    {
        $this->close();
        $this->statement = null;
    }

    /**
     * @return object
     */
    public function __debugInfo()
    {
        return (object)[
            'statement' => $this->queryString(),
            'count'     => $this->count,
            'rows'      => $this->count > static::DUMP_LIMIT
                ? '[TOO MANY RECORDS TO DISPLAY]'
                : $this->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->fetchAll();
    }
}