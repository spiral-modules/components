<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database\Injections;

/**
 * Default implementation of ParameterInterface, provides ability to mock value or array of values
 * and automatically create valid query placeholder at moment of query compilation (? vs (?, ?, ?)).
 *
 * In a nearest future Parameter class will be used for every QueryBuilder parameter, it can also
 * be used to detect value type automatically.
 */
class Parameter implements ParameterInterface
{
    /**
     * Use in constructor to automatically detect parameter type.
     */
    const DETECT_TYPE = 900888;

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
    public function __construct($value, $type = self::DETECT_TYPE)
    {
        $this->value = $value;

        if ($type == self::DETECT_TYPE) {
            if (!is_array($value)) {
                $this->type = $this->detectType($value);
            } else {
                //Default and quick fallback
                $this->type = \PDO::PARAM_STR;
            }
        } else {
            $this->type = $type;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function withValue($value)
    {
        $parameter = clone $this;
        $parameter->value = $value;

        return $parameter;
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
     */
    public function isArray()
    {
        return is_array($this->value);
    }

    /**
     * {@inheritdoc}
     */
    public function flatten()
    {
        if (!is_array($this->value)) {
            return [clone $this];
        }

        $result = [];
        foreach ($this->value as $value) {
            if (!$value instanceof ParameterInterface) {
                //Self copy
                $value = $this->withValue($value);
            }

            $result = array_merge($result, $value->flatten());
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function sqlStatement()
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
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'statement' => $this->sqlStatement(),
            'value'     => $this->value,
        ];
    }

    /**
     * @param mixed $value
     * @return int
     */
    protected function detectType($value)
    {
        switch (gettype($value)) {
            case 'boolean':
                return \PDO::PARAM_BOOL;
            case 'integer':
                return \PDO::PARAM_INT;
            case 'NULL':
                return \PDO::PARAM_NULL;
            default:
                return \PDO::PARAM_STR;
        }
    }
}
