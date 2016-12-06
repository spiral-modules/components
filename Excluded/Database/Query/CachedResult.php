<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Query;

use Spiral\Cache\StoreInterface;
use Spiral\Database\Helpers\QueryInterpolator;

/**
 * CacheResult is almost identical to QueryResult by it's functionality, but used to represent query
 * result stored in cache storage.
 */
class CachedResult extends ArrayResult
{
    /**
     * Limits after which no records will be dumped in __debugInfo.
     */
    const DUMP_LIMIT = 500;

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
     * @param array          $data
     * @param array          $parameters
     * @param string         $queryString
     * @param string         $key
     * @param StoreInterface $store
     */
    public function __construct(
        array $data = [],
        array $parameters = [],
        $queryString,
        $key,
        StoreInterface $store
    ) {
        parent::__construct($data);

        $this->parameters = $parameters;
        $this->queryString = $queryString;

        $this->key = $key;
        $this->store = $store;
    }

    /**
     * {@inheritdoc}
     */
    public function queryString()
    {
        return QueryInterpolator::interpolate($this->queryString, $this->parameters);
    }

    /**
     * Flush results stored in CacheStore and CachedResult.
     */
    public function flushCache()
    {
        $this->store->delete($this->key);
        $this->key = null;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            //Cache part
            'key'   => $this->key,
            'store' => get_class($this->store),

            'query' => $this->queryString(),
            'count' => $this->count(),
            'data'  => $this->count() > self::DUMP_LIMIT ? '[TOO MANY ROWS]' : $this->fetchAll()
        ];
    }
}
