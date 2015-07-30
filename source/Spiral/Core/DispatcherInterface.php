<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

use Spiral\Debug\SnapshotInterface;

/**
 * Dispatchers are general application flow controllers, system should start them and pass exception
 * or instance of snapshot into them when error happens.
 */
interface DispatcherInterface
{
    /**
     * Start dispatcher.
     */
    public function start();

    /**
     * @param \Exception $exception
     * @return mixed
     */
    public function handleException(\Exception $exception);

    /**
     * @param SnapshotInterface $snapshot
     * @return mixed
     */
    public function handleSnapshot(SnapshotInterface $snapshot);
}