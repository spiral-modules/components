<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Cache\Exceptions;

use Spiral\Core\ExceptionInterface;

/**
 * Store not found or can not be constructed.
 */
class CacheException extends \LogicException implements ExceptionInterface
{

}