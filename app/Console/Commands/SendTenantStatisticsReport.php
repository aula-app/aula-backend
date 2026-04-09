<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\TenantStatisticsReport;
use App\Services\TenantStatisticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Log;
use Throwable;

class SendTenantStatisticsReport extends Command
{
    protected $signature = 'statistics:send-report
                            {--output= : Custom file path to save the CSV (default: storage/app/statistics/)}';

    protected $description = 'Generate a CSV statistics report for all tenants, save it, and email it to configured recipients';

    public function __construct(private readonly TenantStatisticsService $statisticsService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $recipients = $this->getRecipients();

        if (empty($recipients)) {
            $this->info('No recipients configured. Set STATISTICS_REPORT_RECIPIENTS in .env.');

            return self::SUCCESS;
        }

        $this->info('Collecting statistics for all tenants...');
        $allStats = $this->statisticsService->getAllTenantsStatistics();

        if (empty($allStats)) {
            $this->warn('No tenants found. Nothing to report.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Collected statistics for %d tenant(s).', count($allStats)));

        $csv = $this->statisticsService->generateCsv($allStats);
        $filename = 'statistics-'.now()->format('Y-m-d').'.csv';

        $savedPath = $this->saveCsv($csv, $filename);
        if ($savedPath !== null) {
            $this->info("CSV saved to: {$savedPath}");
        }

        $this->sendReport($csv, $filename, $recipients, count($allStats));

        return self::SUCCESS;
    }

    /**
     * Save the CSV to the configured or default storage path.
     * Returns the resolved file path, or null on failure.
     */
    private function saveCsv(string $csv, string $filename): ?string
    {
        $customPath = $this->option('output');

        if ($customPath !== null) {
            try {
                file_put_contents($customPath, $csv);

                return $customPath;
            } catch (Throwable $e) {
                $this->warn("Could not write CSV to '{$customPath}': {$e->getMessage()}");

                return null;
            }
        }

        $storagePath = 'statistics/'.$filename;

        try {
            Storage::put($storagePath, $csv);

            return Storage::path($storagePath);
        } catch (Throwable $e) {
            $this->warn("Could not save CSV to storage: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Send the report email to all recipients.
     *
     * @param  string[]  $recipients
     */
    private function sendReport(string $csv, string $filename, array $recipients, int $tenantCount): void
    {
        $mailable = new TenantStatisticsReport($csv, $filename, $tenantCount);

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(clone $mailable);
                $this->info("Report sent to: {$recipient}");
                Log::debug("Report sent to: {$recipient}");
            } catch (Throwable $e) {
                $errorMsg = "Failed to send report to {$recipient}: {$e->getMessage()}";
                $this->error($errorMsg);
                Log::warning($errorMsg);
            }
        }
    }

    /**
     * Parse recipients from the STATISTICS_REPORT_RECIPIENTS env variable.
     *
     * @return string[]
     */
    private function getRecipients(): array
    {
        $raw = config('statistics.report_recipients', '');

        if (empty($raw)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map('trim', explode(',', $raw))
            )
        );
    }
}
