<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Validation\Events;

use Spiral\Validation\ValidatorInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Raised after validation.
 */
class ValidatorEvent extends Event
{
    /**
     * @var ValidatorInterface
     */
    private $validator = null;

    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @return array
     */
    public function getValidator()
    {
        return $this->validator;
    }
}
