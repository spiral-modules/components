<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation\Traits;

use Spiral\Events\Traits\EventsTrait;
use Spiral\Translator\Traits\TranslatorTrait;
use Spiral\Translator\Translator;
use Spiral\Validation\ValidationException;
use Spiral\Validation\ValidatorInterface;

trait ValidatorTrait
{
    /**
     * Allowing error and validation rules location.
     */
    use TranslatorTrait, EventsTrait;

    /**
     * Fields to apply filters and validations, this is primary model data, which can be set using
     * setFields() method and retrieved using getFields() or publicFields().
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Validation and model errors.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Validator instance will be used to check model fields.
     *
     * @var ValidatorInterface
     */
    protected $validator = null;

    /**
     * Set of validation rules associated with their field. Every field can have one or multiple
     * rules assigned, however after first fail system will stop checking that field. This used to
     * prevent cascade validation failing. You can redefine property singleError and addMessage
     * function to specify different behaviour.
     *
     * Every rule should include condition (callback, function name or checker condition).
     * Additionally spiral validator supports custom validation messages which can be associated
     * with one condition by defining key "message" or "error", and additional argument which will
     * be passed to validation function AFTER field value.
     *
     * Default message provided by validator OR by checker (has higher priority that validation
     * message) will be used if you did not specify any custom rule.
     *
     * Validator will skip all empty or not defined values, to force it's validation use specially
     * designed rules like "notEmpty", "required", "requiredWith" and etc.
     *
     * Examples:
     * "status" => [
     *      ["notEmpty"],
     *      ["string::shorter", 10, "error" => "Your string is too short."],
     *      [["MyClass","myMethod"], "error" => "Custom validation failed."]
     * [,
     * "email" => [
     *      ["notEmpty", "error" => "Please enter your email address."],
     *      ["email", "error" => "Email is not valid."]
     * [,
     * "pin" => [
     *      ["string::regexp", "/[0-9]{5}/", "error" => "Invalid pin format, if you don't know your
     *                                                   pin, please skip this field."]
     * [,
     * "flag" => ["notEmpty", "boolean"]
     *
     * In cases where you don't need custom message or check parameters you can use simplified
     * rule syntax:
     *
     * "flag" => ["notEmpty", "boolean"]
     *
     * P.S. "$validates" is common name for validation rules property in validator and modes.
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
     * Validator instance associated with model, will be response for validations of validation errors.
     * Model related error localization should happen in model itself.
     *
     * @param array $validates Custom validation rules.
     * @return ValidatorInterface
     */
    public function getValidator(array $validates = [])
    {
        if (!empty($this->validator))
        {
            //Refreshing data
            $validator = $this->validator->setData($this->fields);
            !empty($validates) && $validator->setRules($validates);

            return $validator;
        }

        if (!empty($this->getContainer()))
        {
            $this->validator = $this->getContainer()->get(ValidatorInterface::class, [
                'fields'    => $this->fields,
                'validates' => !empty($validates) ? $validates : $this->validates
            ]);
        }

        throw new ValidationException("Unable to create class Validator, no global container set.");
    }

    /**
     * Validating data using validation rules, all errors will be stored in model errors array.
     * Errors will not be erased between function calls.
     *
     * @return bool
     */
    protected function validate()
    {
        $this->fire('validation');

        $this->errors = $this->getValidator()->getErrors();

        //Cleaning memory
        $this->validator->setData([]);

        return empty($this->errors = $this->fire('validated', $this->errors));
    }

    /**
     * Validate data and return validation status, true if all fields passed validation and false is
     * some error messages collected (error messages can be forced manually using addError() method).
     *
     * @return bool
     */
    public function isValid()
    {
        $this->validate();

        return !((bool)$this->errors);
    }

    /**
     * Adding error message. You can use this function to assign error manually. This message will be
     * localized same way as other messages, however system will not be able to index them.
     *
     * To use custom errors combined with location use ->addError($model->getMessage()) and store your
     * custom error messages in model::$messages array.
     *
     * @param string       $field   Field storing message.
     * @param string|array $message Message to be added.
     */
    public function addError($field, $message)
    {
        $this->errors[$field] = $message;
    }

    /**
     * Evil tween of isValid() method: validate data (if not already validated) and return true if
     * any validation error occurred including errors added using addError() method.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return !$this->isValid();
    }

    /**
     * Get all validation errors with applied localization using i18n component (if specified), any
     * error message can be localized by using [[ ]] around it. Data will be automatically validated
     * while calling this method (if not validated before).
     *
     * @param bool $reset Remove all model messages and reset validation, false by default.
     * @return array
     */
    public function getErrors($reset = false)
    {
        $this->validate();
        $errors = [];
        foreach ($this->errors as $field => $error)
        {
            if (
                is_string($error)
                && substr($error, 0, 2) == Translator::I18N_PREFIX
                && substr($error, -2) == Translator::I18N_POSTFIX
            )
            {
                $error = $this->translate($error);
            }

            $errors[$field] = $error;
        }

        if ($reset)
        {
            $this->errors = [];
        }

        return $errors;
    }
}