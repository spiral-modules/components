<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core\Exceptions;

/**
 * User input did something wrong, exceptions like that should be treat differently without much
 * logging.
 */
class ClientException extends \RuntimeException implements ExceptionInterface
{

}