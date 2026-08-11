<?php

namespace App\Models;

use App\Services\Idp\IdpProviders;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    public const string IDP_MIGRATION_FLAGGED = 'flagged';

    public const string IDP_MIGRATION_CONNECTED = 'connected';

    public const string IDP_MIGRATION_REVIEWING = 'reviewing';

    public const string IDP_MIGRATION_IMPORTING = 'importing';

    public const string IDP_MIGRATION_LINKING = 'linking';

    public const string IDP_MIGRATION_COMPLETED = 'completed';

    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'name',
            'api_base_url',
            'contact_info',
            'school_type_id',
            'admin1_name',
            'admin1_username',
            'admin1_email',
            'admin1_init_pass_url',
            'admin2_name',
            'admin2_username',
            'admin2_email',
            'admin2_init_pass_url',
            'instance_code',
            'jwt_key',
            'sso_enabled',
            'sso_provider',
            'sso_idp_config',
            'sso_force_logout',
            'sso_required',
            'sso_require_email_verified',
            'idp_school_id',
            'idp_migration_status',
            'idp_import_status',
            'idp_import_rooms',
            'idp_import_users',
            'idp_import_error',
            'idp_import_started_at',
            'idp_import_finished_at',
        ]);
    }

    /**
     * Whether this tenant's users and rooms come from an identity provider's
     * directory.
     *
     * Keyed on `sso_provider` — the same alias Keycloak brokers under — so it
     * is true from tenant creation. The provider's school id is not known until
     * the first person signs in and tells us, so it cannot be what identifies a
     * synced tenant.
     */
    /**
     * Whether this school used aula before it started syncing from a directory.
     *
     * Such a tenant has real accounts and content that predate the provider, so
     * the greenfield shortcuts — bootstrap on first login, create every user —
     * would collide with them.
     */
    public function isMigratingToIdp(): bool
    {
        return $this->idp_migration_status !== null
            && $this->idp_migration_status !== self::IDP_MIGRATION_COMPLETED;
    }

    public function usesIdpDirectory(): bool
    {
        return $this->idp_school_id !== null
            || app(IdpProviders::class)->isConfigured($this->sso_provider);
    }

    public function schoolType(): BelongsTo
    {
        return $this->belongsTo(SchoolType::class);
    }
}
