<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models;

use Spiral\Translator\Traits\TranslatorTrait;
use Spiral\Translator\Translator;
use Spiral\Validation\Events\ValidatedEvent;
use Spiral\Validation\ValidatesInterface;
use Spiral\Validation\ValidatorInterface;

/**
 * Entity with ability to validate it's data and translate validation messages
 */
abstract class ValidatedEntity extends MutableObject implements ValidatesInterface, EntityInterface
{
    use TranslatorTrait;

    /**
     * @var ValidatorInterface
     */
    private $validator = null;

    /**
     * @internal For internal use only
     * @var array
     */
    protected $errors = [];

    /**
     * Attach custom validator to model
     *
     * @param ValidatorInterface $validator
     */
    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Get associated validator instance.
     *
     * @return ValidatesInterface
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        $this->validate();

        return empty($this->errors);
    }

    /**
     * {@inheritdoc}
     */
    public function hasErrors()
    {
        return !$this->isValid();
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($reset = false)
    {
        $this->validate($reset);

        $errors = [];
        foreach ($this->errors as $field => $error) {
            if (is_string($error) && Translator::isMessage($error)) {
                //We will localize only messages embraced with [[ and ]]
                $error = $this->say($error);
            }

            $errors[$field] = $error;
        }

        return $errors;
    }

    /**
     * Attach error to data field. Internal method to be used in validations.
     *
     * @param string $field
     * @param string $message
     */
    protected function setError($field, $message)
    {
        $this->errors[$field] = $message;
    }

    /**
     * Check if desired field caused some validation error.
     *
     * @param string $field
     *
     * @return bool
     */
    protected function hasError($field)
    {
        return !empty($this->errors[$field]);
    }

    /**
     * Validate data using associated validator.
     *
     * @param bool $reset Implementation might reset validation if needed.
     *
     * @return bool
     *
     * @event validated(ValidatedEvent)
     */
    protected function validate($reset = false)
    {
        if (empty($this->validator)) {
            $this->validator = $this->createValidator();
        }

        //Refreshing validation fields
        $this->validator->setData($this->getFields());

        /**
         * @var ValidatedEvent $event
         */
        $event = $this->dispatch('validated', new ValidatedEvent($this->validator->getErrors()));

        //Collecting errors if any
        return empty($this->errors = $event->getErrors());
    }

    /**
     * @return ValidatorInterface
     */
    abstract protected function createValidator();
}