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
 * Every implemented class MUST declare method named "saturate". Container/user MUST inject requested
 * dependencies into this method, which usually puts a label on classes like that - "use container".
 *
 * In order to fully configure class such method MUST be called. Spiral controller will be do it
 * automatically.
 */
interface SaturableInterlace
{
    /**
     * Method to be injected.
     */
    const DEPENDENT_METHOD = 'saturate';
}