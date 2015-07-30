<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

/**
 * Null does not mean "not required", in some cases not required constructor argument can be in reality
 * be very required.
 */
class MissingClassException extends \LogicException implements ExceptionInterface
{

}