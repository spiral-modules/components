<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

/**
 * All sync commands must be aware of parent.
 */
interface SyncCommandInterface extends CommandInterface
{
    /**
     * Returns associated primary key, can be NULL. Promised on execution!
     *
     * @return mixed|null
     */
    public function primaryKey();
}