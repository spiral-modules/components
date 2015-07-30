<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views\Exceptions;

use Spiral\Core\Exceptions\ExceptionInterface;

/**
 * General view component exception (view file not found and etc).
 */
class ViewException extends \LogicException implements ExceptionInterface
{
}