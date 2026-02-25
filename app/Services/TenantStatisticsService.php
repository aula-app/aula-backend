<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantStatisticsService
{
    private const array STATISTICS_TABLES = [
        'votes_count' => 'au_votes',
        'likes_count' => 'au_likes',
        'ideas_count' => 'au_ideas',
        'comments_count' => 'au_comments',
        'users_count' => 'au_users_basedata',
    ];

    private const array CSV_COLUMNS = [
        ['key' => 'instance_name', 'label' => 'Instance Name'],
        ['key' => 'instance_code', 'label' => 'Instance Code'],
        ['key' => 'generated_at', 'label' => 'Generated Date'],
        ['key' => 'users_count', 'label' => 'Total Users'],
        ['key' => 'votes_count', 'label' => 'Total Votes'],
        ['key' => 'likes_count', 'label' => 'Total Likes'],
        ['key' => 'ideas_count', 'label' => 'Total Ideas'],
        ['key' => 'comments_count', 'label' => 'Total Comments'],
    ];

    /**
     * Collect statistics for all tenants.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllTenantsStatistics(): array
    {
        $tenants = Tenant::all();
        $generatedAt = now()->format('Y-m-d H:i:s');
        $results = [];

        foreach ($tenants as $tenant) {
            $stats = [
                'instance_name' => $tenant->name,
                'instance_code' => $tenant->instance_code,
                'generated_at' => $generatedAt,
            ];

            foreach (self::STATISTICS_TABLES as $countKey => $tableName) {
                try {
                    $stats[$countKey] = $tenant->run(
                        fn () => DB::table($tableName)->count()
                    );
                } catch (Throwable $e) {
                    Log::warning("Failed to count {$tableName} for tenant {$tenant->instance_code}", [
                        'tenant_id' => $tenant->id,
                        'table' => $tableName,
                        'error' => $e->getMessage(),
                    ]);
                    $stats[$countKey] = null;
                }
            }

            $results[] = $stats;
        }

        return $results;
    }

    /**
     * Generate a CSV string from an array of per-tenant statistics.
     *
     * @param array<int, array<string, mixed>> $allStats
     */
    public function generateCsv(array $allStats): string
    {
        $output = fopen('php://temp', 'r+');

        $headers = array_column(self::CSV_COLUMNS, 'label');
        fputcsv($output, $headers);

        foreach ($allStats as $stats) {
            $row = array_map(
                fn (array $col) => $stats[$col['key']] ?? '',
                self::CSV_COLUMNS
            );
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
