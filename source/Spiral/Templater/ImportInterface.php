<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Templater;

/**
 * ImportInterface used by Templater to define what tags should be treated as includes and how to
 * resolve their view or namespace.
 */
interface ImportInterface
{
    /**
     * New instance of importer.
     *
     * @param Templater $templater
     * @param array     $token Html token.
     */
    public function __construct(Templater $templater, array $token);

    /**
     * Check if element (tag) has to be imported.
     *
     * @param string $element Element name.
     * @param array  $token   Context token.
     * @return bool
     */
    public function isImported($element, array $token);

    /**
     * Get imported element location. Must be supported by Templater implementation.
     *
     * @param string $element
     * @param array  $token Context token.
     * @return mixed
     */
    public function getLocation($element, array $token);
}