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
use Spiral\Views\Exceptions\CompilerException;

/**
 * Compilers used to create view cache to speed up rendering process.
 */
interface CompilerInterface
{
    /**
     * @param ViewsInterface     $views
     * @param ContainerInterface $container
     * @param array              $config    Compiler configuration.
     * @param string             $namespace View namespace.
     * @param string             $view      View name.
     */
    public function __construct(
        ViewsInterface $views,
        ContainerInterface $container,
        array $config,
        $namespace,
        $view
    );

    /**
     * @return string
     * @throws CompilerException
     * @throws \Exception
     */
    public function compile();
}