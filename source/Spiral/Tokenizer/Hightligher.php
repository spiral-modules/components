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
    protected $style = [
        'templates' => [
            'token'       => '<span style="{style}">{line}</span>',
            'line'        => '<div><div class="number">{number}</div>{content}</div>',
            'highlighted' => '<div class="highlighted"><div class="number">{number}</div><div>{content}</div></div>'
        ]
    ];

    /**
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

    /**
     * New Hightligher instance.
     *
     * @param TokenizerInterface $tokenizer
     * @param array              $style
     */
    public function __construct(TokenizerInterface $tokenizer, array $style = [])
    {
        $this->tokenizer = $tokenizer;
        $this->style = $style + $this->style;
    }

    /**
     * Colorize PHP source using given styles. In addition can automatically return only specified
     * line with few lines around it.
     *
     * Example: line = 10, countLines = 10
     * Output: lines from 5 - 15 will be displayed, line 10 will be highlighted.
     *
     * @param string $filename
     * @param int    $line       Keep blank to return full source.
     * @param int    $countLines Total lines to be returned.
     * @return string
     */
    public function highlight($filename, $line = null, $countLines = 10)
    {
        $result = "";
        foreach ($this->tokenizer->fetchTokens($filename) as $position => $token)
        {
            $token[TokenizerInterface::CODE] = htmlentities($token[TokenizerInterface::CODE]);

            foreach ($this->style as $style => $tokens)
            {
                //This way is slower, but more tolerant to memory usage
                if (in_array($token[TokenizerInterface::TYPE], $tokens))
                {
                    if (strpos($token[TokenizerInterface::CODE], "\n"))
                    {
                        $lines = [];
                        foreach (explode("\n", $token[TokenizerInterface::CODE]) as $number)
                        {
                            $lines[] = \Spiral\interpolate(
                                $this->style['templates']['token'], compact('style', 'line')
                            );
                        }

                        $token[TokenizerInterface::CODE] = join("\n", $lines);
                    }
                    else
                    {
                        $token[TokenizerInterface::CODE] = \Spiral\interpolate(
                            $this->style['templates']['token'],
                            ['style' => $style, 'line' => $token[TokenizerInterface::CODE]]
                        );
                    }
                    break;
                }
            }

            $result .= $token[TokenizerInterface::CODE];
        }

        return empty($line) ? $result : $this->fetchLines($result, $line, $countLines);
    }

    /**
     * Fetch only specified lines from source.
     *
     * @param string $source
     * @param int    $line
     * @param int    $countLines
     * @return string
     */
    private function fetchLines($source, $line, $countLines = 10)
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $source));

        $result = "";
        foreach ($lines as $number => $code)
        {
            $number++;
            if ($number >= $line - $countLines && $number <= $line + $countLines)
            {
                $template = $this->style['templates'][$number == $line ? 'highlighted' : 'line'];
                $result .= \Spiral\interpolate($template, [
                    'number'  => $number,
                    'content' => mb_convert_encoding($code, 'utf-8')
                ]);
            }
        }

        return $result;
    }
}