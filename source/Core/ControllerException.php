<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

class ControllerException extends \RuntimeException implements ExceptionInterface
{
    const NOT_FOUND    = 0;
    const BAD_ACTION   = 1;
    const BAD_ARGUMENT = 2;
}