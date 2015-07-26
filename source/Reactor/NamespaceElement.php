<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Reactor;

class NamespaceElement extends Element
{
    /**
     * List of classes which are declared in this namespace.
     *
     * @var ClassElement[]
     */
    protected $classes = [];

    /**
     * Namespace uses.
     *
     * @var array
     */
    protected $uses = [];

    /**
     * Add a new class declaration to namespace.
     *
     * @param ClassElement $class
     * @return $this
     */
    public function addClass(ClassElement $class)
    {
        $this->classes[] = $class;

        return $this;
    }

    /**
     * Get all classes being used.
     *
     * @return array
     */
    public function getUses()
    {
        return $this->uses;
    }

    /**
     * Add a new class usage to namespace.
     *
     * @param string $class Class name.
     * @return $this
     */
    public function addUse($class)
    {
        if (array_search($class, $this->uses) === false)
        {
            $this->uses[] = $class;
        }

        return $this;
    }

    /**
     * Replace all used classes with a new given list.
     *
     * @param array $uses
     * @return $this
     */
    public function setUses(array $uses)
    {
        $this->uses = $uses;

        return $this;
    }

    /**
     * Render element declaration. This method should be declared in RElement child classes and perform
     * an operation for rendering a specific type of content. Renders namespace section with it's
     * classes, uses and comments.
     *
     * @param int           $indentLevel Tabulation level.
     * @param ArrayExporter $exporter    Custom array exporter for properties.
     * @return string
     */
    public function createDeclaration($indentLevel = 0, ArrayExporter $exporter = null)
    {
        $result = [$this->renderComment($indentLevel)];

        $result[] = 'namespace ' . trim($this->name, '\\');
        $result[] = "{";

        //Uses
        foreach ($this->uses as $class)
        {
            $result[] = $this->indent('use ' . $class . ';', $indentLevel + 1);
        }

        //Classes
        foreach ($this->classes as $class)
        {
            $result[] = $class->render($indentLevel + 1, $exporter);
        }

        $result[] = '}';

        return $this->join($result, $indentLevel);
    }
}