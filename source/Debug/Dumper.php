<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Debug;

use Spiral\Debug\Debugger;
use Spiral\Core\Singleton;

class Dumper extends Singleton
{
    /**
     * Declaring to IoC that class is singleton.
     */
    const SINGLETON = self::class;

    /**
     * Options for Debugger::dump() function to specify dump destination, default options are: return,
     * echo dump into log container "dumps".
     */
    const DUMP_ECHO     = 0;
    const DUMP_RETURN   = 1;
    const DUMP_LOG      = 2;
    const DUMP_LOG_NICE = 3;

    /**
     * Debugger instance
     *
     * @var Debugger
     */
    protected $debugger = null;

    /**
     * Dump styles used to colorize and format variable dump performed using Debugger::dump or dump()
     * functions.
     *
     * @var array
     */
    private $options = [
        'maxLevel'  => 12,
        'container' => 'background-color: white; font-family: monospace;',
        'element'   => "<span style='color: {style};'>{value}</span>",
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
     * Dumper instance.
     *
     * @param Debugger $debugger
     */
    public function __construct(Debugger $debugger)
    {
        $this->debugger = $debugger;
    }

    /**
     * Update dumping styles.
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * Helper function to dump variable into specified destination (output, log or return) using
     * pre-defined dumping styles. This method is fairly slow and should not be used in productions
     * environment. Only use it during development, error handling and other not high loaded
     * application parts.
     *
     * Method has alias with short function dump() which is always defined.
     *
     * @param mixed $value  Value to be dumped.
     * @param int   $output Output method, can print, return or log value dump.
     * @return null|string
     */
    public function dump($value, $output = self::DUMP_ECHO)
    {
        if (PHP_SAPI === 'cli' && $output != self::DUMP_LOG)
        {
            var_dump($value);

            return null;
        }

        $result = "<pre style='" . $this->options['container'] . "'>"
            . $this->dumpVariable($value, '', 0)
            . "</pre>";

        switch ($output)
        {
            case self::DUMP_ECHO:
                echo $result;
                break;

            case self::DUMP_RETURN:
                return $result;

            case self::DUMP_LOG:
                $this->debugger->logger()->debug(print_r($value, true));
                break;

            case self::DUMP_LOG_NICE:
                $this->debugger->logger()->debug($this->dump($value, self::DUMP_RETURN));
                break;
        }

        return null;
    }

    /**
     * Variable dumping method, can be called recursively, maximum nested level specified in
     * Debugger::$dumping['maxLevel']. Dumper values, braces and other parts will be styled using
     * rules defined in Debugger::$dumping. Styles can be redefined any moment. You can hide class
     * fields from dumping by using @invisible doc comment option.
     *
     * This is the oldest spiral function, it was originally written in 2007. :)
     *
     * @param mixed  $variable Value to be dumped.
     * @param string $name     Current variable name (empty if no name).
     * @param int    $level
     * @param bool   $hideType True to hide object/array type declaration, used by __debugInfo.
     * @return string
     */
    private function dumpVariable($variable, $name = '', $level = 0, $hideType = false)
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

        $type = strtolower(gettype($variable));

        if ($type == 'array')
        {
            return $result . $this->dumpArray($variable, $level, $hideType);
        }

        if ($type == 'object')
        {
            return $result . $this->dumpObject($variable, $level, $hideType);
        }

        if ($type == 'resource')
        {
            $result .= $this->style(
                    get_resource_type($variable) . " resource ",
                    "type",
                    "resource"
                ) . "\n";

            return $result;
        }

        $result .= $this->style($type . "(" . strlen($variable) . ")", "type", $type);

        $value = null;
        switch ($type)
        {
            case "string":
                $value = htmlspecialchars($variable);
                break;

            case "boolean":
                $value = ($variable ? "true" : "false");
                break;

            default:
                if ($variable !== null)
                {
                    //Not showing null value, type is enough
                    $value = var_export($variable, true);
                }
        }

        return $result . " " . $this->style($value, "value", $type) . "\n";
    }

    /**
     * Helper method used to arrays.
     *
     * @param mixed $variable Value to be dumped.
     * @param int   $level
     * @param bool  $hideType True to hide object/array type declaration, used by __debugInfo.
     * @return string
     */
    private function dumpArray($variable, $level, $hideType)
    {
        $result = '';
        $indent = $this->indent($level);
        if (!$hideType)
        {
            $count = count($variable);
            $result .= $this->style("array({$count})", "type", "array")
                . "\n" . $indent . $this->style("(", "indent", "(") . "\n";
        }

        foreach ($variable as $name => $value)
        {
            if (!is_numeric($name))
            {
                if (is_string($name))
                {
                    $name = htmlspecialchars($name);
                }
                $name = "'" . $name . "'";
            }

            $result .= $this->dumpVariable(
                $value,
                "[{$name}]",
                $level + 1
            );
        }

        if (!$hideType)
        {
            $result .= $indent . $this->style(")", "indent", ")") . "\n";
        }

        return $result;
    }

    /**
     * Helper method used to dump objects.
     *
     * @param mixed  $variable Value to be dumped.
     * @param int    $level
     * @param bool   $hideType True to hide object/array type declaration, used by __debugInfo.
     * @param string $class    Class name to be used.
     * @return string
     */
    private function dumpObject($variable, $level, $hideType, $class = '')
    {
        $result = '';
        $indent = $this->indent($level);
        if (!$hideType)
        {
            $type = ($class ?: get_class($variable)) . " object ";

            $result .= $this->style($type, "type", "object") .
                "\n" . $indent . $this->style("(", "indent", "(") . "\n";
        }

        if (method_exists($variable, '__debugInfo'))
        {
            $debugInfo = $variable->__debugInfo();

            if (is_object($debugInfo))
            {
                return $this->dumpObject($debugInfo, $level, false, get_class($variable));
            }

            $result .= $this->dumpVariable(
                $debugInfo,
                '',
                $level + (is_scalar($variable)),
                true
            );

            return $result . $indent . $this->style(")", "parentheses") . "\n";
        }

        $refection = new \ReflectionObject($variable);
        foreach ($refection->getProperties() as $property)
        {
            if ($property->isStatic())
            {
                continue;
            }

            //Memory loop while reading doc comment for stdClass variables?
            if (!($variable instanceof \stdClass) && strpos($property->getDocComment(), '@invisible'))
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

            if ($variable instanceof \stdClass)
            {
                $access = 'dynamic';
            }

            $value = $property->getValue($variable);
            $result .= $this->dumpVariable(
                $value,
                $property->getName() . $this->style(":" . $access, "access", $access),
                $level + 1
            );
        }

        return $result . $indent . $this->style(")", "parentheses") . "\n";
    }

    /**
     * Indent based on variable level.
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

    /**
     * Stylize content using pre-defined style. Dump styles defined in Debugger::$dumping and can be
     * redefined at any moment.
     *
     * @param string $element Content to apply style to.
     * @param string $type    Content type (value, indent, name and etc)
     * @param string $subType Content sub type (int, string and etc...)
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
}