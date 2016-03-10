<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Validation;

use Spiral\Core\Component;
use Spiral\Translator\Traits\TranslatorTrait;
use Spiral\Validation\Exceptions\ValidationException;

/**
 * Checkers used to group set of validation rules under one roof.
 */
abstract class Checker extends Component
{
    /**
     * Every checker can defined it's own error messages.
     */
    use TranslatorTrait;

    /**
     * Inherit parent validations.
     */
    const INHERIT_TRANSLATIONS = true;

    /**
     * @var Validator
     */
    private $validator = null;

    /**
     * Default error messages associated with checker method by name.
     *
     * @var array
     */
    protected $messages = [];

    /**
     * Check value using checker method.
     *
     * @param string    $method
     * @param mixed     $value
     * @param array     $arguments
     * @param Validator $validator Parent validator.
     * @return mixed
     */
    public function check($method, $value, array $arguments = [], Validator $validator = null)
    {
        array_unshift($arguments, $value);

        $this->validator = $validator;
        try {
            $result = call_user_func_array([$this, $method], $arguments);
        } finally {
            $this->validator = null;
        }

        return $result;
    }

    /**
     * Return default error message for checker condition.
     *
     * @param string           $method
     * @param \ReflectionClass $reflection Internal, used to resolve parent messages.
     * @return string
     */
    public function getMessage($method, \ReflectionClass $reflection = null)
    {
        if (!empty($reflection)) {
            $messages = $reflection->getDefaultProperties()['messages'];
            if (isset($messages[$method])) {
                //We are inheriting parent messages
                return $this->say($messages[$method]);
            }
        } elseif (isset($this->messages[$method])) {
            return $this->say($this->messages[$method]);
        }

        //Looking for message in parent realization
        $reflection = $reflection ?: new \ReflectionClass($this);
        if ($reflection->getParentClass() && $reflection->getParentClass()->isSubclassOf(self::class)) {
            return $this->getMessage($method, $reflection->getParentClass());
        }

        return '';
    }

    /**
     * Currently active validator instance.
     *
     * @return Validator
     */
    protected function validator()
    {
        if (empty($this->validator)) {
            throw new ValidationException("Unable to receive parent checker validator.");
        }

        return $this->validator;
    }
}