<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Commands\Traits;

/**
 * Provides ability to carry context.
 */
trait ContextTrait
{
    /**
     * @var array
     */
    private $context = [];

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function addContext(string $name, $value)
    {
        $this->context[$name] = $value;
    }
}