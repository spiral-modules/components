<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Drivers\SQLServer;

/**
 * SQLServer specific result reader, required due server need additional column for sorting in some
 * cases.
 */
class QueryResult extends \Spiral\Database\Query\QueryResult
{
    /**
     * Helper column used to create limit, offset statements in older versions of sql server.
     */
    const ROW_NUMBER_COLUMN = 'spiral_row_number';

    /**
     * Indication that result includes row number column which should be excluded from results.
     *
     * @var bool
     */
    protected $helperRow = false;

    /**
     * {@inheritdoc}
     */
    public function __construct(\PDOStatement $statement, array $parameters = [])
    {
        parent::__construct($statement, $parameters);

        if ($this->statement->getColumnMeta($this->countColumns() - 1)['name'] == self::ROW_NUMBER_COLUMN)
        {
            $this->helperRow = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function countColumns()
    {
        return $this->statement->columnCount() + ($this->helperRow ? -1 : 0);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($mode = null)
    {
        $result = parent::fetch($mode);
        !empty($result) && $this->helperRow && array_pop($result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($mode = null)
    {
        if (!$this->helperRow)
        {
            return parent::fetchAll($mode);
        }

        $result = [];
        while ($rowset = $this->fetch($mode))
        {
            $result[] = $rowset;
        }

        return $result;
    }
}