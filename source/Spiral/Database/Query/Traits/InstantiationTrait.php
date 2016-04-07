<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Query\Traits;

use Doctrine\Instantiator\InstantiatorInterface;
use Spiral\Database\Exceptions\ResultException;
use Spiral\Models\AbstractEntity;

trait InstantiationTrait
{
    /**
     * @var InstantiatorInterface|null
     */
    private $instantiator = null;

    /**
     * Set result specific instantiation manager, Doctrine\Instantiator to be used by default.
     *
     * @see Doctrine\Instantiator
     * @param InstantiatorInterface $instantiator
     * @return self
     */
    public function setInstantiator(InstantiatorInterface $instantiator)
    {
        $this->instantiator = $instantiator;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Note: values are selected before instantiation attempt
     */
    public function fetchInstance($class)
    {
        $values = $this->fetch();

        if ($values === false || !is_array($values)) {
            return null;
        }

        if (class_exists(AbstractEntity::class) && is_a($class, AbstractEntity::class, true)) {
            //Spiral Models shortcut
            return new $class($values);
        }

        try {
            $instance = $this->instantiator->instantiate($class);
        } catch (\Doctrine\Instantiator\Exception\ExceptionInterface $e) {
            throw new ResultException($e->getMessage(), $e->getCode(), $e);
        }

        //@see http://stackoverflow.com/questions/9586713/how-do-i-fill-an-object-in-php-from-an-array
        $has = get_object_vars($instance);
        foreach ($has as $field => $oldValue) {
            $instance->{$field} = isset($values[$field]) ? $values[$field] : null;
        }

        return $instance;
    }

    /**
     * All results as instances.
     *
     * @param string $class
     *
     * @return array
     */
    public function fetchInstances($class)
    {
        $result = [];
        while (!empty($instance = $this->fetchInstance($class))) {
            //Generator, how to combine with interface?
            $result[] = $instance;
        }

        return $result;
    }

    /**
     * Fetch one result row as array or return false.
     *
     * @return array|bool
     */
    abstract public function fetch();
}