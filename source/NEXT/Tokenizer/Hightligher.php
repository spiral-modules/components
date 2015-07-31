<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tokenizer;

class Hightligher
{
    /**
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

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
    protected $style = [
        'templates' => [
            'token'       => '<span style="{style}">{line}</span>',
            'line'        => '<div><div class="number">{number}</div>{content}</div>',
            'highlighted' => '<div class="highlighted"><div class="number">{number}</div><div>{content}</div></div>'
        ]
    ];

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
    public function highlight($filename, $targetLine, $countLines = 10)
    {
        $tokens = $this->tokenizer->fetchTokens($filename);

        $phpLines = "";
        foreach ($tokens as $position => $token)
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
                                $this->style['templates']['token'],
                                compact('style', 'line')
                            );
                        }

                        $token[TokenizerInterface::CODE] = join("\n", $lines);
                    }
                    else
                    {
                        $token[TokenizerInterface::CODE] = \Spiral\interpolate(
                            $this->style['templates']['token'],
                            [
                                'style' => $style,
                                'line'  => $token[TokenizerInterface::CODE]
                            ]
                        );
                    }
                    break;
                }
            }

            $phpLines .= $token[TokenizerInterface::CODE];
        }

        $phpLines = explode("\n", str_replace("\r\n", "\n", $phpLines));

        $result = "";
        foreach ($phpLines as $number => $code)
        {
            $number++;
            if ($number >= $targetLine - $countLines && $number <= $targetLine + $countLines)
            {
                $template = $this->style['templates']['line'];

                if ($number == $targetLine)
                {
                    $template = $this->style['templates']['highlighted'];
                }

                $result .= \Spiral\interpolate($template, [
                    'number'  => $number,
                    'content' => mb_convert_encoding($code, 'utf-8')
                ]);
            }
        }

        return $result;
    }
}