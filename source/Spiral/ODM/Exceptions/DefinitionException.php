<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Exceptions;

use Spiral\ODM\Entities\DocumentInstantiator;

/**
 * Raised what document class can not be resolved based on set of provided fields or using logic
 * method.
 *
 * @see DocumentInstantiator::defineClass();
 */
class DefinitionException extends ODMException
{

}