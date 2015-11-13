<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer\Reflections;

/**
 * Represent argument using in method or function invocation with it's type and value.
 */
class ReflectionArgument
{
    /**
     * Argument types.
     */
    const CONSTANT   = 'constant';   //Scalar constant and not variable.
    const VARIABLE   = 'variable';   //PHP variable
    const EXPRESSION = 'expression'; //PHP code (expression).
    const STRING     = 'string';     //Simple scalar string, can be fetched using stringValue().

    /**
     * @var int
     */
    private $type = null;

    /**
     * @var string
     */
    private $value = '';

    /**
     * New instance of ReflectionArgument.
     *
     * @param mixed $type
     * @param mixed $value
     */
    public function __construct($type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Convert argument value into valid string. Can be applied only for STRING type arguments.
     *
     * @return null|string
     */
    public function stringValue()
    {
        if ($this->type != self::STRING) {
            return null;
        }

        //The most reliable way
        return eval("return {$this->value};");
    }

    /**
     * Create Argument reflections based on provided set of tokens (fetched from invoke).
     *
     * @param array $tokens
     * @return self[]
     */
    public static function locateArguments(array $tokens)
    {
        $definition = null;
        $level = 0;

        $result = [];
        foreach ($tokens as $token) {
            if ($token[ReflectionFile::TOKEN_TYPE] == T_WHITESPACE) {
                continue;
            }

            if (empty($definition)) {
                $definition = ['type' => self::EXPRESSION, 'value' => '', 'tokens' => []];
            }

            if ($token[ReflectionFile::TOKEN_TYPE] == '(') {
                $level++;
                $definition['value'] .= $token[ReflectionFile::TOKEN_CODE];
                continue;
            }

            if ($token[ReflectionFile::TOKEN_TYPE] == ')') {
                $level--;
                $definition['value'] .= $token[ReflectionFile::TOKEN_CODE];
                continue;
            }

            if ($level) {
                $definition['value'] .= $token[ReflectionFile::TOKEN_CODE];
                continue;
            }

            if ($token[ReflectionFile::TOKEN_TYPE] == ',') {
                $result[] = self::createArgument($definition);
                $definition = null;
                continue;
            }

            $definition['tokens'][] = $token;
            $definition['value'] .= $token[ReflectionFile::TOKEN_CODE];
        }

        //Last argument
        if (is_array($definition)) {
            $definition = self::createArgument($definition);
            if (!empty($definition->getType())) {
                $result[] = $definition;
            }
        }

        return $result;
    }

    /**
     * Create Argument reflection using token definition. Internal method.
     *
     * @see locateArguments
     * @param array $definition
     * @return static
     */
    private static function createArgument(array $definition)
    {
        $result = new static(self::EXPRESSION, $definition['value']);

        if (count($definition['tokens']) == 1) {
            //If argument represent by one token we can try to resolve it's type more precisely
            switch ($definition['tokens'][0][0]) {
                case T_VARIABLE:
                    $result->type = self::VARIABLE;
                    break;
                case T_LNUMBER:
                case T_DNUMBER:
                    $result->type = self::CONSTANT;
                    break;
                case T_CONSTANT_ENCAPSED_STRING:
                    $result->type = self::STRING;
                    break;
            }
        }

        return $result;
    }
}