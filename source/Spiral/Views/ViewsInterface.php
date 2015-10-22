<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Views;

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
     * Get instance of view class associated with view path (path can include namespace).
     *
     * @param string $path  View path, CAN include separated namespace and view, or only view name
     *                      in this case default namespace should be used.
     * @param array  $data  View specific dataset transferred in form of array.
     * @return ViewInterface
     */
    public function get($path, array $data = []);

    /**
     * Compile desired view path into string. Just a shortcut.
     *
     * @see get()
     * @param string $path
     * @param array  $data View specific dataset transferred in form of array.
     * @return string
     * @throws ViewException
     */
    public function render($path, array $data = []);
}