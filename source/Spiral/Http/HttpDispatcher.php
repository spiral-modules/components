<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\DispatcherInterface;
use Spiral\Core\Singleton;

class HttpDispatcher extends Singleton implements DispatcherInterface, LoggerAwareInterface
{

}