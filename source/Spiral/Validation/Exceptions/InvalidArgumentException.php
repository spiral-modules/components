<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation\Exceptions;

use Spiral\Core\Exceptions\ExceptionInterface;

/**
 * Invalid argument supplied into validation rule.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{

}