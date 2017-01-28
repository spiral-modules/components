<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\Stempler;

/**
 * Describes template/view location, source and name.
 */
interface SourceContextInterface
{
    /**
     * Filename associated with view template.
     *
     * @return string
     */
    public function getFilename(): string;

    /**
     * @return string
     */
    public function getSource(): string;
}