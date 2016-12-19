<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Security;

use Spiral\Core\Component;
use Spiral\Security\Exceptions\GuardException;

/**
 * Checks permissions using given actor.
 */
class Guard extends Component implements GuardInterface
{
    /**
     * @var ActorInterface|null
     */
    private $actor = null;

    /**
     * Session specific roles.
     *
     * @var array
     */
    private $roles = [];

    /**
     * @var PermissionsInterface
     */
    private $permissions = null;

    /**
     * @param array                $roles Session specific roles.
     * @param ActorInterface       $actor
     * @param PermissionsInterface $permissions
     */
    public function __construct(
        array $roles = [],
        ActorInterface $actor = null,
        PermissionsInterface $permissions
    ) {
        $this->roles = $roles;
        $this->actor = $actor;
        $this->permissions = $permissions;
    }

    /**
     * {@inheritdoc}
     */
    public function allows(string $permission, array $context = []): bool
    {
        foreach ($this->getRoles() as $role) {
            if (!$this->permissions->hasRole($role)) {
                continue;
            }

            $rule = $this->permissions->getRule($role, $permission);
            if ($rule === self::ALLOW) {
                return true;
            }

            if ($rule instanceof RuleInterface) {
                if ($rule->allows($this->actor, $permission, $context)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Currently active actor/session roles.
     *
     * @return array
     *
     * @throws GuardException
     */
    public function getRoles(): array
    {
        return array_merge($this->roles, $this->actor->getRoles());
    }

    /**
     * Create instance of guard with session specific roles (existed roles will be droppped).
     *
     * @param array $roles
     *
     * @return self
     */
    public function withRoles(array $roles): Guard
    {
        $guard = clone $this;
        $guard->roles = $roles;

        return $guard;
    }

    /**
     * {@inheritdoc}
     *
     * @throws GuardException
     */
    public function getActor(): ActorInterface
    {
        if (empty($this->actor)) {
            throw new GuardException("Unable to get Guard Actor, no value set");
        }

        return $this->actor;
    }

    /**
     * {@inheritdoc}
     */
    public function withActor(ActorInterface $actor): GuardInterface
    {
        $guard = clone $this;
        $guard->actor = $actor;

        return $guard;
    }
}