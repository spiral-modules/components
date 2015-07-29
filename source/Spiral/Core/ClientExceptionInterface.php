<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

interface ClientExceptionInterface
{
    /**
     * Exception with this interface implemented has to be handled separately, treat exceptions like
     * that as "soft application error" (not found, bad request and etc). Usually spiral core will
     * forward exception like that to dispatcher directly, without any logging or snapshots.
     */
}