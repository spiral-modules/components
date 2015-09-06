<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation\Traits;

use Spiral\Core\ContainerInterface;
use Spiral\Core\Exceptions\MissingContainerException;
use Spiral\Events\Traits\EventsTrait;
use Spiral\Translator\Traits\TranslatorTrait;
use Spiral\Translator\Translator;
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
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * Validation rules defined in validator format. Named like that for convenience.
     *
     * @var array
     */
    protected $validates = [];

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
     * Get associated instance of ValidatorInterface or create new using Container.
     *
     * @param array              $rules
     * @param ContainerInterface $container Will fall back to global container.
     * @return ValidatorInterface
     * @throws MissingContainerException
     */
    public function validator(array $rules = [], ContainerInterface $container = null)
    {
        if (!empty($this->validator)) {
            //Refreshing data
            $validator = $this->validator->setData($this->fields);
            !empty($rules) && $validator->setRules($rules);

            return $validator;
        }

        if (empty($container)) {
            $container = $this->container();
        }

        if (empty($container) || !$container->has(ValidatorInterface::class)) {
            //We can't create default validation without any rule, this is not secure
            throw new MissingContainerException(
                "Unable to create Validator instance, no global container set or binding is missing."
            );
        }

        return $this->validator = $container->construct(ValidatorInterface::class, [
            'data'  => $this->fields,
            'rules' => !empty($rules) ? $rules : $this->validates
        ]);
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
     * Validate data using associated validator.
     *
     * @param bool $reset
     * @return bool
     * @throws ValidationException
     * @throws MissingContainerException
     * @event validation()
     * @event validated($errors)
     */
    protected function validate($reset = false)
    {
        $this->fire('validation');
        $this->errors = $this->validator()->getErrors();
        $this->validator->setData([]);

        return empty($this->errors = $this->fire('validated', $this->errors));
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
     * @return ContainerInterface
     */
    abstract protected function container();
}