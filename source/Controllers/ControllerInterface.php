<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Controllers;

use Spiral\Core\ContainerInterface;

interface ControllerInterface
{
    /**
     * Performing controller action. This method should either return response object or string, or
     * any other type supported by specified dispatcher. This method can be overwritten in child
     * controller to force some specific Response or modify output from every controller action.
     *
     * @param ContainerInterface $container
     * @param string             $action     Method name.
     * @param array              $parameters Set of parameters to populate controller method.
     * @return mixed
     * @throws ControllerException
     */
    public function callAction(ContainerInterface $container, $action = '', array $parameters = []);
}