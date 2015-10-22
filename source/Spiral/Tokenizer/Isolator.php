<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Spiral\Core\Component;

/**
 * Isolators used to find and replace php blocks in given source. Can be used by view processors,
 * or to remove php core from some string.
 */
class Isolator extends Component
{
    /**
     * Unique block id, required to generate unique placeholders.
     *
     * @var int
     */
    private static $blockID = 0;

    /**
     * Found PHP blocks to be replaced.
     *
     * @var array
     */
    private $phpBlocks = [];

    /**
     * Isolated prefix and postfix. Use any values that will not corrupt HTML or other source.
     *
     * @var string
     */
    private $prefix = '';
    private $postfix = '';

    /**
     * Set of patterns to convert "disabled" php blocks into catchable (in terms of token_get_all)
     * code. Will fix short tags using regular expressions.
     *
     * @var array
     */
    private $patterns = [];

    /**
     * Temporary block replaces. Used to "enable" php blocks.
     *
     * @var array
     */
    private $replaces = [];

    /**
     * New php isolator.
     *
     * @param string $prefix    Replaced block prefix, -php by default.
     * @param string $postfix   Replaced block postfix, block- by default.
     * @param bool   $shortTags Handle short tags. This is not required if short_tags are enabled.
     */
    public function __construct($prefix = '-php-', $postfix = '-block-', $shortTags = true)
    {
        $this->prefix = $prefix;
        $this->postfix = $postfix;

        if ($shortTags) {
            $this->addPattern('<?=', false, "<?php /*%s*/ echo ");
            $this->addPattern('<?', '/<\?(?!php)/is');
        }
    }

    /**
     * Isolates all returned PHP blocks with a defined pattern. Method uses token_get_all function.
     *
     * @param string $source
     * @return string
     */
    public function isolatePHP($source)
    {
        //Replacing all
        $source = $this->replaceTags($source);
        $tokens = token_get_all($source);

        $this->phpBlocks = [];
        $phpBlock = false;
        $blockID = 0;

        $source = '';
        foreach ($tokens as $token) {
            if ($token[0] == T_OPEN_TAG || $token[0] == T_OPEN_TAG_WITH_ECHO) {
                $phpBlock = $token[1];
                continue;
            }

            if ($token[0] == T_CLOSE_TAG) {
                $phpBlock .= $token[1];
                $this->phpBlocks[$blockID] = $phpBlock;
                $phpBlock = '';

                $source .= $this->prefix . ($blockID++) . $this->postfix;

                continue;
            }

            if (!empty($phpBlock)) {
                $phpBlock .= is_array($token) ? $token[1] : $token;
            } else {
                $source .= is_array($token) ? $token[1] : $token;
            }
        }

        foreach ($this->phpBlocks as &$phpBlock) {
            //Will repair php source with correct (original) tags
            $phpBlock = $this->restoreTags($phpBlock);
            unset($phpBlock);
        }

        //Will restore tags which were replaced but weren't handled by php (for example string
        //contents)
        return $this->restoreTags($source);
    }

    /**
     * Restore PHP blocks position in isolated source (isolatePHP() must be already called).
     *
     * @param string $source
     * @return string
     */
    public function repairPHP($source)
    {
        return preg_replace_callback(
            '/' . preg_quote($this->prefix) . '(?P<id>[0-9]+)' . preg_quote($this->postfix) . '/',
            [$this, 'getBlock'],
            $source
        );
    }

    /**
     * Remove PHP blocks from isolated source (isolatePHP() must be already called).
     *
     * @param string $isolatedSource
     * @return string
     */
    public function removePHP($isolatedSource)
    {
        return preg_replace(
            '/' . preg_quote($this->prefix) . '(?P<id>[0-9]+)' . preg_quote($this->postfix) . '/',
            '',
            $isolatedSource
        );
    }

    /**
     * Update isolator php blocks.
     *
     * @param array $phpBlocks
     * @return $this
     */
    public function setBlocks($phpBlocks)
    {
        $this->phpBlocks = $phpBlocks;

        return $this;
    }

    /**
     * List of all found and replaced php blocks.
     *
     * @return array
     */
    public function getBlocks()
    {
        return $this->phpBlocks;
    }

    /**
     * Reset isolator state.
     */
    public function reset()
    {
        $this->phpBlocks = $this->replaces = [];
    }

    /**
     * New pattern to fix untrackable blocks.
     *
     * @param string $name
     * @param string $regexp
     * @param string $replace
     */
    protected function addPattern($name, $regexp = null, $replace = "<?php /*%s*/")
    {
        $this->patterns[$name] = [
            'regexp'  => $regexp,
            'replace' => $replace
        ];
    }

    /**
     * Get PHP block by it's ID.
     *
     * @param int $blockID
     * @return mixed
     */
    protected function getBlock($blockID)
    {
        if (!isset($this->phpBlocks[$blockID['id']])) {
            return $blockID[0];
        }

        return $this->phpBlocks[$blockID['id']];
    }

    /**
     * Replace all matched tags with their <?php equivalent. These tags will be detected and parsed
     * by token_get_all() function even if there isn't a directive in php.ini file.
     *
     * @param string $source
     * @return string
     */
    private function replaceTags($source)
    {
        $replaces = &$this->replaces;
        foreach ($this->patterns as $tag => $pattern) {
            if (empty($pattern['regexp'])) {
                if ($replace = array_search($tag, $replaces)) {
                    $source = str_replace($tag, $replace, $source);
                    continue;
                }

                $replace = sprintf($pattern['replace'], $this->getPlaceholder());
                $replaces[$replace] = $tag;

                //Replacing
                $source = str_replace($tag, $replace, $source);
                continue;
            }

            $source = preg_replace_callback($pattern['regexp'],
                function ($tag) use (&$replaces, $pattern) {
                    $tag = $tag[0];

                    if ($key = array_search($tag, $replaces)) {
                        return $key;
                    }

                    $replace = sprintf($pattern['replace'], $this->getPlaceholder());
                    $replaces[$replace] = $tag;

                    return $replace;
                }, $source);
        }

        return $source;
    }

    /**
     * Fix blocks altered by replaceTags() method.
     *
     * @see replaceTags()
     * @param string $source
     * @return string
     */
    private function restoreTags($source)
    {
        return strtr($source, $this->replaces);
    }

    /**
     * Get unique block placeholder for replacement.
     *
     * @return string
     */
    private function getPlaceholder()
    {
        return md5(self::$blockID++ . '-' . uniqid('', true));
    }
}