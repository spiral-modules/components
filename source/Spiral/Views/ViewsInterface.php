<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;
use Spiral\Views\Exceptions\CompilerException;
use Spiral\Views\Exceptions\ViewException;

/**
 * Provides access to views functionality.
 */
interface ViewsInterface
{
    /**
     * In some cases namespace is not specified, this namespace will be used instead.
     */
    const DEFAULT_NAMESPACE = 'default';

    /**
     * View name can be specified with namespace included, this separator has to be used.
     */
    const NS_SEPARATOR = ':';

    /**
     * Convert namespace and view name into valid file location (in terms of associated
     * FilesInterface).
     *
     * @param string $namespace
     * @param string $view
     * @return string
     * @throws ViewException
     */
    public function getFilename($namespace, $view);

    /**
     * Get string source of view by it's namespace and name.
     *
     * @param string $namespace
     * @param string $view
     * @return string
     * @throws ViewException
     */
    public function getSource($namespace, $view);

    /**
     * Pre-compile desired view.
     *
     * @param string $namespace
     * @param string $view
     * @throws ViewException
     * @throws CompilerException
     * @throws \Exception
     */
    public function compile($namespace, $view);

    /**
     * Get instance of view class associated with view path (path can include namespace).
     *
     * @param string $path View path, CAN include separated namespace and view, or only view name
     *                     in this case default namespace should be used.
     * @param array  $data Data to be interpolated into view, usually name associated with value
     *                     or data source.
     * @return ViewInterface
     * @throws ViewException
     * @throws CompilerException
     * @throws \Exception
     */
    public function get($path, array $data = []);

    /**
     * Compile desired view path into string. Just a shortcut.
     *
     * @see get()
     * @param string $path
     * @param array  $data
     * @return string
     * @throws ViewException
     * @throws CompilerException
     * @throws \Exception
     */
    public function render($path, array $data = []);
}