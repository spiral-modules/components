<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Http\Input;

use Spiral\Http\Exceptions\InputException;

/**
 * Generic data accessor, used to read properties of active request.
 */
class InputBag implements \Countable, \IteratorAggregate, \ArrayAccess
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->data;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Get property or return default value.
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        if (!$this->has($name))
        {
            return $default;
        }

        return $this->data[$name];
    }

    /**
     * Fetch only specified keys from property values. Missed values can be filled with defined filler.
     *
     * @param array $keys
     * @param bool  $fill Fill missing key with filler value.
     * @param mixed $filler
     * @return array
     */
    public function fetch(array $keys, $fill = false, $filler = null)
    {
        $result = array_intersect_key($this->data, array_flip($keys));;

        if (!$fill)
        {
            return $result;
        }

        return $result + array_fill_keys($keys, $filler);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InputException
     */
    public function offsetSet($offset, $value)
    {
        throw new InputException("InputBag does not allow parameter altering.");
    }

    /**
     * {@inheritdoc}
     *
     * @throws InputException
     */
    public function offsetUnset($offset)
    {
        throw new InputException("InputBag does not allow parameter altering.");
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return $this->all();
    }
}