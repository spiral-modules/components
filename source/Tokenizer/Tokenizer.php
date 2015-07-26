<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tokenizer;

use Spiral\Core\HippocampusInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Traits\LoggerTrait;
use Spiral\Files\FilesInterface;

class Tokenizer extends Singleton implements TokenizerInterface
{
    /**
     * Required traits.
     */
    use ConfigurableTrait, LoggerTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Token array constants.
     */
    const TYPE = 0;
    const CODE = 1;
    const LINE = 2;

    /**
     * To cache tokenizer class map.
     *
     * @invisible
     * @var HippocampusInterface
     */
    protected $runtime = null;

    /**
     * FileManager component to load files.
     *
     * @invisible
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * Rules and styles to highlight code using tokens. This rules used in Tokenizer->getCode()
     * method to colorize some php parts in exceptions. Rule specified by: "style" => array(tokens),
     * example:
     * Tokenizer->setHighlighting(array(
     *      'color: blue' => array(
     *          T_DNUMBER, T_LNUMBER
     *      )
     * ));
     *
     * @var array
     */
    protected $highlighting = [];

    /**
     * Rules and styles to highlight code using tokens. This rules used in Tokenizer->getCode() method
     * to colorize some php parts in exceptions. Rule specified by: "style" => array(tokens), example:
     *
     * Tokenizer->setHighlighting(array(
     *      'color: blue' => array(
     *          T_DNUMBER, T_LNUMBER
     *      )
     * ));
     *
     * @param array $highlighting
     * @return $this
     */
    public function setHighlightingStyles($highlighting)
    {
        $this->highlighting = $highlighting;

        return $this;
    }

    /**
     * Fetch specified amount of lines from provided filename and highlight them according to specified
     * highlighting rules (setHighlighting() method), target (middle) line number are specified in
     * "$targetLine" argument and will be used as reference to count lines before and after.
     *
     * Example:
     * line = 10, countLines = 10
     *
     * Output:
     * lines from 5 - 15 will be displayed, line 10 will be highlighted.
     *
     * @param string $filename   Filename to fetch and highlight lines from.
     * @param int    $targetLine Line number where code should be highlighted from.
     * @param int    $countLines Lines to fetch before and after code line specified in previous
     *                           argument.
     * @return string
     */
    public function highlightCode($filename, $targetLine, $countLines = 10)
    {
        $tokens = $this->fetchTokens($filename);

        $phpLines = "";
        foreach ($tokens as $position => $token)
        {
            $token[self::CODE] = htmlentities($token[self::CODE]);

            foreach ($this->highlighting as $style => $tokens)
            {
                //This way is slower, but more tolerant to memory usage
                if (in_array($token[self::TYPE], $tokens))
                {
                    if (strpos($token[self::CODE], "\n"))
                    {
                        $lines = [];
                        foreach (explode("\n", $token[self::CODE]) as $line)
                        {
                            $lines[] = '<span style="' . $style . '">'
                                . $line
                                . '</span>';
                        }

                        $token[self::CODE] = join("\n", $lines);
                    }
                    else
                    {
                        $token[self::CODE] = '<span style="' . $style . '">'
                            . $token[self::CODE]
                            . '</span>';
                    }
                    break;
                }
            }

            $phpLines .= $token[self::CODE];
        }

        $phpLines = explode("\n", str_replace("\r\n", "\n", $phpLines));
        $result = "";
        foreach ($phpLines as $line => $code)
        {
            $line++;
            if ($line >= $targetLine - $countLines && $line <= $targetLine + $countLines)
            {
                $result .= "<div class=\"" . ($line == $targetLine ? "highlighted" : "") . "\">"
                    . "<div class=\"number\">{$line}</div>"
                    . mb_convert_encoding($code, 'utf-8')
                    . "</div>";
            }
        }

        return $result;
    }
}