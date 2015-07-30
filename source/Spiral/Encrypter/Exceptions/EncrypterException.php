<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Encrypter\Exceptions;

use Spiral\Core\Exceptions\ExceptionInterface;

/**
 * General encrypter exception (bad key and etc).
 */
class EncrypterException extends \LogicException implements ExceptionInterface
{

}