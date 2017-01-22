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
 *
 * @todo possible update interface like new Twig model which incaplutes source and filename into one
 * @todo object
 */
interface LoaderInterface
{
    /**
     * Get local (includable) filename for given view name, needed to highlight errors (if any).
     * Attention, this is filename which is going to be needed to correctly describe exceptions
     * inside token engine.
     *
     * @param string $path
     *
     * @return string
     *
     * @throws LoaderExceptionInterface
     */
    public function localFilename(string $path): string;

    /**
     * Get source for given name.
     *
     * @param string $path
     *
     * @return string
     *
     * @throws LoaderExceptionInterface
     */
    public function getSource($path);
}