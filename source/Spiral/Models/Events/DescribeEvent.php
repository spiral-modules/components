<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Models\Events;

use Spiral\Models\Reflections\ReflectionEntity;
use Symfony\Component\EventDispatcher\Event;

/**
 * Raised while entity analysis to allow traits and other listeners apply changed to entity schema.
 */
class DescribeEvent extends Event
{
    /**
     * @var ReflectionEntity
     */
    private $reflection = null;

    /**
     * @var string
     */
    private $property = '';

    /**
     * @var mixed
     */
    private $value = null;

    /**
     * @param ReflectionEntity $reflection
     * @param string           $property
     * @param mixed            $value
     */
    public function __construct(ReflectionEntity $reflection, $property, $value)
    {
        $this->reflection = $reflection;
        $this->property = $property;
        $this->value = $value;
    }

    /**
     * @return ReflectionEntity
     */
    public function reflection()
    {
        return $this->reflection;
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @param string $property
     */
    public function setProperty($property)
    {
        $this->property = $property;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }
}