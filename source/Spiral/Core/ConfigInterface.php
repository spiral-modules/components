<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Core;

use Spiral\Core\Container\InjectableInterface;
use Spiral\Validation\ValidatorInterface;

/**
 * Simple Spiral components config interface.
 */
interface ConfigInterface extends InjectableInterface
{
    /**
     * Must populate validator rules or errors to be validated.
     *
     * @param ValidatorInterface $validator
     * @return ValidatorInterface
     */
    public function validate(ValidatorInterface $validator);

    /**
     * Serialize config into array.
     *
     * @return array
     */
    public function toArray();
}