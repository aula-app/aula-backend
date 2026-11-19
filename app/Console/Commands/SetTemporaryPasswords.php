<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TemporaryPasswordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gives the active regular users of a tenant who never chose a password a
 * temporary one and writes one CSV per room, so a teacher can hand the
 * credentials out class by class.
 *
 * @psalm-import-type Sheet from TemporaryPasswordService
 */
class SetTemporaryPasswords extends Command
{
    private const array CSV_HEADER = ['Full name', 'Username', 'Temporary password', 'Class'];

    protected $signature = 'tenant:temp-passwords
                            {instance_code : Instance code of the tenant}
                            {--output= : Directory for the CSV files (default: storage/app/temp-passwords/<code>/<timestamp>)}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Set a temporary password for active regular users who never chose one and write one CSV per room';

    public function handle(TemporaryPasswordService $service): int
    {
        $code = (string) $this->argument('instance_code');
        $tenant = Tenant::where('instance_code', $code)->first();

        if ($tenant === null) {
            $this->error("No tenant with instance code [{$code}].");

            return self::FAILURE;
        }

        $outputDir = $this->option('output')
            ?: storage_path('app/temp-passwords/'.$code.'/'.now()->format('Y-m-d_His'));

        $summary = $service->summary($tenant);

        $this->line("Tenant: <info>{$tenant->name}</info> ({$code})");
        $this->table(['', 'count'], [
            ['users without a password (get a new temporary password)', $summary['new']],
            ['users already holding a temporary password (kept)', $summary['existing']],
        ]);
        $this->line("CSV files go to: {$outputDir}");

        if ($summary['new'] + $summary['existing'] === 0) {
            $this->warn('Nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Set temporary passwords for these users?')) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $result = $service->issue($tenant);
        $written = array_map(fn (array $sheet): string => $this->writeCsv($outputDir, $sheet), $result['sheets']);

        $this->info("Set {$result['new']} new passwords, kept {$result['existing']}. Wrote ".count($written).' CSV file(s):');
        foreach ($written as $file) {
            $this->line("  {$file}");
        }
        $this->warn('The files hold passwords in plain text. Delete them once handed out.');

        return self::SUCCESS;
    }

    /**
     * @param  Sheet  $sheet
     */
    private function writeCsv(string $outputDir, array $sheet): string
    {
        $name = $sheet['room_id'] === null
            ? Str::slug($sheet['room'])
            : Str::slug($sheet['room']).'-'.$sheet['room_id'];
        $path = $outputDir.'/'.$name.'.csv';

        File::ensureDirectoryExists($outputDir);

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Cannot write {$path}");
        }

        // BOM so Excel reads umlauts as UTF-8; ';' is what Excel expects in a
        // German locale and what the legacy CSV import uses.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::CSV_HEADER, ';', '"', '\\', "\n");
        foreach ($sheet['rows'] as $row) {
            fputcsv($handle, [$row['name'], $row['username'], $row['password'], $row['classes']], ';', '"', '\\', "\n");
        }
        fclose($handle);

        return $path;
    }
}
