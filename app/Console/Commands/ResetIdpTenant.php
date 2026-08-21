<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IdpDirectoryEntry;
use App\Models\IdpWebhookEvent;
use App\Models\LegacyUser;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Puts a tenant back to never-synced, so the next SSO login runs
 * SsoController::bootstrapIdpTenant() again.
 *
 * Deletes only what SchoolImport created. An account that predates the import
 * is kept, with `sso_sub` and `idp_user_id` cleared.
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
     * Accounts SchoolImport created, as opposed to accounts it claimed.
     *
     * They carry an `idp_user_id` and no password. admin1_username and
     * admin2_username are excluded: bootstrapIdpTenant() stamps its identity on
     * one of them, and deleting it leaves no admin for the next first login to
     * take over. Email is not a signal, since adoptDirectoryProvisionedUser()
     * writes one onto an imported row.
     *
     * @return Builder<LegacyUser>
     */
    private function directoryCreatedUsers(Tenant $tenant): Builder
    {
        $seededAdmins = array_values(array_filter([$tenant->admin1_username, $tenant->admin2_username]));

        return LegacyUser::whereNotNull('idp_user_id')
            ->where(fn (Builder $q) => $q->whereNull('pw')->orWhere('pw', ''))
            ->when($seededAdmins !== [], fn (Builder $q) => $q->whereNotIn('username', $seededAdmins));
    }

    /**
     * @return array<string, int>
     */
    private function summarise(Tenant $tenant): array
    {
        /** @var array<string, int> $counts */
        $counts = $tenant->run(fn (): array => [
            'imported_users' => $this->directoryCreatedUsers($tenant)->count(),
            'imported_rooms' => DB::table('au_rooms')->whereNotNull('idp_group_id')->count(),
            'other_users' => LegacyUser::query()->count() - $this->directoryCreatedUsers($tenant)->count(),
        ]);

        return $counts + [
            'directory' => IdpDirectoryEntry::where('tenant_id', $tenant->id)->count(),
            'events' => IdpWebhookEvent::where('tenant_id', $tenant->id)->count(),
        ];
    }

    private function resetTenantDatabase(Tenant $tenant): void
    {
        $tenant->run(function () use ($tenant): void {
            $rooms = DB::table('au_rooms')
                ->whereNotNull('idp_group_id')
                ->get(['id', 'hash_id']);

            $roomIds = $rooms->pluck('id')->all();

            if ($roomIds !== []) {
                DB::table('au_rel_rooms_users')->whereIn('room_id', $roomIds)->delete();
                DB::table('au_rooms')->whereIn('id', $roomIds)->delete();
            }

            // `idp_user_id` alone is not the test: bootstrapIdpTenant() stamps
            // it onto the seeded admin, and MergeProposalApplier stamps it onto
            // accounts that predate the import.
            $importedIds = $this->directoryCreatedUsers($tenant)->pluck('id')->all();

            if ($importedIds !== []) {
                DB::table('au_rel_rooms_users')->whereIn('user_id', $importedIds)->delete();
                LegacyUser::whereIn('id', $importedIds)->delete();
            }

            // Clearing sso_sub on the rows that remain is what makes the next
            // login the tenant's first.
            DB::table('au_users_basedata')->update(['sso_sub' => null, 'idp_user_id' => null]);

            // idp_merge_candidates describes rows that no longer exist.
            DB::table('idp_merge_candidates')->truncate();

            $this->stripRolesForRooms($rooms->pluck('hash_id')->all());
        });
    }

    /**
     * Drop the `roles` entries pointing at the rooms just deleted.
     *
     * Entry by entry rather than clearing the column: entries for rooms created
     * inside aula are real configuration that a sync reset does not own.
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
            // bootstrapIdpTenant() declines while this is set, so leaving it
            // would return a tenant that cannot do what the reset is for.
            'idp_migration_status' => null,
            'idp_import_status' => null,
            'idp_import_error' => null,
            'idp_import_rooms' => 0,
            'idp_import_users' => 0,
            'idp_import_started_at' => null,
            'idp_import_finished_at' => null,
        ]);

        IdpDirectoryEntry::where('tenant_id', $tenant->id)->delete();

        // Events with a null tenant_id are left alone: they may belong to a
        // school another installation hosts.
        IdpWebhookEvent::where('tenant_id', $tenant->id)->delete();
    }
}
