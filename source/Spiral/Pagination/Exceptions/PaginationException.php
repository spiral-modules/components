<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Pagination\Exceptions;

use Spiral\Core\Exceptions\ExceptionInterface;

/**
 * General pagination error.
 */
class PaginationException extends \LogicException implements ExceptionInterface
{

}