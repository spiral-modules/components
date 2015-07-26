<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

interface ViewManagerInterface
{
    /**
     * Namespace separator in views.
     */
    const NS_SEPARATOR = ':';

    /**
     * Searching for view in namespaces. Namespace specifies set of view files joined by module or
     * application folder or etc. View name is relative file name (starting with namespace folder).
     *
     * @param string $namespace View namespace.
     * @param string $view      View filename, without .php included.
     * @return string
     * @throws ViewException
     */
    public function viewFilename($namespace, $view);

    /**
     * Get source of non compiled view file.
     *
     * @param string $namespace
     * @param string $view
     * @return string
     */
    public function getSource($namespace, $view);

    /**
     * Get instance of View class binded to specified view filename. View file will can be selected
     * from specified namespace, or default namespace if not specified.
     *
     * Every view file will be pro-processed using view processors (also defined in view config) before
     * rendering, result of pre-processing will be stored in names cache file to speed-up future
     * renderings.
     *
     * Example or view names:
     * home                     - render home view from default namespace
     * namespace:home           - render home view from specified namespace
     *
     * @param string $view View name without .php extension, can include namespace prefix separated
     *                     by : symbol.
     * @param array  $data Array or view data, will be exported as local view variables, not available
     *                     in view processors.
     * @return ViewInterface
     */
    public function get($view, array $data = []);

    /**
     * Perform view file rendering. View file will can be selected from specified namespace, or
     * default namespace if not specified.
     *
     * View data has to be associated array and will be exported using extract() function and set of
     * local view variables, here variable name will be identical to array key.
     *
     * Every view file will be pro-processed using view processors (also defined in view config) before
     * rendering, result of pre-processing will be stored in names cache file to speed-up future
     * renderings.
     *
     * Example or view names:
     * home                     - render home view from default namespace
     * namespace:home           - render home view from specified namespace
     *
     * @param string $view View name without .php extension, can include namespace prefix separated
     *                     by : symbol.
     * @param array  $data Array or view data, will be exported as local view variables, not available
     *                     in view processors.
     * @return string
     */
    public function render($view, array $data = []);
}