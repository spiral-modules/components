<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views\Compiler;

use Spiral\Views\Compiler;
use Spiral\Views\Exceptions\CompilerException;
use Spiral\Views\ViewsInterface;

/**
 * View processors used to prepare/compile view source with specific set of operations.
 */
interface ProcessorInterface
{
    /**
     * @param ViewsInterface $views
     * @param Compiler       $compiler
     * @param array          $options Processor specific options.
     */
    public function __construct(ViewsInterface $views, Compiler $compiler, array $options);

    /**
     * Compile view source.
     *
     * @param string $source View source (code).
     * @return string
     * @throws CompilerException
     * @throws \ErrorException
     */
    public function process($source);
}