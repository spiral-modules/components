<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core\Container;

/**
 * Every implemented class MUST declare method named "inject". Container/user should inject requested
 * dependencies into this method.
 *
 * In order to fully configure class such method MUST be called.
 */
interface DependedInterface
{
    /**
     * Method to be injected.
     */
    const DEPENDENT_METHOD = 'inject';
}