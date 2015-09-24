<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Database\Injections;

use Spiral\Database\Entities\QueryCompiler;

/**
 * Default implementation of ParameterInterface, provides ability to mock value or array of values
 * and automatically create valid query placeholder at moment of query compilation (? vs (?, ?, ?)).
 */
class Parameter implements ParameterInterface
{
    /**
     * Mocked value or array of values.
     *
     * @var mixed|array
     */
    private $value = null;

    /**
     * Parameter type.
     *
     * @var int|null
     */
    private $type = null;

    /**
     * @param mixed $value
     * @param int   $type
     */
    public function __construct($value, $type = \PDO::PARAM_STR)
    {
        $this->value = $value;
        $this->type = $type;
    }

    /**
     * Change mocked parameter value.
     *
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        if (is_array($this->value)) {
            $result = [];
            foreach ($this->value as $value) {
                $result[] = new self($value, $this->type);
            }

            return $result;
        }

        return $this->value;
    }

    /**
     * Parameter type.
     *
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     *
     * @param QueryCompiler $compiler
     */
    public function sqlStatement(QueryCompiler $compiler = null)
    {
        if (is_array($this->value)) {
            //Array were mocked
            return '(' . trim(str_repeat('?, ', count($this->value)), ', ') . ')';
        }

        return '?';
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->sqlStatement();
    }

    /**
     * @return object
     */
    public function __debugInfo()
    {
        return (object)[
            'statement' => $this->sqlStatement(),
            'value'     => $this->value
        ];
    }
}