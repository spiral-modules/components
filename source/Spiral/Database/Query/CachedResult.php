<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Query;

use PDO;
use Spiral\Cache\StoreInterface;
use Spiral\Database\Entities\QueryInterpolator;
use Spiral\Database\Exceptions\ResultException;

/**
 * CacheResult is almost identical to QueryResult by it's functionality, but used to represent query
 * result stored in cache storage.
 */
class CachedResult extends QueryResult
{
    /**
     * @var StoreInterface
     */
    protected $store = null;

    /**
     * @var string
     */
    protected $key = '';

    /**
     * Query string (not interpolated).
     *
     * @var string
     */
    protected $queryString = '';

    /**
     * Cache data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * FetchMode to be emulated.
     *
     * @var int
     */
    protected $fetchMode = PDO::FETCH_ASSOC;

    /**
     * Column bindings has to be validated also.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * @param StoreInterface $store
     * @param string         $cacheID
     * @param string         $queryString
     * @param array          $parameters
     * @param array          $data
     */
    public function __construct(
        StoreInterface $store,
        $cacheID,
        $queryString,
        array $parameters = [],
        array $data = []
    ) {
        $this->store = $store;
        $this->key = $cacheID;
        $this->queryString = $queryString;
        $this->parameters = $parameters;
        $this->data = $data;
        $this->count = count($data);
        $this->cursor = 0;

        //No need to call parent constructor
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
     */
    public function countColumns()
    {
        return $this->data ? count($this->data[0]) : 0;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ResultException
     */
    public function fetchMode($mode)
    {
        if ($mode != PDO::FETCH_ASSOC && $mode != PDO::FETCH_NUM) {
            throw new ResultException(
                'Cached query supports only FETCH_ASSOC and FETCH_NUM fetching modes.'
            );
        }

        $this->fetchMode = $mode;

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

        if (!isset($this->data[$this->cursor])) {
            return false;
        }

        if ($data = $this->data[$this->cursor++]) {
            foreach ($this->bindings as $columnID => &$variable) {
                $variable = $data[$columnID];
            }
        }

        if ($this->fetchMode == PDO::FETCH_NUM) {
            return $data ? array_values($data) : false;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnID = 0)
    {
        return $this->fetch(PDO::FETCH_NUM)[$columnID];
    }

    /**
     * {@inheritdoc}
     *
     * @throws ResultException
     */
    public function bind($columnID, &$variable)
    {
        if (!$this->data) {
            return $this;
        }

        if (is_numeric($columnID)) {
            //Getting column number
            foreach (array_keys($this->data[0]) as $index => $name) {
                if ($index == $columnID - 1) {
                    $this->bindings[$name] = &$variable;

                    return $this;
                }
            }

            throw new ResultException("No such index '{$columnID}' in the result columns.");
        } else {
            if (!isset($this->data[0][$columnID])) {
                throw new ResultException(
                    "No such column name '{$columnID}' in the result columns."
                );
            }

            $this->bindings[$columnID] = &$variable;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($mode = null)
    {
        $mode && $this->fetchMode($mode);

        //So we can properly emulate bindings and etc.
        $result = [];
        foreach ($this as $row) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->rowData = $this->fetch();

        return $this->rowData;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->cursor = 0;
        $this->rowData = $this->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * Flush results stored in CacheStore and CachedResult.
     */
    public function flush()
    {
        $this->data = [];
        $this->count = 0;

        $this->store->delete($this->key);
        $this->key = null;
    }

    /**
     * @return object
     */
    public function __debugInfo()
    {
        return (object)[
            'store'     => get_class($this->store),
            'cacheKey'  => $this->key,
            'statement' => $this->queryString(),
            'count'     => $this->count,
            'rows'      => $this->count > static::DUMP_LIMIT
                ? '[TOO MANY RECORDS TO DISPLAY]'
                : $this->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }
}