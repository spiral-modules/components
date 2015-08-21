<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Templater;

use Spiral\Templater\Behaviours\BlockBehaviourInterface;
use Spiral\Templater\Behaviours\ExtendsBehaviourInterface;
use Spiral\Templater\Behaviours\IncludeBehaviourInterface;
use Spiral\Templater\Exceptions\StrictModeException;

/**
 * Template Node represents simple XML like tree of blocks defined by behaviours provided by it's
 * supervisor. Node utilizes HtmlTokenizer to create set of tokens being feeded to supervisor.
 */
class Node
{
    /**
     * Short tags expression, usually used inside attributes and etc.
     */
    const SHORT_TAGS = '/\${(?P<name>[a-z0-9_\.\-]+)(?: *\| *(?P<default>[^}]+) *)?}/i';

    /**
     * Node name (usually related to block name).
     *
     * @var string
     */
    private $name = '';

    /**
     * Indication that node extended parent layout/node, meaning custom blocks can not be rendered
     * outside defined parent layout.
     *
     * @var bool
     */
    private $extended = false;

    /**
     * Set of child nodes being used during rendering.
     *
     * @var string[]|Node[]
     */
    private $nodes = [];

    /**
     * Set of blocks defined outside parent scope (parent layout blocks), blocks like either dynamic
     * or used for internal template reasons. They should not be rendered in plain HTML (but can be
     * used by Exporters to render as something else).
     *
     * @var Node[]
     */
    private $outerBlocks = [];

    /**
     * NodeSupervisor responsible for resolve tag behaviours.
     *
     * @invisible
     * @var SupervisorInterface
     */
    protected $supervisor = null;

    /**
     * @param SupervisorInterface $supervisor
     * @param string              $name
     * @param string|array        $source    String content or array of html tokens.
     * @param HtmlTokenizer       $tokenizer Html tokens source.
     */
    public function __construct(
        SupervisorInterface $supervisor,
        $name,
        $source = [],
        HtmlTokenizer $tokenizer = null
    ) {
        $this->supervisor = $supervisor;
        $this->name = $name;

        $tokenizer = !empty($tokenizer) ? $tokenizer : new HtmlTokenizer();
        $this->parseTokens(is_string($source) ? $tokenizer->parse($source) : $source);
    }

    /**
     * @return SupervisorInterface
     */
    public function getSupervisor()
    {
        return $this->supervisor;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set of blocks defined outside parent scope (parent layout blocks), blocks like either dynamic
     * or used for internal template reasons. They should not be rendered in plain HTML (but can be
     * used by Exporters to render as something else).
     *
     * @return Node[]
     */
    public function getOuterBlocks()
    {
        return $this->outerBlocks;
    }

    /**
     * Add sub node.
     *
     * @param Node $node
     */
    public function addNode(Node $node)
    {
        $this->nodes[] = $node;
    }

    /**
     * Recursively find a children node by it's name.
     *
     * @param string $name
     * @return Node|null
     */
    public function findNode($name)
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof self && $node->name) {
                if ($node->name === $name) {
                    return $node;
                }

                if ($found = $node->findNode($name)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Create new block under current node. If node extends parent, block will ether replace parent
     * content or will be added as outer block (block with parent placeholder).
     *
     * @param string       $name
     * @param string|array $source     String content or array of html tokens.
     * @param array        $forceNodes Used to redefine node content and bypass token parsing.
     * @param bool         $replace    Set to true to send created Node directly to outer blocks.
     */
    public function registerBlock($name, $source, $forceNodes = [], $replace = false)
    {
        $node = new Node($this->supervisor, $name, $source);

        if (!empty($forceNodes)) {
            $node->nodes = $forceNodes;
        }

        if (!$this->extended && !$replace) {
            $this->nodes[] = $node;

            return;
        }

        if (empty($parent = $this->findNode($name))) {
            $this->outerBlocks[] = $node;

            return;
        }

        //We have to replace parent content with extended blocks
        $parent->replaceNode($node);
    }

    /**
     * Compile node data (inner nodes) into string.
     *
     * @param array $outerBlocks All outer blocks will be aggregated in this array (in compiled
     *                           form).
     * @param array $compiled    Internal complication memory (method called recursively)
     * @return string
     */
    public function compile(&$outerBlocks = [], array &$compiled = [])
    {
        $compiled = is_array($compiled) ? $compiled : [];
        $outerBlocks = is_array($outerBlocks) ? $outerBlocks : [];

        //We have to pre-compile outer nodes first
        foreach ($this->outerBlocks as $node) {
            if ($node instanceof self && !array_key_exists($node->name, $compiled)) {
                //We don't need outer blocks from deeper level (right?)
                $nestedOuter = [];

                //Node was never compiled
                $outerBlocks[$node->name] = $compiled[$node->name] = $node->compile(
                    $nestedOuter,
                    $compiled
                );
            }
        }

        if ($this->nodes === [null]) {
            //Valueless attributes
            return null;
        }

        $result = '';
        foreach ($this->nodes as $node) {
            if (is_string($node) || is_null($node)) {
                $result .= $node;
                continue;
            }

            if (!array_key_exists($node->name, $compiled)) {
                //We don't need outer blocks from deeper level (right?)
                $nestedOuter = [];

                //Node was never compiled
                $compiled[$node->name] = $node->compile($nestedOuter, $compiled);
            }

            $result .= $compiled[$node->name];
        }

        return $result;
    }

    /**
     * Once supervisor defined custom token behaviour we can process it's content accordingly.
     *
     * @param BehaviourInterface $behaviour
     * @param array              $content
     */
    public function applyBehaviour(BehaviourInterface $behaviour, array $content = [])
    {
        if ($behaviour instanceof ExtendsBehaviourInterface) {
            //We have to copy nodes from parent
            $this->nodes = $behaviour->getParent()->nodes;

            //Indication that this node has parent, meaning we have to handle blocks little
            //bit different way
            $this->extended = true;

            foreach ($behaviour->getBlocks() as $block => $blockContent) {
                //Blocks defined at moment of import
                $this->registerBlock($block, $blockContent);
            }

            return;
        }

        if ($behaviour instanceof BlockBehaviourInterface) {
            //Registering block
            $this->registerBlock($behaviour->getName(), $content);

            return;
        }

        if ($behaviour instanceof IncludeBehaviourInterface) {
            //We got external content as Node
            $this->nodes[] = $behaviour->createNode();
        }
    }

    /**
     * Parse set of tokens provided by html Tokenizer and create blocks and other control
     * constructions. Basically it will try to created html tree.
     *
     * @param array $tokens
     * @throws StrictModeException
     */
    protected function parseTokens(array $tokens)
    {
        //Current active token
        $activeToken = [];

        //Some blocks can be named as parent. We have to make sure we closing the correct one
        $activeLevel = 0;

        //Content to represent full tag declaration (including body)
        $activeContent = [];

        foreach ($tokens as $token) {
            $tokenType = $token[HtmlTokenizer::TOKEN_TYPE];

            if (empty($activeToken)) {
                switch ($tokenType) {
                    case HtmlTokenizer::TAG_VOID:
                    case HtmlTokenizer::TAG_SHORT:
                        $this->registerToken($token);
                        break;

                    case HtmlTokenizer::TAG_OPEN:
                        $activeToken = $token;
                        break;

                    case HtmlTokenizer::TAG_CLOSE:
                        if ($this->supervisor) {
                            throw new StrictModeException(
                                "Unpaired close tag '{$token[HtmlTokenizer::TOKEN_NAME]}'.",
                                $token
                            );
                        }
                        break;
                    case HtmlTokenizer::PLAIN_TEXT:
                        //Everything outside any tag
                        $this->registerContent([$token]);
                        break;
                }

                continue;
            }

            if (
                $tokenType != HtmlTokenizer::PLAIN_TEXT
                && $token[HtmlTokenizer::TOKEN_NAME] == $activeToken[HtmlTokenizer::TOKEN_NAME]
            ) {
                if ($tokenType == HtmlTokenizer::TAG_OPEN) {
                    $activeContent[] = $token;
                    $activeLevel++;
                } elseif ($tokenType == HtmlTokenizer::TAG_CLOSE) {
                    if ($activeLevel === 0) {
                        //Closing current token
                        $this->registerToken($activeToken, $activeContent, $token);
                        $activeToken = $activeContent = [];
                    } else {
                        $activeContent[] = $token;
                        $activeLevel--;
                    }
                } else {
                    //Short tag with same name (used to call for parent content)s
                    $activeContent[] = $token;
                }

                continue;
            }

            //Collecting token content
            $activeContent[] = $token;
        }

        //Everything after last tag
        $this->registerContent($activeContent);
    }

    /**
     * Once token content (nested tags and text) is correctly collected we can pass it to supervisor
     * to check what we actually should be doing with this token.
     *
     * @param array $token
     * @param array $content
     * @param array $closeToken Token described close tag of html element.
     */
    private function registerToken(array $token, array $content = [], array $closeToken = [])
    {
        $behaviour = $this->supervisor->getBehaviour($token, $content, $this);

        //Let's check token behaviour to understand how to handle this token
        if ($behaviour === BehaviourInterface::SKIP_TOKEN) {
            //This is some technical tag (import and etc)
            return;
        }

        if ($behaviour === BehaviourInterface::SIMPLE_TAG) {

            //Nothing really to do with this tag
            $this->registerContent([$token]);

            //Let's parse inner content
            $this->parseTokens($content);

            !empty($closeToken) && $this->registerContent([$closeToken]);

            return;
        }

        //Now we have to process more complex behaviours
        $this->applyBehaviour($behaviour, $content);
    }

    /**
     * Register string node content.
     *
     * @param string|array $content String content or html tokens.
     */
    private function registerContent($content)
    {
        if ($this->extended || empty($content)) {
            //No blocks or text can exists outside parent template blocks
            return;
        }

        if (is_array($content)) {
            $plainContent = '';
            foreach ($content as $token) {
                $plainContent .= $token[HtmlTokenizer::TOKEN_CONTENT];
            }

            $content = $plainContent;
        }

        //Looking for short tag definitions (${title|DEFAULT})
        if (preg_match(self::SHORT_TAGS, $content, $matches)) {
            $chunks = explode($matches[0], $content);

            //We expecting first chunk to be string (before block)
            $this->registerContent(array_shift($chunks));

            $this->registerBlock(
                $matches['name'],
                isset($matches['default']) ? $matches['default'] : ''
            );

            //Rest of content (after block)
            $this->registerContent(join($matches[0], $chunks));

            return;
        }

        if (is_string(end($this->nodes))) {
            $this->nodes[key($this->nodes)] .= $content;

            return;
        }

        $this->nodes[] = $content;
    }

    /**
     * Replace node content with content provided by external node, external node can still use
     * content of parent block by defining block named identical to it's parent.
     *
     * @param Node $node
     */
    private function replaceNode(Node $node)
    {
        //Looking for parent block call
        if (!empty($inner = $node->findNode($this->name))) {
            //This construction allows child block use parent content
            $inner->nodes = $this->nodes;
        }

        $this->nodes = $node->nodes;
    }
}