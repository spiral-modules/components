<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation;

use Spiral\Core\Component;
use Spiral\Translator\Traits\TranslatorTrait;

abstract class Checker extends Component
{
    /**
     * Localization and indexation support.
     */
    use TranslatorTrait;

    /**
     * We are going to inherit parent validation, we have to let i18n indexer know to collect both
     * local and parent messages under one bundle.
     */
    const I18N_INHERIT_MESSAGES = true;

    /**
     * Set of default error messages associated with their check methods organized by method name.
     * Will be returned by the checker to replace the default validator message. Can have placeholders
     * for interpolation.
     *
     * @var array
     */
    protected $messages = [];

    /**
     * Validator instance, can be used for complex and composite validations.
     *
     * @var Validator
     */
    protected $validator = null;

    /**
     * Perform value check using specified checker method, value and arguments. Validator instance
     * can be provided to create appropriate context for complex and composite validations.
     *
     * @param string    $method    Checker method name.
     * @param mixed     $value     Value to be validated.
     * @param array     $arguments Additional arguments will be provided to checker method AFTER value.
     * @param Validator $validator Validator instance initiated validation session.
     * @return mixed
     */
    public function check($method, $value, array $arguments = [], Validator $validator = null)
    {
        array_unshift($arguments, $value);

        $this->validator = $validator;
        $result = call_user_func_array([$this, $method], $arguments);
        $this->validator = null;

        return $result;
    }

    /**
     * Return custom error message associated with checker methods. Return empty string if no methods
     * associated.
     *
     * @param string           $method     Checker method name.
     * @param \ReflectionClass $reflection Source to fetch messages from.
     * @return string
     */
    public function getMessage($method, \ReflectionClass $reflection = null)
    {
        if (!empty($reflection))
        {
            $messages = $reflection->getDefaultProperties()['messages'];
            if (isset($messages[$method]))
            {
                //We are inheriting parent messages
                return $this->translate($messages[$method]);
            }
        }
        elseif (isset($this->messages[$method]))
        {
            return $this->translate($this->messages[$method]);
        }

        //Looking for message in parent realization
        $reflection = $reflection ?: new \ReflectionClass($this);
        if ($reflection->getParentClass())
        {
            return $this->getMessage($method, $reflection->getParentClass());
        }

        return '';
    }
}