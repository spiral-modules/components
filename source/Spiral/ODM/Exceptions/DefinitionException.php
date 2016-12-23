<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Exceptions;

use Spiral\ODM\Entities\DocumentCursor;

/**
 * Raised what document class can not be resolved based on set of provided fields or using logic
 * method.
 *
 * @see DocumentCursor::defineClass();
 */
class DefinitionException extends ODMException
{

}