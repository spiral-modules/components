<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tokenizer;

use Spiral\Core\Component;

/**
 * Highlighters used mainly by spiral exceptions to make it more user friendly, it can colorize
 * php source using given styles and return only required set of lines.
 */
class Hightligher extends Component
{
    /**
     * Set of highlighting rules in a form CSS_STYLE => [TOKENS].
     *
     * @var array
     */
    protected $options = [
        'templates' => [
            'token'       => "<span style=\"{style}\">{token}</span>",
            'line'        => "<div><span class=\"number\">{number}</span>{source}</div>\n",
            'highlighted' => "<div class=\"highlighted\"><span class=\"number\">{number}</span>{source}</div>\n"
        ],
        'styles'    => []
    ];

    /**
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

    /**
     * New Hightligher instance.
     *
     * @param TokenizerInterface $tokenizer
     * @param array              $options
     */
    public function __construct(TokenizerInterface $tokenizer, array $options = [])
    {
        $this->tokenizer = $tokenizer;
        $this->options = $options + $this->options;
    }

    /**
     * Colorize PHP source using given styles. In addition can automatically return only specified
     * line with few lines around it.
     *
     * Example: target = 10, return = 10
     * Output: lines from 5 - 15 will be displayed, line 10 will be highlighted.
     *
     * @param string $filename
     * @param int    $target Target line to highlight.
     * @param int    $return Total lines to be returned.
     * @return string
     */
    public function highlight($filename, $target = null, $return = 10)
    {
        $result = "";
        foreach ($this->tokenizer->fetchTokens($filename) as $position => $token)
        {
            $source = htmlentities($token[TokenizerInterface::CODE]);
            foreach ($this->options['styles'] as $style => $tokens)
            {
                if (!in_array($token[TokenizerInterface::TYPE], $tokens))
                {
                    continue;
                }

                if (strpos($source, "\n") === false)
                {
                    $source = \Spiral\interpolate($this->options['templates']['token'], [
                        'style' => $style,
                        'token' => $token[TokenizerInterface::CODE]
                    ]);

                    break;
                }

                $lines = [];
                foreach (explode("\n", $source) as $line)
                {
                    $lines[] = \Spiral\interpolate($this->options['templates']['token'], [
                        'style' => $style,
                        'token' => $line
                    ]);
                }

                $source = join("\n", $lines);
            }

            $result .= $source;
        }

        return $this->lines($result, (int)$target, $return);
    }

    /**
     * Prepare highlighted lines for output.
     *
     * @param string $source
     * @param int    $target
     * @param int    $return
     * @return string
     */
    private function lines($source, $target = null, $return = 10)
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $source));

        $result = "";
        foreach ($lines as $number => $code)
        {
            $number++;
            if (empty($return) || $number >= $target - $return && $number <= $target + $return)
            {
                $template = $this->options['templates'][$number === $target ? 'highlighted' : 'line'];
                $result .= \Spiral\interpolate($template, [
                    'number' => $number,
                    'source' => mb_convert_encoding($code, 'utf-8')
                ]);
            }
        }

        return $result;
    }
}