<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

use Spiral\Views\Exceptions\RenderException;

/**
 * Provides ability to configure and compile specified view.
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