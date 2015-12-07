<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Spiral\Core\Component;
use Spiral\Core\Traits\SaturateTrait;
use Spiral\Tokenizer\Highlighter\Style;

/**
 * Highlights php file using specified style.
 */
class Highlighter extends Component
{
    /**
     * Sugaring.
     */
    use SaturateTrait;

    /**
     * @invisible
     * @var Style
     */
    private $style = null;

    /**
     * @invisible
     * @var array
     */
    private $tokens = [];

    /**
     * Highlighted source.
     *
     * @var string
     */
    private $highlighted = '';

    /**
     * @param string     $source
     * @param Style|null $style
     */
    public function __construct($source, Style $style = null)
    {
        $this->style = !empty($style) ? $style : new Style();
        $this->tokens = token_get_all($source);
    }

    /**
     * Set highlighter styler.
     *
     * @param Style $style
     * @return $this
     */
    public function setStyle(Style $style)
    {
        $this->style = $style;

        return $this;
    }

    /**
     * Get highlighted source.
     *
     * @return string
     */
    public function highlight()
    {
        if (!empty($this->highlighted)) {
            //Nothing to do
            return $this->highlighted;
        }

        $this->highlighted = '';
        foreach ($this->tokens as $tokenID => $token) {
            $this->highlighted .= $this->style->highlightToken(
                $token[TokenizerInterface::TYPE],
                htmlentities($token[TokenizerInterface::CODE])
            );
        }

        return $this->highlighted;
    }

    /**
     * Get only part of php file around specified line.
     *
     * @param int|null $line   Set as null to avoid line highlighting.
     * @param int|null $around Set as null to return every line.
     * @return string
     */
    public function lines($line = null, $around = null)
    {
        //Chinking by lines
        $lines = explode("\n", str_replace("\r\n", "\n", $this->highlight()));

        $result = "";
        foreach ($lines as $number => $code) {
            $human = $number + 1;
            if (
                !empty($around)
                && ($human <= $line - $around || $human >= $line + $around)
            ) {
                //Not included in a range
                continue;
            }

            $result .= $this->style->line(
                $human,
                mb_convert_encoding($code, 'utf-8'),
                $human === $line
            );
        }

        return $result;
    }
}