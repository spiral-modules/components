<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Entities\Nodes;

use Spiral\ORM\Entities\Nodes\Traits\DuplicateTrait;
use Spiral\ORM\ORMInterface;

/**
 * Similar to normal pivot node but does not require parent!
 */
class PivotedRootNode extends OutputNode
{
    use DuplicateTrait;

    /**
     * @invisible
     * @var int
     */
    private $countPivot = 0;

    /**
     * @var string
     */
    protected $innerPivotKey;

    /**
     * @var string
     */
    protected $outerPivotKey;

    /**
     * @param array  $columns
     * @param array  $pivotColumns
     * @param string $outerKey
     * @param string $innerPivotKey
     * @param string $outerPivotKey
     */
    public function __construct(
        array $columns = [],
        array $pivotColumns = [],
        string $outerKey,
        string $innerPivotKey,
        string $outerPivotKey
    ) {
        //Pivot columns are always prior to table columns
        parent::__construct(array_merge($pivotColumns, $columns), $outerKey);
        $this->countPivot = count($pivotColumns);

        $this->innerPivotKey = $innerPivotKey;
        $this->outerPivotKey = $outerPivotKey;
    }

    /**
     * {@inheritdoc}
     */
    protected function pushData(array &$data)
    {
        if (is_null($data[ORMInterface::PIVOT_DATA][$this->outerPivotKey])) {
            //No data was loaded
            return;
        }

        $this->result[] = &$data;
    }

    /**
     * {@inheritdoc}
     *
     * Method fetches pivot data into sub-array with key "@pivot".
     */
    protected function fetchData(int $dataOffset, array $line): array
    {
        $data = parent::fetchData($dataOffset, $line);

        //Forming pivot data presence
        return array_merge(
            [ORMInterface::PIVOT_DATA => array_slice($data, 0, $this->countPivot)],
            array_slice($data, $this->countPivot)
        );
    }

    /**
     * De-duplication in pivot tables based on values in pivot table.
     *
     * @param array $data
     *
     * @return string
     */
    protected function duplicateCriteria(array &$data)
    {
        $pivotData = $data[ORMInterface::PIVOT_DATA];

        //Unique row criteria
        return $pivotData[$this->innerPivotKey] . '.' . $pivotData[$this->outerPivotKey];
    }

}