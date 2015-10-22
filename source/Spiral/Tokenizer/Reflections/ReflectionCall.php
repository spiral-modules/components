<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer\Reflections;

/**
 * ReflectionCall used to represent function or static method call found by ReflectionFile. This
 * reflection is very useful for static analysis and mainly used in Translator component to index
 * translation function usages.
 */
class ReflectionCall
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
    private $name = '';

    /**
     * @var string
     */
    private $class = '';

    /**
     * @var string
     */
    private $source = '';

    /**
     * @var ReflectionArgument[]
     */
    private $arguments = [];

    /**
     * @var int
     */
    private $openTID = 0;

    /**
     * @var int
     */
    private $closeTID = 0;

    /**
     * Was a function used inside another function call?
     *
     * @var int
     */
    private $level = 0;

    /**
     * New call reflection.
     *
     * @param string $filename
     * @param int    $line
     * @param string $class
     * @param string $name
     * @param array  $arguments
     * @param string $source
     * @param int    $openTID
     * @param int    $closeTID
     * @param int    $level
     */
    public function __construct(
        $filename,
        $line,
        $class,
        $name,
        array $arguments,
        $source,
        $openTID,
        $closeTID,
        $level
    ) {
        $this->filename = $filename;
        $this->line = $line;
        $this->class = $class;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->source = $source;
        $this->openTID = $openTID;
        $this->closeTID = $closeTID;
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
     * Function usage name, may include :: with a parent static class.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
     * All parsed function arguments.
     *
     * @return ReflectionArgument[]
     */
    public function getArguments()
    {
        return $this->arguments;
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
}