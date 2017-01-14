<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

interface SyncCommandInterface extends CommandInterface
{
    /**
     * Returns associated primary key, can be NULL. Promised on execution!
     *
     * @return mixed|null
     */
    public function primaryKey();
}