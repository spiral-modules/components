<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Debug\Logger;

use Monolog\Handler\AbstractHandler;

/**
 * Stores log messages in memory.
 */
class SharedHandler extends AbstractHandler
{
    /**
     * @var array
     */
    protected $records = [];

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        $this->records[] = $record;

        //Passing
        return false;
    }

    /**
     * All collected records.
     *
     * @return array
     */
    public function getRecords()
    {
        return $this->records;
    }
}