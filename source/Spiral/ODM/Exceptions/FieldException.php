<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\ODM\Exceptions;

use Spiral\Models\Exceptions\FieldExceptionInterface;

/**
 * When field can not be set or get.
 */
class FieldException extends DocumentException implements FieldExceptionInterface
{

}