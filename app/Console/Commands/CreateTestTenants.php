<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\UseCases\CreateTenantUseCase;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTestTenants extends Command
{
    protected $signature = 'tenants:create-test
                            {--index=-1 : Only create/reuse one tenant with exact code}
                            {--amount=3}
                            {--fresh : Delete existing test instances before running}
                            {--cleanup : Only delete existing instances}';
    protected $description = 'TODO';

    public function __construct(
        private readonly CreateTenantUseCase $createTenantUseCase
    ) {
        parent::__construct();
    }

    private function cleanUp(): void
    {
        Tenant::whereLike('instance_code', 'TEST%')->delete();
        /* doesn't work, needs central db connection...
        foreach (Tenant::whereLike('instance_code', 'TEST%')->get() as $i => $tenant) {
            $id = $tenant->id;
            $this->line(sprintf("Deleting tenant %d '%s'", $i + 1, $tenant->instance_code));
            $tenant->delete();
            DB::statement("DROP DATABASE `tenant_$id`");
        }
        */
    }

    public function handle(): int
    {
        try {
            if ($this->option('fresh') || $this->option('cleanup')) {
                $this->cleanUp();
            }
            if (!$this->option('cleanup')) {
                $index = (int) $this->option('index');
                $indexes = [];
                if ($index > -1) {
                    $indexes[] = $index;
                } else {
                    $amount = (int) $this->option('amount');
                    $indexes = range(0, $amount - 1);
                }
                foreach ($indexes as $i) {
                    $name = "TEST.$i";
                    $code = "TEST$i";
                    if (Tenant::where('instance_code', $code)->exists()) {
                        $this->line(sprintf("Reusing tenant %d '%s', instance code '%s'", $i, $name, $code));
                        // TODO: use truncate from FE?
                        continue;
                    }
                    $this->line(sprintf("Creating tenant %d '%s', instance code '%s'", $i, $name, $code));
                    $this->createTenantUseCase->execute(
                        name: $name,
                        instanceCode: $code,
                        admin1Username: "admin",
                        admin1FullName: "Test Admin",
                        admin1Email: "admin1@aula.de",
                        admin2Username: "admin2",
                        admin2FullName: "Test Admin II",
                        admin2Email: "admin2@aula.de",
                        adminPassword: "aula"
                    );
                }
            }
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}

