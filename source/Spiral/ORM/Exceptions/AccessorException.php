<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Exceptions;

use Spiral\Records\Exceptions\AccessorExceptionInterface;

/**
 * Generic ORM accessor exception.
 */
class AccessorException extends RecordException implements AccessorExceptionInterface
{

}