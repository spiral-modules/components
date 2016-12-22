<?php
/**
 * components
 *
 * @author    Wolfy-J
 */

namespace Spiral\ORM;

interface ORMInterface
{
    public function schema();

    public function entityCache();
}