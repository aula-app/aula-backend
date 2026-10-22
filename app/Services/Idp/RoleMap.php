<?php

declare(strict_types=1);

namespace App\Services\Idp;

/**
 * Maps a provider's role vocabulary onto both of aula's ranks: the account-wide
 * `userlevel` and the per-room entry in the `roles` column.
 *
 * Both read one `config/idp.php` map, so an account cannot come out privileged
 * account-wide but not in its own rooms, or the reverse.
 */
final class RoleMap
{
    public function __construct(
        private readonly IdpProviders $providers,
    ) {}

    /**
     * The aula level for a provider role: the initial `userlevel` of a new
     * account and the initial role in each room it joins.
     */
    public function level(string $provider, ?string $role): int
    {
        $default = (int) $this->providers->config($provider, 'default_role', 20);

        if ($role === null) {
            return $default;
        }

        /** @var array<string, int> $map */
        $map = (array) $this->providers->config($provider, 'roles', []);

        return $map[strtoupper($role)] ?? $default;
    }
}
