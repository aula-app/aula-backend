<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\TenantStatisticsService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Statistics extends Page
{
    protected static string $view = 'filament.pages.statistics';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Statistics';

    protected static ?int $navigationSort = 2;

    /** @var array<int, array<string, mixed>>|null */
    public ?array $statistics = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate & Preview')
                ->icon('heroicon-o-arrow-path')
                ->action('generate'),

            Action::make('downloadDirect')
                ->label('Generate & Download CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action('downloadDirect'),
        ];
    }

    public function generate(): void
    {
        /** @var TenantStatisticsService $service */
        $service = app(TenantStatisticsService::class);
        $this->statistics = $service->getAllTenantsStatistics();
    }

    public function downloadPreview(): StreamedResponse
    {
        return $this->streamCsvDownload();
    }

    public function downloadDirect(): StreamedResponse
    {
        return $this->streamCsvDownload();
    }

    private function streamCsvDownload(): StreamedResponse
    {
        /** @var TenantStatisticsService $service */
        $service = app(TenantStatisticsService::class);
        $stats = $service->getAllTenantsStatistics();
        $csv = $service->generateCsv($stats);
        $filename = 'tenant-statistics-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
