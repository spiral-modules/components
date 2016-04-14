<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models\Traits;

use Spiral\Core\Exceptions\SugarException;
use Spiral\Core\FactoryInterface;
use Spiral\Models\AccessorInterface;
use Spiral\Models\InvalidatesInterface;
use Spiral\Translator\Traits\TranslatorTrait;
use Spiral\Translator\Translator;
use Spiral\Validation\Events\ValidatorEvent;
use Spiral\Validation\Exceptions\ValidationException;
use Spiral\Validation\ValidatorInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Provides ability to validate entity and all nested entities.
 */
trait ValidatorTrait
{
    use TranslatorTrait;

    /**
     * @var ValidatorInterface
     */
    private $validator = null;

    /**
     * Indication that entity been validated.
     *
     * @var bool
     */
    private $validated = true;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * Attach custom validator to model.
     *
     * @param ValidatorInterface $validator
     */
    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->validated = false;
    }

    /**
     * Get associated entity validator or create one (if needed), method can return null
     *
     * @return ValidatorInterface|null
     */
    public function getValidator()
    {
        if (empty($this->validator)) {
            $this->createValidator();
        }

        return $this->validator;
    }

    /**
     * Check if context data is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        $this->validate(false);

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
     *
     * @return array
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
     * Attach error to data field. Internal method to be used in validations. To be used ONLY in
     * overloaded validate method.
     *
     * @see validate()
     *
     * @param string $field
     *
     * @param string $message
     */
    protected function setError($field, $message)
    {
        $this->errors[$field] = $message;
    }

    /**
     * Check if desired field caused some validation error. To be used ONLY in overloaded validate
     * method.
     *
     * @see validate()
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
     * @return bool
     */
    public function isValidated()
    {
        return $this->validated;
    }

    /**
     * Entity must re-validate data.
     *
     * @param bool $cascade Do not invalidate nested models (if such presented)
     *
     * @return $this
     */
    public function invalidate($cascade = false)
    {
        $this->validated = false;

        if ($cascade) {
            return $this;
        }

        //Invalidating all compositions
        foreach ($this->getFields(false) as $value) {
            //Let's force composition construction
            if ($value instanceof InvalidatesInterface) {
                $value->invalidate($cascade);
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'fields' => $this->getFields(),
            'errors' => $this->getErrors(),
        ];
    }

    /**
     * Validate entity fields
     *
     * @param bool $reset
     *
     * @return bool
     *
     * @throws ValidationException
     *
     * @event validate($validator)
     */
    protected function validate($reset = false)
    {
        $validator = $this->getValidator();
        if (empty($validator)) {
            $this->validated = true;
        }

        if ($this->validated && !$reset) {
            //Nothing to do
            return empty($this->errors);
        }

        //Refreshing validation fields
        $validator->setData($this->getFields(false));

        $this->dispatch('validate', new ValidatorEvent($validator));

        //Validation performed
        $this->validated = true;

        //Collecting errors if any
        return empty($this->errors = $validator->getErrors());
    }

    /**
     * @param bool $filter
     * @return array|AccessorInterface[]
     */
    abstract public function getFields($filter = true);

    /**
     * Method can return null if no validator is required.
     *
     * @return ValidatorInterface|null
     */
    abstract protected function createValidator();

    /**
     * Dispatch event. If no dispatched associated even will be returned without dispatching.
     *
     * @param string     $name  Event name.
     * @param Event|null $event Event class if any.
     *
     * @return Event
     */
    abstract protected function dispatch($name, Event $event = null);

    /**
     * Create validator using shared container.
     *
     * @param array $rules Non empty rules will initiate validator.
     *
     * @return ValidatorInterface
     *
     * @throws SugarException
     */
    private function defaultValidator(array $rules = [])
    {
        $container = $this->container();

        if (empty($container) || !$container->has(ValidatorInterface::class)) {
            //We can't create default validation without any rule, this is not secure
            throw new SugarException(
                'Unable to create Validator, no global container set or binding is missing'
            );
        }

        //We need factory to create validator
        if ($container instanceof FactoryInterface) {
            //Shortcut
            $factory = $container;
        } else {
            $factory = $container->get(FactoryInterface::class);
        }

        //Receiving instance of validator from container (todo: impove?)
        return $factory->make(ValidatorInterface::class, compact('rules'));
    }
}