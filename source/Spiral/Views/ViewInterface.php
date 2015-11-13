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
 * Provides ability to configure (implementation specific) and compile specified view.
 */
interface ViewInterface
{
    /**
     * Alter view parameters (should replace existed value).
     *
     * @param string $name
     * @param mixed  $value
     * @return self
     */
    public function set($name, $value);

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