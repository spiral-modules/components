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
     * View instance binded to specified view file (file has to be already pre-processed).
     *
     * @param ViewsInterface $views
     * @param string         $namespace
     * @param string         $view
     * @param array          $data
     */
    public function __construct(ViewsInterface $views, $namespace, $view, array $data = []);

    /**
     * Alter view parameters (should replace existed value).
     *
     * @param string $name
     * @param mixed  $value
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