<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

interface CoreInterface
{
    /**
     * Call controller method by fully specified or short controller name, action and addition
     * options such as default controllers namespace, default name and postfix.
     *
     * Can be used for controller-less applications.
     *
     * @param string $controller Controller name, or class, or name with namespace prefix.
     * @param string $action     Controller action, empty by default (controller will use default action).
     * @param array  $parameters Additional methods parameters.
     * @return mixed
     * @throws ExceptionInterface
     */
    public function callAction($controller, $action = '', array $parameters = []);
}