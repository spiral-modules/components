<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Tokenizer;

use Spiral\Tokenizer\Exceptions\IsolatorException;

/**
 * Isolators used to find and replace php blocks in given source. Can
 * be used by view processors, or to remove php code from some string.
 */
class Isolator
{
    /**
     * Found PHP blocks to be replaced.
     *
     * @var array
     */
    private $phpBlocks = [];

    /**
     * Isolation prefix. Use any values that will not corrupt HTML or
     * other source.
     *
     * @var string
     */
    private $prefix = '';

    /**
     * Isolation postfix. Use any values that will not corrupt HTML
     * or other source.
     *
     * @var string
     */
    private $postfix = '';

    /**
     * @param string $prefix  Replaced block prefix, -php by default.
     * @param string $postfix Replaced block postfix, block- by default.
     */
    public function __construct($prefix = '-php-', $postfix = '-block-')
    {
        $this->prefix = $prefix;
        $this->postfix = $postfix;
    }

    /**
     * Isolates all returned PHP blocks with a defined pattern. Method uses
     * token_get_all function. Resulted source have all php blocks replaced
     * with non executable placeholder.
     *
     * @param string $source
     *
     * @return string
     */
    public function isolatePHP($source)
    {
        $phpBlock = false;

        $isolated = '';
        foreach (token_get_all($source) as $token) {
            if ($this->isOpenTag($token)) {
                $phpBlock = $token[1];

                continue;
            }

            if ($this->isCloseTag($token)) {
                $blockID = $this->uniqueID();

                $this->phpBlocks[$blockID] = $phpBlock . $token[1];
                $isolated .= $this->placeholder($blockID);

                $phpBlock = '';

                continue;
            }

            $tokenContent = is_array($token) ? $token[1] : $token;

            if (!empty($phpBlock)) {
                $phpBlock .= $tokenContent;
            } else {
                $isolated .= $tokenContent;
            }
        }

        return $isolated;
    }

    /**
     * Replace every isolated block.
     *
     * @deprecated Use setBlock instead!
     *
     * @param array $blocks
     *
     * @return $this
     */
    public function setBlocks(array $blocks)
    {
        $this->phpBlocks = $blocks;

        return $this;
    }

    /**
     * Set block content by id.
     *
     * @param string $blockID
     * @param string $source
     *
     * @return $this
     *
     * @throws IsolatorException
     */
    public function setBlock($blockID, $source)
    {
        if (!isset($this->phpBlocks[$blockID])) {
            throw new IsolatorException("Undefined block {$blockID}");
        }

        $this->phpBlocks[$blockID] = $source;

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
     * Restore PHP blocks position in isolated source (isolatePHP() must
     * be already called).
     *
     * @param string $source
     *
     * @return string
     */
    public function repairPHP($source)
    {
        return preg_replace_callback(
            $this->blockRegex(),
            function ($match) {
                if (!isset($this->phpBlocks[$match['id']])) {
                    return $match[0];
                }

                return $this->phpBlocks[$match['id']];
            },
            $source
        );
    }

    /**
     * Remove PHP blocks from isolated source (isolatePHP() must be
     * already called).
     *
     * @param string $isolatedSource
     *
     * @return string
     */
    public function removePHP($isolatedSource)
    {
        return preg_replace($this->blockRegex(), '', $isolatedSource);
    }

    /**
     * Reset isolator state.
     */
    public function reset()
    {
        $this->phpBlocks = [];
    }

    /**
     * @return string
     */
    private function blockRegex()
    {
        return '/' .
        preg_quote($this->prefix)
        . '(?P<id>[0-9a-z]+)'
        . preg_quote($this->postfix)
        . '/';
    }

    /**
     * @return string
     */
    private function uniqueID()
    {
        return md5(count($this->phpBlocks) . uniqid(true));
    }

    /**
     * @param int $blockID
     *
     * @return string
     */
    private function placeholder($blockID)
    {
        return $this->prefix . $blockID . $this->postfix;
    }

    /**
     * @param mixed $token
     *
     * @return bool
     */
    private function isOpenTag($token)
    {
        if (!is_array($token)) {
            return false;
        }

        if ($token[0] == T_ECHO && $token[1] == '<?=') {
            //todo Find out why HHVM behaves differently or create issue
            return true;
        }

        return $token[0] == T_OPEN_TAG || $token[0] == T_OPEN_TAG_WITH_ECHO;
    }

    /**
     * @param mixed $token
     *
     * @return bool
     */
    public function isCloseTag($token)
    {
        if (!is_array($token)) {
            return false;
        }

        return $token[0] == T_CLOSE_TAG;
    }
}
