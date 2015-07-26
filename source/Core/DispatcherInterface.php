<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

interface DispatcherInterface
{
    /**
     * Starting dispatcher.
     */
    public function start();

    /**
     * Every application dispatcher should know how to handle exception.
     *
     * @param \Exception $exception
     * @return mixed
     */
    public function handleException(\Exception $exception);
}