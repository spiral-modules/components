<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tokenizer\Reflections;

/**
 * Represent argument using in method or function call with it's type and value.
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
        if ($this->type != self::STRING)
        {
            return null;
        }

        //The most reliable way
        return eval("return {$this->value};");
    }

    /**
     * Create Argument reflections based on provided set of tokens (call inner part)
     *
     * @param array $tokens
     * @return self[]
     */
    public static function fetchArguments(array $tokens)
    {
        $definition = null;
        $parenthesisLevel = 0;

        $result = [];
        foreach ($tokens as $token)
        {
            if ($token[ReflectionFile::TOKEN_TYPE] == T_WHITESPACE)
            {
                continue;
            }

            if (empty($definition))
            {
                $definition = ['type' => self::EXPRESSION, 'value' => '', 'tokens' => []];
            }

            if ($token[ReflectionFile::TOKEN_TYPE] == '(')
            {
                $parenthesisLevel++;
                $definition['value'] .= $token[ReflectionFile::TOKEN_CODE];
                continue;
            }

            if ($token[ReflectionFile::TOKEN_TYPE] == ')')
            {
                $parenthesisLevel--;
                $definition['value'] .= $token[ReflectionFile::TOKEN_CODE];
                continue;
            }

            if ($parenthesisLevel)
            {
                $definition['value'] .= $token[ReflectionFile::TOKEN_CODE];
                continue;
            }

            if ($token[ReflectionFile::TOKEN_TYPE] == ',')
            {
                $result[] = self::createArgument($definition);
                $definition = null;
                continue;
            }

            $definition['tokens'][] = $token;
            $definition['value'] .= $token[ReflectionFile::TOKEN_CODE];
        }

        //Last argument
        if (is_array($definition))
        {
            $definition = self::createArgument($definition);
            if (!empty($definition->getType()))
            {
                $result[] = $definition;
            }
        }

        return $result;
    }

    /**
     * Create Argument reflection using token definition. Internal method.
     *
     * @see fetchArguments
     * @param array $definition
     * @return static
     */
    private static function createArgument(array $definition)
    {
        $argument = new static(self::EXPRESSION, $definition['value']);

        if (count($definition['tokens']) == 1)
        {
            //If argument represent by one token we can try to resolve it's type more precisely
            switch ($argument['tokens'][0][0])
            {
                case T_VARIABLE:
                    $argument->type = self::VARIABLE;
                    break;
                case T_LNUMBER:
                case T_DNUMBER:
                    $argument->type = self::CONSTANT;
                    break;
                case T_CONSTANT_ENCAPSED_STRING:
                    $argument->type = self::STRING;
                    break;
            }
        }

        return $argument;
    }
}