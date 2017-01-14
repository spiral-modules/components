<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

/**
 * Contextual commands used to carry FK and PK values across commands pipeline, other commands are
 * able to mount it's values into parent context or read from it.
 */
interface ContextualCommandInterface extends CommandInterface
{
    /**
     * Get current command context.
     *
     * @return array
     */
    public function getContext(): array;

    /**
     * Add context value, usually FK.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function addContext(string $name, $value);
}