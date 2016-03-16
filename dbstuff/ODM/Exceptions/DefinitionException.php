<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ODM\Exceptions;

/**
 * Raised what document class can not be resolved based on set of provided fields or using logic
 * method.
 *
 * @see Document::defineClass();
 */
class DefinitionException extends DocumentException
{
}
