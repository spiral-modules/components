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
abstract class AbstractChecker extends Component implements CheckerInterface
{
    use TranslatorTrait;

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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getMessage($method, \ReflectionClass $reflection = null)
    {
        if (!empty($reflection)) {
            $messages = $reflection->getDefaultProperties()['messages'];
            if (isset($messages[$method])) {
                //We are inheriting parent messages
                return $this->say($messages[$method], [], $reflection->getName());
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
    protected function getValidator()
    {
        if (empty($this->validator)) {
            throw new ValidationException("Unable to receive parent checker validator.");
        }

        return $this->validator;
    }
}