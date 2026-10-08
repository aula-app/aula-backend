<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Models\IdpDirectoryEntry;
use App\Models\Tenant;
use App\Services\PasswordSetupLinks;
use Illuminate\Support\Facades\DB;

/**
 * Unlinks a tenant from its IdP school and returns it to password login.
 *
 * SSO logins to a tenant without idp_school_id are refused and nothing sets it
 * again. Password login refuses a user with an sso_sub and every user on an
 * sso_required tenant, so admin1 and admin2 lose their provider identity and
 * get new password setup links, and sso_required is switched off. Imported
 * users and rooms are untouched.
 */
class ReleaseIdpSchoolUseCase
{
    public function __construct(private readonly PasswordSetupLinks $links) {}

    public function execute(Tenant $tenant): void
    {
        $urls = $tenant->run(fn (): array => [
            'admin1_init_pass_url' => $this->reissue($tenant, $tenant->admin1_username),
            'admin2_init_pass_url' => $this->reissue($tenant, $tenant->admin2_username),
        ]);

        $tenant->update([
            'idp_school_id' => null,
            'sso_required' => false,
            ...$urls,
        ]);

        // Stale index rows would keep routing the school's webhooks here.
        IdpDirectoryEntry::where('tenant_id', $tenant->id)->delete();
    }

    /**
     * @return string|null null when no user has that username
     */
    private function reissue(Tenant $tenant, ?string $username): ?string
    {
        if ($username === null || $username === '') {
            return null;
        }

        $userId = DB::table('au_users_basedata')->where('username', $username)->value('id');

        if ($userId === null) {
            return null;
        }

        DB::table('au_users_basedata')
            ->where('id', $userId)
            ->update(['sso_sub' => null, 'idp_user_id' => null]);

        return $this->links->url(
            (string) $tenant->api_base_url,
            (string) $tenant->instance_code,
            $this->links->issue((int) $userId),
        );
    }
}
