<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

use Spiral\Core\ContainerInterface;

interface CompilerInterface
{
    /**
     * Instance of view compiler. Compilers used to pre-process view files for faster rendering in
     * runtime environment.
     *
     * @param ViewManagerInterface $viewFacade
     * @param ContainerInterface  $container
     * @param array               $config    Compiler configuration.
     * @param string              $source    Non-compiled source.
     * @param string              $namespace View namespace.
     * @param string              $view      View name.
     */
    public function __construct(
        ViewManagerInterface $viewFacade,
        ContainerInterface $container,
        array $config,
        $source,
        $namespace,
        $view
    );

    /**
     * Compile original view file to plain php code.
     *
     * @return string
     */
    public function compile();
}