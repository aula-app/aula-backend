<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IdpDirectoryEntry;
use App\Models\IdpWebhookEvent;
use App\Models\LegacyUser;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts a tenant back to "never synced", so the next SSO login bootstraps it
 * again from scratch.
 *
 * Testing the first-login flow otherwise means a long tinker one-liner that is
 * easy to fire at the wrong instance code.
 *
 * Only touches what the directory import created. Accounts that existed before
 * it are kept — cleared of their provider identity, but not deleted.
 */
class ResetIdpTenant extends Command
{
    protected $signature = 'idp:reset-tenant
                            {instance_code : Instance code of the tenant to reset}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Reset a tenant so the next SSO login re-runs the directory import';

    public function handle(): int
    {
        $code = (string) $this->argument('instance_code');
        $tenant = Tenant::where('instance_code', $code)->first();

        if ($tenant === null) {
            $this->error("No tenant with instance code [{$code}].");

            return self::FAILURE;
        }

        $summary = $this->summarise($tenant);

        $this->line("Tenant: <info>{$tenant->name}</info> ({$code})");
        $this->table(['', 'count'], [
            ['users the import created (will be deleted)', $summary['imported_users']],
            ['rooms the import created (will be deleted)', $summary['imported_rooms']],
            ['other users (kept, provider identity cleared)', $summary['other_users']],
            ['directory index entries', $summary['directory']],
            ['captured webhook events', $summary['events']],
        ]);
        $this->line('School: '.($tenant->idp_school_id ?? '(none)').'   import status: '.($tenant->idp_import_status ?? '(none)'));

        if (! $this->option('force') && ! $this->confirm("Reset {$code}? Imported users and rooms will be deleted.")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $this->resetTenantDatabase($tenant);
        $this->resetCentralRecords($tenant);

        $this->info("Reset {$code}. The next SSO login will import the school again.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function summarise(Tenant $tenant): array
    {
        /** @var array<string, int> $counts */
        $counts = $tenant->run(fn (): array => [
            'imported_users' => LegacyUser::whereNotNull('idp_user_id')->count(),
            'imported_rooms' => DB::table('au_rooms')->whereNotNull('idp_group_id')->count(),
            'other_users' => LegacyUser::whereNull('idp_user_id')->count(),
        ]);

        return $counts + [
            'directory' => IdpDirectoryEntry::where('tenant_id', $tenant->id)->count(),
            'events' => IdpWebhookEvent::where('tenant_id', $tenant->id)->count(),
        ];
    }

    private function resetTenantDatabase(Tenant $tenant): void
    {
        $tenant->run(function (): void {
            $rooms = DB::table('au_rooms')
                ->whereNotNull('idp_group_id')
                ->get(['id', 'hash_id']);

            $roomIds = $rooms->pluck('id')->all();

            if ($roomIds !== []) {
                DB::table('au_rel_rooms_users')->whereIn('room_id', $roomIds)->delete();
                DB::table('au_rooms')->whereIn('id', $roomIds)->delete();
            }

            // Only rows the import created. Anyone who has signed in owns their
            // account now, whatever brought it into being.
            $importedIds = LegacyUser::whereNotNull('idp_user_id')->pluck('id')->all();

            if ($importedIds !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $importedIds)->delete();
                LegacyUser::whereIn('id', $importedIds)->delete();
            }

            // Everyone left goes back to never-having-signed-in, which is what
            // makes the next login the tenant's first.
            DB::table('au_users_basedata')->update(['sso_sub' => null, 'idp_user_id' => null]);

            // A proposal describes rows that no longer exist.
            DB::table('idp_merge_candidates')->truncate();

            $this->stripRolesForRooms($rooms->pluck('hash_id')->all());
        });
    }

    /**
     * Drop `roles` entries that point at rooms we just deleted.
     *
     * Surgical rather than resetting the column: entries for rooms created
     * inside aula are somebody's real configuration, and a reset of the
     * directory sync has no business discarding them.
     *
     * @param  list<string>  $roomHashIds
     */
    private function stripRolesForRooms(array $roomHashIds): void
    {
        if ($roomHashIds === []) {
            return;
        }

        foreach (DB::table('au_users_basedata')->get(['id', 'roles']) as $row) {
            /** @var list<array{role?: int, room?: string}> $roles */
            $roles = json_decode((string) $row->roles, true) ?: [];

            $kept = array_values(array_filter(
                $roles,
                fn ($entry): bool => is_array($entry) && ! in_array($entry['room'] ?? null, $roomHashIds, true),
            ));

            if (count($kept) !== count($roles)) {
                DB::table('au_users_basedata')->where('id', $row->id)->update(['roles' => json_encode($kept)]);
            }
        }
    }

    private function resetCentralRecords(Tenant $tenant): void
    {
        $tenant->update([
            'idp_school_id' => null,
            // A tenant left part-way through a migration never bootstraps on a
            // first login again, so without this the reset hands back a tenant
            // that cannot do the thing it was reset for.
            'idp_migration_status' => null,
            'idp_import_status' => null,
            'idp_import_error' => null,
            'idp_import_rooms' => 0,
            'idp_import_users' => 0,
            'idp_import_started_at' => null,
            'idp_import_finished_at' => null,
        ]);

        IdpDirectoryEntry::where('tenant_id', $tenant->id)->delete();

        // Events that never resolved to a tenant are left alone: they may
        // belong to a school someone else hosts.
        IdpWebhookEvent::where('tenant_id', $tenant->id)->delete();
    }
}
