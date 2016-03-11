<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Validation;

interface ValidatorsInterface
{
    public function getValidator(array $rules);
}