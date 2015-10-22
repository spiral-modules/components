<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Templater\Behaviours;

use Spiral\Templater\HtmlTokenizer;
use Spiral\Templater\Node;
use Spiral\Templater\Templater;

/**
 * {@inheritdoc}
 *
 * Uses extend token attributes as additional blocks. Every element and tag declared inside html
 * token will go into context block of included node.
 */
class IncludeBehaviour implements IncludeBehaviourInterface
{
    /**
     * Name of block used to represent import context.
     */
    const CONTEXT_BLOCK = 'context';

    /**
     * Location to be included (see Templater->createNode()).
     *
     * @var string
     */
    protected $location = '';

    /**
     * Import context includes everything between opening and closing tag.
     *
     * @var array
     */
    protected $context = [];

    /**
     * Context token.
     *
     * @var array
     */
    protected $token = [];

    /**
     * User able to define custom attributes while importing element, this attributes will be
     * treated as node blocks.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * @var Templater
     */
    protected $templater = null;

    /**
     * @param Templater $templater
     * @param string    $location
     * @param array     $context
     * @param array     $token
     */
    public function __construct(Templater $templater, $location, array $context, array $token = [])
    {
        $this->templater = $templater;
        $this->location = $location;

        $this->context = $context;

        $this->token = $token;
        $this->attributes = $token[HtmlTokenizer::TOKEN_ATTRIBUTES];
    }

    /**
     * {@inheritdoc}
     */
    public function createNode()
    {
        //We need node with content being importer
        $node = $this->templater->createNode($this->location, '', $this->token);

        //Let's register user defined blocks (context and attributes) as placeholders
        $node->registerBlock(
            self::CONTEXT_BLOCK,
            [],
            [$this->createPlaceholder(self::CONTEXT_BLOCK, $contextID)],
            true
        );

        foreach ($this->attributes as $attribute => $value) {
            //Attributes counted as blocks to replace elements in included node
            $node->registerBlock($attribute, [], [$value], true);
        }

        //We now have to compile node content to pass it's body to parent node
        $content = $node->compile($outerBlocks);

        //Outer blocks (usually user attributes) can be exported to template using non default
        //rendering technique, for example every "extra" attribute can be passed to specific
        //template location. Templater to decide.
        $content = $this->templater->exportBlocks($content, $outerBlocks);

        //Let's parse complied content without any imports (to prevent collision)
        $templater = clone $this->templater;
        $templater->flushImports();

        //Outer content must be protected using unique names
        $rebuilt = new Node($templater, $templater->uniquePlaceholder(), $content);

        if ($contextBlock = $rebuilt->findNode($contextID)) {
            //Now we can mount our content block
            $contextBlock->addNode($this->getContext());
        }

        return $rebuilt;
    }

    /**
     * Pack node context (everything between open and close tag).
     *
     * @return Node
     */
    protected function getContext()
    {
        $context = '';

        foreach ($this->context as $token) {
            $context .= $token[HtmlTokenizer::TOKEN_CONTENT];
        }

        return new Node($this->templater, $this->templater->uniquePlaceholder(), $context);
    }

    /**
     * Create placeholder block (to be injected with inner blocks defined in context).
     *
     * @param string $name
     * @param string $blockID
     * @return string
     */
    protected function createPlaceholder($name, &$blockID)
    {
        $blockID = $name . '-' . $this->templater->uniquePlaceholder();

        //Short block declaration syntax
        return '${' . $blockID . '}';
    }
}