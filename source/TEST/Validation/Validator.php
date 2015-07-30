<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation;

use Psr\Log\LoggerAwareInterface;
use Spiral\Core\Component;
use Spiral\Core\ContainerInterface;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Translator\Traits\TranslatorTrait;

class Validator extends Component implements LoggerAwareInterface
{
    /**
     * Localization and indexation support.
     */
    use LoggerTrait, TranslatorTrait;

    /**
     * Errors added manually to validator using addError() method will get this condition type.
     */
    const FORCED_ERROR = "forced";

    /**
     * Rules declared in empty conditions should return this value to let system know that future
     * field validation can be skipped.
     */
    const STOP_VALIDATION = -99;

    /**
     * Validator will share checker instances for performance reasons.
     *
     * @var array
     */
    protected static $checkers = [];

    /**
     * Container. Used to resolve checkes.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Default message to apply as error when rule validation failed, has lowest priority and will
     * be replaced by custom checker or user defined message. Can be automatically interpolated
     * with condition and field names.
     *
     * @var string
     */
    protected $defaultMessage = "Condition '{condition}' does not meet for field '{field}'.";

    /**
     * Validation configuration options.
     *
     * @var array
     */
    protected $options = [
        'names'           => true, //Interpolate names
        'emptyConditions' => [],   //If met - validator will stop field validation on empty value
        'checkers'        => [],   //Set of validation checker (to dedicate validation)
        'aliases'         => []    //Set of validation rule aliases
    ];

    /**
     * Flag if validation was already applied for provided fields.
     *
     * @var bool
     */
    protected $validated = false;

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
     * Data to be validated. Nothing else to say.
     *
     * @var array|\ArrayAccess
     */
    protected $data = [];

    /**
     * Error messages collected during validating input data, by default one field associated with
     * first fail message, this behaviour can be changed by rewriting validator.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * If true (set by default), validator will stop checking field rules after first fail. This is
     * default behaviour used to render model errors and spiral frontend.
     *
     * @var bool
     */
    protected $singlePass = true;

    /**
     * Validator instance with specified input data and validation rules.
     *
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
     * @param array|\ArrayAccess     $data         Data to be validated.
     * @param array                  $validates    Validation rules.
     * @param array                  $options      Validation specific options.
     * @param ContainerInterface     $container    Container instance used to resolve checkers, global
     *                                             container will be used if nothing else provided.
     * @param ValidationConfigurator $configurator Used to supply global options.
     */
    public function __construct(
        $data,
        array $validates,
        array $options = [],
        ContainerInterface $container,
        ValidationConfigurator $configurator = null
    )
    {
        $this->data = $data;
        $this->validates = $validates;
        $this->options = $options + $this->options;
        $this->container = $container;

        if (!empty($configurator))
        {
            $this->options = $configurator->config() + $this->options;
        }
    }

    /**
     * Update validation data (context), this method will automatically clean all existed error
     * messages and set validated flag to false.
     *
     * @param array|\ArrayAccess $data Data to be validated.
     * @return self
     */
    public function setData($data)
    {
        $this->validated = false;

        $this->data = $data;
        $this->errors = [];

        return $this;
    }

    /**
     * Update validation rules.
     *
     * @param array $validates
     * @return self
     */
    public function setRules(array $validates)
    {
        $this->validated = false;
        $this->validates = $validates;
        $this->errors = [];

        return $this;
    }

    /**
     * Retrieve field value from data array. Can be used in validator Checker classes as they are
     * receiving validator instance during condition check.
     *
     * @param string $field   Data field to retrieve.
     * @param mixed  $default Default value to return.
     * @return null
     */
    public function getField($field, $default = null)
    {
        $value = isset($this->data[$field]) ? $this->data[$field] : $default;

        return $value instanceof ValueInterface ? $value->serializeData() : $value;
    }

    /**
     * Receive checker instance previously registered by registerChecker() or defined in default
     * spiral checkers set.
     *
     * Every checker can provide set of validation methods (conditions), which can be called by using
     * expression "checker::condition" where checker is alias class or object binded to. As any other
     * function used to check field, checker conditions can accept additional arguments collected
     * from rule. Checker classes resolved using IoC container and can depend on other tools.
     * Additionally checker will receive validator instance, so they can be used for complex and
     * composite data checks (use validator->getField()).
     *
     * @param string $name Checker name.
     * @return Checker
     * @throws ValidationException
     */
    public function getChecker($name)
    {
        if (isset(self::$checkers[$name]))
        {
            return self::$checkers[$name];
        }

        if (!isset($this->options['checkers'][$name]))
        {
            throw new ValidationException(
                "Unable to create validation checker defined by '{$name}' name."
            );
        }

        return self::$checkers[$name] = $this->container->get($this->options['checkers'][$name]);
    }

    /**
     * Helper methods, apply validation rules to existed data fields and collect validation error
     * messages. Can be redefined by custom behaviour.
     */
    protected function validate()
    {
        $this->errors = [];

        foreach ($this->validates as $field => $rules)
        {
            foreach ($rules as $rule)
            {
                if (isset($this->errors[$field]) && $this->singlePass)
                {
                    continue;
                }

                $condition = is_string($rule) ? $rule : $rule[0];
                if (empty($this->data[$field]) && !in_array($condition, $this->options['emptyConditions']))
                {
                    //There is no need to validate empty field except for special conditions
                    break;
                }

                $result = $this->check(
                    $field,
                    $condition,
                    $this->getField($field),
                    $arguments = is_string($rule) ? [] : $this->fetchArguments($rule)
                );

                if ($result instanceof Checker)
                {
                    //Custom message handling
                    if ($message = $result->getMessage($condition[1]))
                    {
                        $this->addMessage(
                            $field,
                            is_string($rule) ? $message : $this->fetchMessage($rule, $message),
                            $condition,
                            $arguments
                        );

                        continue;
                    }

                    $result = false;
                }

                if ((bool)$result)
                {
                    //Success
                    continue;
                }

                if ($result == self::STOP_VALIDATION)
                {
                    break;
                }

                //Default message
                $message = $this->translate($this->defaultMessage);

                //Recording error message
                $this->addMessage(
                    $field,
                    is_string($rule) ? $message : $this->fetchMessage($rule, $message),
                    $condition,
                    $arguments
                );
            }
        }
    }

    /**
     * Helper method to apply validation condition to field value, will automatically detect
     * condition type (function name, callback or checker condition).
     *
     * @param string $field     Field name.
     * @param mixed  $condition Condition definition (see rules).
     * @param mixed  $value     Value to be checked.
     * @param array  $arguments Additional arguments will be provided to check function or method
     *                          AFTER value.
     * @return bool
     * @throws ValidationException
     */
    protected function check($field, &$condition, $value, array $arguments)
    {
        if (is_string($condition) && isset($this->options['aliases'][$condition]))
        {
            $condition = $this->options['aliases'][$condition];
        }

        try
        {
            //Aliased condition
            if (strpos($condition, '::'))
            {
                $condition = explode('::', $condition);
                if (isset(self::$checkers[$condition[0]]))
                {
                    $checker = $this->getChecker($condition[0]);
                    if (!$result = $checker->check($condition[1], $value, $arguments, $this))
                    {
                        //To let validation() method know that message should be handled via Checker
                        return $checker;
                    }

                    return $result;
                }
            }

            if (is_string($condition) || is_array($condition))
            {
                array_unshift($arguments, $value);

                return call_user_func_array($condition, $arguments);
            }
        }
        catch (\ErrorException $exception)
        {
            $condition = func_get_arg(1);
            if (is_array($condition))
            {
                if (is_object($condition[0]))
                {
                    $condition[0] = get_class($condition[0]);
                }

                $condition = join('::', $condition);
            }

            $this->logger()->error(
                "Condition '{condition}' failed with '{exception}' while checking '{field}' field.",
                compact('condition', 'field') + ['exception' => $exception->getMessage()]
            );

            return false;
        }

        return true;
    }

    /**
     * Fetch additional validation arguments from rule. See rules explanation for more information.
     *
     * @param array $rule Rule definition.
     * @return array
     */
    protected function fetchArguments(array $rule)
    {
        unset($rule[0], $rule['message'], $rule['error']);

        return array_values($rule);
    }

    /**
     * Fetch message from validation rule or use default message defined by validator or checker
     * instances.
     *
     * @param array  $rule    Rule definition.
     * @param string $message Default message to use.
     * @return mixed
     */
    protected function fetchMessage(array $rule, $message)
    {
        $message = isset($rule['message']) ? $rule['message'] : $message;
        $message = isset($rule['error']) ? $rule['error'] : $message;

        return $message;
    }

    /**
     * Helper method used to register error message to error array. If interpolateMessages property
     * set to true message will be automatically interpolated with field and condition names.
     *
     * @param string $field     Field name.
     * @param string $message   Error message to be added.
     * @param mixed  $condition Condition definition (will be converted to string to interpolate).
     * @param array  $arguments Additional condition arguments.
     */
    protected function addMessage($field, $message, $condition, array $arguments = [])
    {
        if (is_array($condition))
        {
            if (is_object($condition[0]))
            {
                $condition[0] = get_class($condition[0]);
            }

            $condition = join('::', $condition);
        }

        if ($this->options['names'])
        {
            $this->errors[$field] = \Spiral\interpolate(
                $message,
                compact('field', 'condition') + $arguments
            );
        }
        else
        {
            $this->errors[$field] = \Spiral\interpolate(
                $message,
                compact('condition') + $arguments
            );
        }
    }

    /**
     * Validate data (if not already validated) and return validation status, true if all fields
     * passed validation and false is some error messages collected (error messages can be forced
     * manually using addError() method).
     *
     * @return bool
     */
    public function isValid()
    {
        !$this->validated && $this->validate();

        return !(bool)$this->errors;
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
     * Manually force error for some field ("forced" condition will be used).
     *
     * @param string $field
     * @param string $message Custom error message, will be interpolated if interpolateMessages
     *                        property set to true.
     */
    public function addError($field, $message)
    {
        $this->addMessage($field, $message, static::FORCED_ERROR, []);
    }

    /**
     * Validate data (if not already) and return all error messages associated with their field names.
     * Output format can vary based on validator implementation.
     *
     * @return array
     */
    public function getErrors()
    {
        !$this->validated && $this->validate();

        return $this->errors;
    }
}