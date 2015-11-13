<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer\Reflections;

/**
 * ReflectionInvocation used to represent function or static method call found by ReflectionFile.
 * This reflection is very useful for static analysis and mainly used in Translator component to
 * index translation function usages.
 */
class ReflectionInvocation
{
    /**
     * @var string
     */
    private $filename = '';

    /**
     * @var int
     */
    private $line = 0;

    /**
     * @var string
     */
    private $class = '';

    /**
     * @var string
     */
    private $operator = '';

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var string
     */
    private $source = '';

    /**
     * @var ReflectionArgument[]
     */
    private $arguments = [];

    /**
     * Was a function used inside another function call?
     *
     * @var int
     */
    private $level = 0;

    /**
     * New call reflection.
     *
     * @param string               $filename
     * @param int                  $line
     * @param string               $class
     * @param string               $operator
     * @param string               $name
     * @param ReflectionArgument[] $arguments
     * @param string               $source
     * @param int                  $level
     */
    public function __construct(
        $filename,
        $line,
        $class,
        $operator,
        $name,
        array $arguments,
        $source,
        $level
    ) {
        $this->filename = $filename;
        $this->line = $line;
        $this->class = $class;
        $this->operator = $operator;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->source = $source;
        $this->level = $level;
    }

    /**
     * Function usage filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Function usage line.
     *
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * Parent class.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Method operator (:: or ->).
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Function or method name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Call made by class method.
     *
     * @return bool
     */
    public function isMethod()
    {
        return !empty($this->class);
    }

    /**
     * Function usage source.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Count of arguments in call.
     *
     * @return int
     */
    public function countArguments()
    {
        return count($this->arguments);
    }

    /**
     * All parsed function arguments.
     *
     * @return ReflectionArgument[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Get call argument by it's position.
     *
     * @param int $index
     * @return ReflectionArgument|null
     */
    public function argument($index)
    {
        return isset($this->arguments[$index]) ? $this->arguments[$index] : null;
    }

    /**
     * Invoking level.
     *
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }
}