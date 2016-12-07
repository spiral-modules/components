<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Stempler;

use Spiral\Stempler\Exceptions\LoaderExceptionInterface;

/**
 * View loader interface. Pretty simple class which is compatible with Twig loader.
 */
interface LoaderInterface
{
    /**
     * Get local (includable) filename for given view name, needed to highlight errors (if any).
     *
     * @param string $path
     *
     * @return string
     * @throws LoaderExceptionInterface
     */
    public function localFilename($path);

    /**
     * Get source for given name.
     *
     * @param string $path
     *
     * @return string
     * @throws LoaderExceptionInterface
     */
    public function getSource($path);
}