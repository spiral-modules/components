<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

use Spiral\Core\Singleton;

/**
 * One of the oldest spiral parts, used to dump variables content in user friendly way.
 */
class Dumper extends Singleton
{
    /**
     * Declaring to IoC that class is singleton.
     */
    const SINGLETON = self::class;

    /**
     * Options for dump() function to specify output.
     */
    const OUTPUT_ECHO     = 0;
    const OUTPUT_RETURN   = 1;
    const OUTPUT_LOG      = 2;
    const OUTPUT_LOG_NICE = 3;

    /**
     * @var array
     */
    private $options = [
        'maxLevel'  => 12,
        'container' => '<pre style="background-color: white; font-family: monospace;">{dump}</pre>',
        'element'   => "<span style='color: {style};'>{element}</span>",
        'indent'    => '&middot;    ',
        'styles'    => [
            'common'           => 'black',
            'name'             => 'black',
            'indent'           => 'gray',
            'indent-('         => 'black',
            'indent-)'         => 'black',
            'recursion'        => '#ff9900',
            'value-string'     => 'green',
            'value-integer'    => 'red',
            'value-double'     => 'red',
            'value-boolean'    => 'purple; font-weight: bold',
            'type'             => '#666',
            'type-object'      => '#333',
            'type-array'       => '#333',
            'type-null'        => '#666; font-weight: bold',
            'type-resource'    => '#666; font-weight: bold',
            'access'           => '#666',
            'access-public'    => '#8dc17d',
            'access-private'   => '#c18c7d',
            'access-protected' => '#7d95c1'
        ]
    ];

    /**
     * @var Debugger
     */
    protected $debugger = null;

    /**
     * @param Debugger $debugger
     */
    public function __construct(Debugger $debugger)
    {
        $this->debugger = $debugger;
    }

    /**
     * Update dumping styles with new values.
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * Dump value content into specified output.
     **
     *
     * @param mixed $value
     * @param int   $output
     * @return null|string
     */
    public function dump($value, $output = self::OUTPUT_ECHO)
    {
        if (php_sapi_name() === 'cli' && $output != self::OUTPUT_LOG)
        {
            print_r($value);
            if (is_string($value))
            {
                echo "\n";
            }

            return null;
        }

        $result = \Spiral\interpolate($this->options['container'], [
            'dump' => $this->dumpVariable($value, '', 0)
        ]);

        switch ($output)
        {
            case self::OUTPUT_ECHO:
                echo $result;
                break;

            case self::OUTPUT_RETURN:
                return $result;

            case self::OUTPUT_LOG:
                $this->debugger->logger()->debug(
                    print_r($value, true)
                );
                break;

            case self::OUTPUT_LOG_NICE:
                $this->debugger->logger()->debug(
                    $this->dump($value, self::OUTPUT_RETURN)
                );
                break;
        }

        return null;
    }

    /**
     * Stylize content using pre-defined style.
     *
     * @param string $element
     * @param string $type
     * @param string $subType
     * @return string
     */
    public function style($element, $type, $subType = '')
    {
        if (isset($this->options['styles'][$type . '-' . $subType]))
        {
            $style = $this->options['styles'][$type . '-' . $subType];
        }
        elseif (isset($this->options['styles'][$type]))
        {
            $style = $this->options['styles'][$type];
        }
        else
        {
            $style = $this->options['styles']['common'];
        }

        if (!empty($style))
        {
            $element = \Spiral\interpolate($this->options['element'], compact('style', 'element'));
        }

        return $element;
    }

    /**
     * Variable dumper. This is the oldest spiral function, it was originally written in 2007. :)
     *
     * @param mixed  $value
     * @param string $name     Variable name, internal.
     * @param int    $level    Dumping level, internal.
     * @param bool   $hideType Hide array/object header, internal.
     * @return string
     */
    private function dumpVariable($value, $name = '', $level = 0, $hideType = false)
    {
        $result = $indent = $this->indent($level);
        if (!$hideType && $name)
        {
            $result .= $this->style($name, "name") . $this->style(" = ", "indent", "equal");
        }

        if ($level > $this->options['maxLevel'])
        {
            return $indent . $this->style('-possible recursion-', 'recursion') . "\n";
        }

        $type = strtolower(gettype($value));

        if ($type == 'array')
        {
            return $result . $this->dumpArray($value, $level, $hideType);
        }

        if ($type == 'object')
        {
            return $result . $this->dumpObject($value, $level, $hideType);
        }

        if ($type == 'resource')
        {
            $result .= $this->style(
                    get_resource_type($value) . " resource ",
                    "type",
                    "resource"
                ) . "\n";

            return $result;
        }

        $result .= $this->style($type . "(" . strlen($value) . ")", "type", $type);

        $value = null;
        switch ($type)
        {
            case "string":
                $value = htmlspecialchars($value);
                break;

            case "boolean":
                $value = ($value ? "true" : "false");
                break;

            default:
                if ($value !== null)
                {
                    //Not showing null value, type is enough
                    $value = var_export($value, true);
                }
        }

        return $result . " " . $this->style($value, "value", $type) . "\n";
    }

    /**
     * @param array $array
     * @param int   $level
     * @param bool  $hideType
     * @return string
     */
    private function dumpArray($array, $level, $hideType)
    {
        $result = '';
        $indent = $this->indent($level);
        if (!$hideType)
        {
            $count = count($array);
            $result .= $this->style("array({$count})", "type", "array")
                . "\n" . $indent . $this->style("(", "indent", "(") . "\n";
        }

        foreach ($array as $name => $value)
        {
            if (!is_numeric($name))
            {
                if (is_string($name))
                {
                    $name = htmlspecialchars($name);
                }
                $name = "'" . $name . "'";
            }

            $result .= $this->dumpVariable($value, "[{$name}]", $level + 1);
        }

        if (!$hideType)
        {
            $result .= $indent . $this->style(")", "indent", ")") . "\n";
        }

        return $result;
    }

    /**
     * @param object $value
     * @param int    $level
     * @param bool   $hideType
     * @param string $class
     * @return string
     */
    private function dumpObject($value, $level, $hideType, $class = '')
    {
        $result = '';
        $indent = $this->indent($level);
        if (!$hideType)
        {
            $type = ($class ?: get_class($value)) . " object ";

            $result .= $this->style($type, "type", "object") .
                "\n" . $indent . $this->style("(", "indent", "(") . "\n";
        }

        if (method_exists($value, '__debugInfo'))
        {
            $debugInfo = $value->__debugInfo();

            if (is_object($debugInfo))
            {
                return $this->dumpObject($debugInfo, $level, false, get_class($value));
            }

            $result .= $this->dumpVariable(
                $debugInfo,
                '',
                $level + (is_scalar($value)),
                true
            );

            return $result . $indent . $this->style(")", "parentheses") . "\n";
        }

        $refection = new \ReflectionObject($value);
        foreach ($refection->getProperties() as $property)
        {
            if ($property->isStatic())
            {
                continue;
            }

            //Memory loop while reading doc comment for stdClass variables?
            if (!($value instanceof \stdClass) && strpos($property->getDocComment(), '@invisible'))
            {
                continue;
            }

            $access = "public";
            if ($property->isPrivate())
            {
                $access = "private";
            }
            elseif ($property->isProtected())
            {
                $access = "protected";
            }
            $property->setAccessible(true);

            if ($value instanceof \stdClass)
            {
                $access = 'dynamic';
            }

            $value = $property->getValue($value);
            $result .= $this->dumpVariable(
                $value,
                $property->getName() . $this->style(":" . $access, "access", $access),
                $level + 1
            );
        }

        return $result . $indent . $this->style(")", "parentheses") . "\n";
    }

    /**
     * Set indent to line based on it's level, internal.
     *
     * @param int $level
     * @return string
     */
    private function indent($level)
    {
        if (!$level)
        {
            return '';
        }

        return $this->style(str_repeat($this->options["indent"], $level), "indent");
    }
}