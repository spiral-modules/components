<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Database\Entities\Schemas\Traits;

trait DeclaredTrait
{
    /**
     * Declaration flag used to create full table diff.
     *
     * @var bool
     */
    private $declared = false;

    /**
     * Mark schema entity as declared, it will be kept in final diff.
     */
    public function markDeclared()
    {
        $this->declared = true;
    }

    /**
     * @return bool
     */
    public function isDeclared()
    {
        return $this->declared;
    }
}