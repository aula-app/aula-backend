<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Idp\Migration\MergeProposalBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs MergeProposalBuilder and prints the idp_merge_candidates rows, the same
 * data MergeProposalController::index() serves.
 */
class ProposeIdpMerge extends Command
{
    protected $signature = 'idp:propose-merge
                            {instance_code : Instance code of the tenant}
                            {--show=all : all, merges, ambiguous, aula-only or idp-only}';

    protected $description = 'Match a school\'s existing rows against its identity provider and show the result';

    public function handle(MergeProposalBuilder $builder): int
    {
        $code = (string) $this->argument('instance_code');
        $tenant = Tenant::where('instance_code', $code)->first();

        if ($tenant === null) {
            $this->error("No tenant with instance code [{$code}].");

            return self::FAILURE;
        }

        try {
            $counts = $tenant->run(fn () => $builder->build($tenant));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            'confident: <info>%d</info>   ambiguous: <comment>%d</comment>   unmatched: %d',
            $counts['confident'] ?? 0,
            $counts['ambiguous'] ?? 0,
            $counts['none'] ?? 0,
        ));

        $rows = $tenant->run(fn () => DB::table('idp_merge_candidates')
            ->orderBy('kind')->orderBy('outcome')->orderBy('id')->get());

        $show = (string) $this->option('show');

        $this->table(
            ['kind', 'aula', 'provider', 'name', 'outcome', 'default'],
            $rows->filter(fn ($row): bool => match ($show) {
                'merges' => $row->outcome === 'confident',
                'ambiguous' => $row->outcome === 'ambiguous',
                'aula-only' => $row->idp_id === null,
                'idp-only' => $row->local_id === null,
                default => true,
            })->map(fn ($row): array => [
                $row->kind,
                $row->local_name ?? '—',
                $row->idp_name ?? '—',
                $row->idp_name_kind ?? '',
                $row->outcome,
                $row->decision ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
