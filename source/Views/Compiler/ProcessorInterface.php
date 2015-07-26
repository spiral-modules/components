<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views\Compiler;

use Spiral\Views\ViewManagerInterface;

interface ProcessorInterface
{
    /**
     * New processors instance with options specified in view config.
     *
     * @param ViewManagerInterface $viewFacade
     * @param Compiler            $compiler SpiralCompiler instance.
     * @param array               $options
     */
    public function __construct(ViewManagerInterface $viewFacade, Compiler $compiler, array $options);

    /**
     * Performs view code pre-processing. LayeredCompiler will provide view source into processors,
     * processors can perform any source manipulations using this code expect final rendering.
     *
     * @param string $source View source (code).
     * @return string
     */
    public function process($source);
}