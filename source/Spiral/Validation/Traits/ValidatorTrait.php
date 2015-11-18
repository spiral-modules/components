<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Validation\Traits;

use Spiral\Core\ConstructorInterface;
use Spiral\Core\Exceptions\SugarException;
use Spiral\Core\InteropContainerInterface;
use Spiral\Events\Traits\EventsTrait;
use Spiral\Translator\Traits\TranslatorTrait;
use Spiral\Translator\Translator;
use Spiral\Validation\Events\ValidatedEvent;
use Spiral\Validation\Events\ValidatorEvent;
use Spiral\Validation\Exceptions\ValidationException;
use Spiral\Validation\ValidatorInterface;

/**
 * Provides set of common validation methods.
 */
trait ValidatorTrait
{
    /**
     * Will translate every message it [[]] used and fire some events.
     */
    use TranslatorTrait, EventsTrait;

    /**
     * @var ValidatorInterface
     */
    private $validator = null;

    /**
     * Fields (data) to be validated. Named like that for convenience.
     *
     * @whatif private
     * @var array
     */
    protected $fields = [];

    /**
     * Validation rules defined in validator format. Named like that for convenience.
     *
     * @var array
     */
    protected $validates = [];

    /**
     * @whatif private
     * @var array
     */
    protected $errors = [];

    /**
     * Attach custom validator to model.
     *
     * @param ValidatorInterface $validator
     */
    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Check if context data is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        $this->validate();

        return empty($this->errors);
    }

    /**
     * Check if context data has errors.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return !$this->isValid();
    }

    /**
     * List of errors associated with parent field, every field should have only one error assigned.
     *
     * @param bool $reset Re-validate object.
     * @return array
     */
    public function getErrors($reset = false)
    {
        $this->validate($reset);

        $errors = [];
        foreach ($this->errors as $field => $error) {
            if (
                is_string($error)
                && substr($error, 0, 2) == Translator::I18N_PREFIX
                && substr($error, -2) == Translator::I18N_POSTFIX
            ) {
                //We will localize only messages embraced with [[ and ]]
                $error = $this->translate($error);
            }

            $errors[$field] = $error;
        }

        return $errors;
    }

    /**
     * Get associated instance of validator or return new one.
     *
     * @return ValidatorInterface
     * @throws SugarException
     */
    protected function validator()
    {
        if (!empty($this->validator)) {
            return $this->validator;
        }

        return $this->validator = $this->createValidator();
    }

    /**
     * Validate data using associated validator.
     *
     * @param bool $reset Implementation might reset validation if needed.
     * @return bool
     * @throws ValidationException
     * @throws SugarException
     * @event validated(ValidatedEvent)
     */
    protected function validate($reset = false)
    {
        //Refreshing validation fields
        $this->validator()->setData($this->fields);

        /**
         * @var ValidatedEvent $validatedEvent
         */
        $validatedEvent = $this->dispatch('validated', new ValidatedEvent(
            $this->validator()->getErrors()
        ));

        //Collecting errors if any
        return empty($this->errors = $validatedEvent->getErrors());
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
     * @return bool
     */
    protected function hasError($field)
    {
        return !empty($this->errors[$field]);
    }

    /**
     * Create instance of ValidatorInterface.
     *
     * @param array                     $rules     Non empty rules will initiate validator.
     * @param InteropContainerInterface $container Will fall back to global container.
     * @return ValidatorInterface
     * @throws SugarException
     * @event validator(ValidatorEvent)
     */
    protected function createValidator(
        array $rules = [],
        InteropContainerInterface $container = null
    ) {
        if (empty($container)) {
            $container = $this->container();
        }

        if (empty($container) || !$container->has(ValidatorInterface::class)) {
            //We can't create default validation without any rule, this is not secure
            throw new SugarException(
                "Unable to create Validator, no global container set or binding is missing."
            );
        }

        //We need constructor
        if ($container instanceof ConstructorInterface) {
            $constructor = $container;
        } else {
            $constructor = $container->get(ConstructorInterface::class);
        }

        //Receiving instance of validator from container
        $validator = $constructor->construct(ValidatorInterface::class, [
            'data'  => $this->fields,
            'rules' => !empty($rules) ? $rules : $this->validates
        ]);

        /**
         * @var ValidatorEvent $validatorEvent
         */
        $validatorEvent = $this->dispatch('validator', new ValidatorEvent($validator));

        return $validatorEvent->validator();
    }
}