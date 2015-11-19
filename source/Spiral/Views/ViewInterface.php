<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Views;

use Spiral\Views\Exceptions\RenderException;

/**
 * Generic view interface.
 */
interface ViewInterface
{
    /**
     * Render view source using internal logic.
     *
     * @return string
     * @throws RenderException
     */
    public function render();

    /**
     * @return string
     */
    public function __toString();
}