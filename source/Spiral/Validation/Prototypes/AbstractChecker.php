<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Validation\Prototypes;

use Interop\Container\ContainerInterface;
use Spiral\Core\Component;
use Spiral\Core\Exceptions\SugarException;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Translator\Traits\TranslatorTrait;
use Spiral\Validation\CheckerInterface;
use Spiral\Validation\Exceptions\ValidationException;
use Spiral\Validation\ValidatorInterface;

/**
 * Checkers used to group set of validation rules under one roof.
 */
abstract class AbstractChecker extends Component implements CheckerInterface
{
    use TranslatorTrait, SaturateTrait;

    /**
     * @var ValidatorInterface
     */
    private $validator = null;

    /**
     * Default error messages associated with checker method by name.
     *
     * @var array
     */
    protected $messages = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ContainerInterface $container Needed for translations and other things, saturated
     *
     * @throws SugarException
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $this->saturate($container, ContainerInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function check(
        string $method,
        $value,
        array $arguments = [],
        ValidatorInterface $validator = null
    ) {
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
    public function getMessage(string $method, \ReflectionClass $reflection = null): string
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
     * @return ValidatorInterface
     */
    protected function getValidator(): ValidatorInterface
    {
        if (empty($this->validator)) {
            throw new ValidationException("Unable to receive parent checker validator");
        }

        return $this->validator;
    }
}