<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\UseCases\CreateTenantUseCase;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CreateTestTenants extends Command
{
    protected $signature = 'tenants:create-test
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
        // TODO also drop their tables OR reuse tenant, but truncate
        Tenant::whereLike('instance_code', 'TEST%')->delete();
    }

    public function handle(): int
    {
        try {
            if ($this->option('fresh') || $this->option('cleanup')) {
                $this->cleanUp();
            }
            if (!$this->option('cleanup')) {
                $amount = (int) $this->option('amount');
                for ($i = 0; $i < $amount; $i++) {
                    $name = "TEST.$i";
                    $code = "TEST$i";
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
                    $this->line(sprintf("Created tenant %d/%d '%s', instance code '%s'", $i + 1, $amount, $name, $code));
                }
            }
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}

