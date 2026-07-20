<?php

declare(strict_types=1);

namespace App\Services\Idp;

use App\Models\Tenant;
use App\Services\Idp\Contracts\IdentityDirectory;
use App\Services\Idp\Contracts\WebhookAdapter;
use InvalidArgumentException;

/**
 * Resolves a provider alias to its implementation and settings.
 *
 * The alias is `tenants.sso_provider` — the same value Keycloak uses as its IdP
 * alias — so a tenant's login and its directory can never point at different
 * providers.
 */
final class IdpProviders
{
    public function isConfigured(?string $provider): bool
    {
        return $provider !== null && $provider !== '' && is_array($this->settings($provider));
    }

    public function directory(string $provider): IdentityDirectory
    {
        return app($this->classFor($provider, 'directory'));
    }

    public function webhook(string $provider): WebhookAdapter
    {
        return app($this->classFor($provider, 'webhook'));
    }

    /**
     * The alias this tenant syncs from, or null when it syncs from nowhere.
     */
    public function forTenant(Tenant $tenant): ?string
    {
        $provider = $tenant->sso_provider;

        return $this->isConfigured($provider) ? $provider : null;
    }

    public function config(string $provider, string $key, mixed $default = null): mixed
    {
        return data_get($this->settings($provider), $key, $default);
    }

    /**
     * Every configured alias, for the scan that finds which tenant an entity
     * belongs to.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys((array) config('idp.providers', []));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function settings(string $provider): ?array
    {
        $settings = config('idp.providers.'.$provider);

        return is_array($settings) ? $settings : null;
    }

    /**
     * @return class-string
     */
    private function classFor(string $provider, string $key): string
    {
        $class = $this->config($provider, $key);

        if (! is_string($class) || $class === '') {
            throw new InvalidArgumentException("Identity provider [{$provider}] has no {$key} configured.");
        }

        return $class;
    }
}
