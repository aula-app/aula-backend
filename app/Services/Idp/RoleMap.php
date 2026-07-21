<?php

declare(strict_types=1);

namespace App\Services\Idp;

/**
 * Maps a provider's role vocabulary onto aula's two notions of rank: the
 * account-wide `userlevel` and the per-room entry in the `roles` column.
 *
 * Both come from one configured map, so a teacher cannot end up privileged
 * account-wide but not in their own class, or the other way round.
 */
final class RoleMap
{
    public function __construct(
        private readonly IdpProviders $providers,
    ) {}

    public function userlevel(string $provider, ?string $role): int
    {
        $default = (int) $this->providers->config($provider, 'default_role', 20);

        if ($role === null) {
            return $default;
        }

        /** @var array<string, int> $map */
        $map = (array) $this->providers->config($provider, 'roles', []);

        return $map[strtoupper($role)] ?? $default;
    }

    /**
     * The role recorded against each room the person belongs to.
     */
    public function roomRole(string $provider, ?string $role): int
    {
        return $this->userlevel($provider, $role);
    }
}
