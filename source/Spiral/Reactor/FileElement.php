<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Reactor;

use Spiral\Files\FilesInterface;

class FileElement extends NamespaceElement
{
    /**
     * PHP file open tag.
     */
    const PHP_OPEN = '<?php';

    /**
     * All elements nested to the PHP file, that can include classes and namespaces.
     *
     * @var AbstractElement[]
     */
    protected $elements = [];

    /**
     * FileManager component.
     *
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * New instance of RPHPFile, class used to write Reactor declarations to specified filename.
     *
     * @param mixed          $namespace
     * @param FilesInterface $files
     */
    public function __construct($namespace = null, FilesInterface $files)
    {
        parent::__construct($namespace);
        $this->files = $files;
    }

    /**
     * Adding a new reactor element to a file.
     *
     * @param AbstractElement $element
     * @return $this
     */
    public function addElement(AbstractElement $element)
    {
        $this->elements[] = $element;

        return $this;
    }

    /**
     * Add a new class declaration to a file.
     *
     * @param ClassElement $class
     * @return $this
     */
    public function addClass(ClassElement $class)
    {
        $this->elements[] = $class;

        return $this;
    }

    /**
     * Render the PHP file's code and deliver it to a given filename.
     *
     * @param string $filename        Filename to render code into.
     * @param int    $mode            Use File::RUNTIME for 777 and File::READONLY for application
     *                                files.
     * @param bool   $ensureDirectory If true, helper will ensure that the destination directory exists
     *                                and has the correct permissions.
     * @return bool
     */
    public function renderFile($filename, $mode = FilesInterface::RUNTIME, $ensureDirectory = false)
    {
        return $this->files->write($filename, $this->render(), $mode, $ensureDirectory);
    }

    /**
     * Render element declaration. This method should be declared in RElement child classes and perform
     * an operation for rendering the specific type of content. RPHPFile will render all nested classes
     * and namespaces into valid (in terms of syntax) php code.
     *
     * @param int $indentLevel Tabulation level.
     * @return string
     */
    public function render($indentLevel = 0)
    {
        $result = [self::PHP_OPEN, trim($this->renderComment($indentLevel))];

        if ($this->name)
        {
            $result[] = 'namespace ' . $this->name . ';';
        }

        //Uses
        foreach ($this->uses as $class)
        {
            $result[] = $this->indent('use ' . $class . ';', $indentLevel);
        }

        //Classes
        foreach ($this->elements as $element)
        {
            $result[] = $element->render($indentLevel);
        }

        return $this->join($result, $indentLevel);
    }
}