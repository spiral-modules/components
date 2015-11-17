<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Templater;

use Spiral\Templater\Behaviours\BlockBehaviour;
use Spiral\Templater\Behaviours\ExtendsBehaviour;
use Spiral\Templater\Behaviours\IncludeBehaviour;
use Spiral\Templater\Exceptions\TemplaterException;
use Spiral\Templater\Exporters\AttributeExporter;
use Spiral\Templater\Imports\StopImport;

/**
 * Templater uses html constructions parsed via HtmlTokenizer and describes them for Node classes.
 * Class support following functionality:
 * - extend parent view/node with inherited blocks
 * - declare namespace or alias to import outer view
 * - importing outer view
 *
 * P.S. Do not even think to use Templater without caching layer at top of it, templater not just
 * slow, it's SUPER SLOW (per design).
 */
abstract class Templater implements SupervisorInterface
{
    /**
     * Basic templater behaviours.
     */
    const TYPE_BLOCK   = 'block';
    const TYPE_EXTENDS = 'extends';
    const TYPE_IMPORT  = 'use';
    const TYPE_INCLUDE = 'include';

    /**
     * Used to create unique node names when required.
     *
     * @var int
     */
    private static $index = 0;

    /**
     * Active set of imports.
     *
     * @var ImportInterface[]
     */
    private $imports = [];

    /**
     * Templater syntax options, syntax and names. Every option is required.
     *
     * @var array
     */
    protected $options = [
        'strictMode' => false,
        'prefixes'   => [
            self::TYPE_BLOCK   => ['block:', 'section:', 'yield:', 'define:'],
            self::TYPE_EXTENDS => ['extends:'],
            self::TYPE_IMPORT  => ['use', 'node:use', 'templater:use']
        ],
        'exporters'  => [
            AttributeExporter::class
        ]
    ];

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function isStrictMode()
    {
        return $this->options['strictMode'];
    }

    /**
     * Add new elements import locator.
     *
     * @param ImportInterface $import
     */
    public function addImport(ImportInterface $import)
    {
        array_unshift($this->imports, $import);
    }

    /**
     * Active templater imports.
     *
     * @return ImportInterface[]
     */
    public function getImports()
    {
        return $this->imports;
    }

    /**
     * Remove all element importers.
     */
    public function flushImports()
    {
        $this->imports = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getBehaviour(array $token, array $content, Node $node)
    {
        switch ($this->tokenType($token, $name)) {
            case self::TYPE_BLOCK:
                //Tag declares block (section)
                return new BlockBehaviour($name);

            case self::TYPE_EXTENDS:
                //Declares parent extending
                $extends = new ExtendsBehaviour(
                    $this->createNode($this->fetchLocation($name, $token), '', $token),
                    $token
                );

                //We have to combine parent imports with local one
                $this->imports = $extends->getImports();

                //Sending command to extend parent
                return $extends;

            case self::TYPE_IMPORT:
                //Implementation specific
                $this->registerImport($name, $token);

                //No need to include import tag into source
                return BehaviourInterface::SKIP_TOKEN;
        }

        //We now have to decide if element points to external view (source) to be imported
        foreach ($this->imports as $import) {
            if ($import->isImported($name, $token)) {
                if ($import instanceof StopImport) {
                    //Native importer tells us to treat this element as simple html
                    break;
                }

                //Let's include!
                return new IncludeBehaviour(
                    $this,
                    $import->getLocation($name, $token),
                    $content,
                    $token
                );
            }
        }

        return BehaviourInterface::SIMPLE_TAG;
    }

    /**
     * Outer blocks (usually user attributes) can be exported to template using non default
     * rendering technique, for example every "extra" attribute can be passed to specific template
     * location.
     *
     * @param string $content
     * @param array  $blocks
     * @return string
     */
    public function exportBlocks($content, array $blocks)
    {
        foreach ($this->options['exporters'] as $exporter) {
            /**
             * @var ExporterInterface $exporter
             */
            $exporter = new $exporter($content, $blocks);

            //Exporting
            $content = $exporter->mountBlocks();
        }

        return $content;
    }

    /**
     * Create node using specific location definition.
     *
     * @see fetchLocation()
     * @see getSource()
     * @param mixed  $location Location compatible with fetchLocation method.
     * @param string $name     If not specified unique name will be used.
     * @param array  $token    Token used only to clarify location at exceptions.
     * @return Node
     * @throws TemplaterException
     */
    public function createNode($location, $name = '', array $token = [])
    {
        $source = $this->getSource($location, $templater, $token);

        return new Node($templater, !empty($name) ? $name : $this->uniquePlaceholder(), $source);
    }

    /**
     * Fetch implementation specific location of external node source. You can count it as filename.
     *
     * @param string $name Resolved (no prefix) element name.
     * @param array  $token
     * @return mixed
     * @throws TemplaterException
     */
    abstract public function fetchLocation($name, array $token = []);

    /**
     * Get unique placeholder name, unique names are required in some cases to correctly process
     * includes and etc.
     *
     * @return string
     */
    public function uniquePlaceholder()
    {
        return md5(self::$index++);
    }

    /**
     * Must parse token and element name to register (or not) instance of ImportInterface.
     *
     * @see addImport()
     * @param array $name
     * @param array $token
     * @throws TemplaterException
     */
    abstract protected function registerImport($name, array $token);

    /**
     * Must fetch source from implementation specific location.
     *
     * @param mixed     $location
     * @param Templater $templater Source specific templater, reference.
     * @param array     $token     Token used only to clarify location at exceptions.
     * @return string
     * @throws TemplaterException
     */
    abstract protected function getSource(
        $location,
        Templater &$templater = null,
        array $token = []
    );

    /**
     * Helper method used to define tag type based on defined templater syntax.
     *
     * @param string $token
     * @param string $name Tag name stripped from prefix will go there.
     * @return int|null|string
     */
    protected function tokenType($token, &$name)
    {
        $name = $token[HtmlTokenizer::TOKEN_NAME];
        foreach ($this->options['prefixes'] as $type => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    //We found prefix pointing to needed behaviour
                    $name = substr($name, strlen($prefix));

                    return $type;
                }
            }
        }

        return null;
    }
}
