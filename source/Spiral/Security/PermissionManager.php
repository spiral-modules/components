<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Security;

use Spiral\Core\Component;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Security\Exceptions\PermissionException;
use Spiral\Security\Exceptions\RoleException;
use Spiral\Support\Patternizer;

/**
 * Default implementation of associations repository and manager. Provides ability to set
 * permissions in bulk using * syntax.
 *
 * Attention, this class is serializable and can be cached in memory.
 *
 * Example:
 * $associations->associate('admin', '*');
 * $associations->associate('editor', 'posts.*');
 */
class PermissionManager extends Component implements PermissionsInterface, SingletonInterface
{
    /**
     * Roles associated with their permissions.
     *
     * @var array
     */
    private $associations = [];

    /**
     * Roles deassociated with their permissions.
     *
     * @var array
     */
    private $deassociations = [];

    /**
     * @var RulesInterface
     */
    private $rules = null;

    /**
     * @var Patternizer
     */
    private $patternizer = null;

    /**
     * @param RulesInterface   $rules
     * @param Patternizer|null $patternizer
     */
    public function __construct(RulesInterface $rules, Patternizer $patternizer)
    {
        $this->rules = $rules;
        $this->patternizer = $patternizer;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRole(string $role): bool
    {
        return array_key_exists($role, $this->associations);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function addRole(string $role): PermissionManager
    {
        if ($this->hasRole($role)) {
            throw new RoleException("Role '{$role}' already exists.");
        }

        $this->associations[$role] = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function removeRole(string $role): PermissionManager
    {
        if (!$this->hasRole($role)) {
            throw new RoleException("Undefined role '{$role}'.");
        }

        unset($this->associations[$role]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles(): array
    {
        return array_keys($this->associations);
    }

    /**
     * {@inheritdoc}
     */
    public function getRule(string $role, string $permission)
    {
        if (!$this->hasRole($role)) {
            throw new RoleException("Undefined role '{$role}'");
        }

        $rule = $this->findRule($role, $permission);
        if ($rule === GuardInterface::ALLOW || $rule === GuardInterface::UNDEFINED) {
            return $rule;
        }

        //Behaviour points to rule
        return $this->rules->get($rule);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function associate(
        string $role,
        string $permission,
        $rule = GuardInterface::ALLOW
    ): PermissionManager {
        if (!$this->hasRole($role)) {
            throw new RoleException("Undefined role '{$role}'");
        }

        if ($rule !== GuardInterface::ALLOW) {
            if (!$this->rules->has($rule)) {
                throw new PermissionException("Invalid permission rule '{$rule}'");
            }
        }

        $this->associations[$role][$permission] = $rule;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function deassociate(string $role, string $permission): PermissionManager
    {
        if (!$this->hasRole($role)) {
            throw new RoleException("Undefined role '{$role}'");
        }

        if (!isset($this->associations[$role][$permission])) {
            $this->deassociations[$role][] = $permission;
        } else {
            unset($this->associations[$role][$permission]);
        }

        return $this;
    }

    /**
     * @param string $role
     * @param string $permission
     *
     * @return bool|string
     */
    private function findRule(string $role, string $permission)
    {
        if (isset($this->deassociations[$role]) && in_array($permission,
                $this->deassociations[$role])
        ) {
            return GuardInterface::UNDEFINED;
        }

        if (isset($this->associations[$role][$permission])) {
            //O(1) check
            return $this->associations[$role][$permission];
        } else {
            //Checking using star syntax
            foreach ($this->associations[$role] as $pattern => $behaviour) {
                if ($this->patternizer->matches($permission, $pattern)) {
                    return $behaviour;
                }
            }
        }

        return GuardInterface::UNDEFINED;
    }
}
