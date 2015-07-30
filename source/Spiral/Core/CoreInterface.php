<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;
use Spiral\Core\Exceptions\ControllerException;

/**
 * He made 9 rings... i mean we need one general class.
 */
interface CoreInterface extends ContainerInterface, ConfiguratorInterface, HippocampusInterface
{
    /**
     * Request specific action result from Core. Due in 99% every action will need parent controller,
     * we can request it too.
     *
     * @param string $controller Controller class.
     * @param string $action     Controller action, empty by default (controller will use default action).
     * @param array  $parameters Action parameters (if any).
     * @return mixed
     * @throws ControllerException
     * @throws \Exception
     */
    public function callAction($controller, $action = '', array $parameters = []);
}