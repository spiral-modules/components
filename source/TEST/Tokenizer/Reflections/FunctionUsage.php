<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tokenizer\Reflections;

use Spiral\Tokenizer\Reflections\FunctionUsage\Argument;

class FunctionUsage
{
    /**
     * Function location.
     *
     * @var string
     */
    protected $filename = '';

    /**
     * Line where function was used.
     *
     * @var int
     */
    protected $line = 0;

    /**
     * Function name, can include :: as a parent class.
     * Function name.
     *
     * @var string
     */
    protected $function = '';

    /**
     * Function class.
     *
     * @var string
     */
    protected $class = '';

    /**
     * Function usage source.
     *
     * @var string
     */
    protected $source = '';

    /**
     * Function arguments with their types and values.
     *
     * @var Argument[]
     */
    protected $arguments = [];

    /**
     * Function open token ID.
     *
     * @var int
     */
    protected $openTID = 0;

    /**
     * Function close token ID.
     *
     * @var int
     */
    protected $closeTID = 0;

    /**
     * Was a function used inside another function call?
     *
     * @var int
     */
    protected $level = 0;

    /**
     * New function usage.
     *
     * @param string $function
     * @param string $class
     * @param string $source
     */
    public function __construct($function, $class, $source)
    {
        $this->function = $function;

        $this->class = $class;
        $this->source = $source;
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
     * Function usage name, may include :: with a parent static class.
     *
     * @return string
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * Function usage name, may include :: with parent static class.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
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
     * All parsed function arguments.
     *
     * @return Argument[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Get argument by it's position to function or return null
     * if no argument is specified.
     *
     * @param int $index
     * @return Argument|null
     */
    public function getArgument($index)
    {
        return isset($this->arguments[$index]) ? $this->arguments[$index] : null;
    }

    /**
     * Where function usage begins.
     *
     * @return int
     */
    public function getOpenTID()
    {
        return $this->openTID;
    }

    /**
     * Where function usage ends.
     *
     * @return int
     */
    public function getCloseTID()
    {
        return $this->closeTID;
    }

    /**
     * Function is used inside another function.
     *
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Function usage arguments.
     *
     * @param array $arguments
     * @return Argument[]
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * Function usage position.
     *
     * @param string $filename Function usage filename.
     * @param int    $line     Usage line number.
     * @param int    $openTID  Where function usage starts.
     * @param int    $closeTID Where function usage ends.
     * @param int    $level    Function used inside another function.
     */
    public function setLocation($filename, $line, $openTID, $closeTID, $level = 0)
    {
        $this->filename = $filename;
        $this->line = $line;
        $this->openTID = $openTID;
        $this->closeTID = $closeTID;
        $this->level = $level;
    }
}