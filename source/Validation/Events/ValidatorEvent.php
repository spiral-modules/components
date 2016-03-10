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
 * Raised at moment of validator creation.
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
     * Change validation.
     *
     * @param ValidatorInterface $validator
     */
    public function setValidator(ValidatorInterface $validator)
    {
        $this->$validator = $validator;
    }

    /**
     * @return ValidatorInterface
     */
    public function validator()
    {
        return $this->validator;
    }
}
