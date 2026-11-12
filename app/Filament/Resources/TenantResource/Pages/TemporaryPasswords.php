<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TemporaryPasswordExport;
use App\Services\TemporaryPasswordService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Lists the rooms of a tenant with users who never chose a password and
 * downloads their temporary passwords: one PDF per room, or all as a ZIP.
 *
 * Downloads are streamed, never stored: they hold passwords in plain text.
 *
 * @psalm-import-type RoomOverview from TemporaryPasswordService
 * @psalm-import-type Download from TemporaryPasswordExport
 */
class TemporaryPasswords extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TenantResource::class;

    protected string $view = 'filament.pages.temporary-passwords';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Password login is refused for an SSO-only tenant.
        abort_if((bool) $this->tenant()->sso_required, 404);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Temporary passwords';
    }

    public function getSubheading(): ?string
    {
        return $this->tenant()->name.' ('.$this->tenant()->instance_code.')';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadAll')
                ->label('Download all (ZIP)')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->disabled(fn (): bool => $this->rooms() === [])
                ->requiresConfirmation()
                ->modalHeading('Download all rooms')
                ->modalDescription(fn (): string => $this->confirmation(array_sum(array_column($this->rooms(), 'new'))))
                ->modalSubmitActionLabel('Download ZIP')
                ->action(fn (): ?StreamedResponse => $this->downloadAll()),
        ];
    }

    /**
     * @return list<RoomOverview>
     */
    public function rooms(): array
    {
        try {
            return app(TemporaryPasswordService::class)->overview($this->tenant());
        } catch (Throwable $e) {
            Log::error('Reading rooms for temporary passwords failed', [
                'tenant' => $this->tenant()->instance_code,
                'exception' => $e,
            ]);

            return [];
        }
    }

    public function confirmation(int $new): string
    {
        return $new === 0
            ? 'No password changes: every listed user already holds a temporary password.'
            : "{$new} ".($new === 1 ? 'user gets' : 'users get').' a new temporary password. Their set-password links stop working.';
    }

    public function downloadRoom(string $sheetKey): ?StreamedResponse
    {
        return $this->download(fn (): ?array => app(TemporaryPasswordExport::class)->room($this->tenant(), $sheetKey));
    }

    public function downloadAll(): ?StreamedResponse
    {
        return $this->download(fn (): ?array => app(TemporaryPasswordExport::class)->all($this->tenant()));
    }

    /**
     * @param  callable(): ?Download  $export
     */
    private function download(callable $export): ?StreamedResponse
    {
        try {
            $download = $export();
        } catch (Throwable $e) {
            Log::error('Issuing temporary passwords failed', [
                'tenant' => $this->tenant()->instance_code,
                'exception' => $e,
            ]);

            Notification::make()
                ->title('Temporary passwords failed')
                ->body('Downloading again lists the passwords already issued. See the log for the error.')
                ->danger()
                ->send();

            return null;
        }

        if ($download === null) {
            Notification::make()
                ->title('Nothing to do')
                ->body('Every active user here has already chosen a password.')
                ->warning()
                ->send();

            return null;
        }

        Log::info('Temporary passwords downloaded', [
            'tenant' => $this->tenant()->instance_code,
            'file' => $download['filename'],
            'new' => $download['new'],
            'existing' => $download['existing'],
            'manager' => auth()->id(),
        ]);

        Notification::make()
            ->title('Temporary passwords downloaded')
            ->body("{$download['new']} new, {$download['existing']} kept.")
            ->success()
            ->send();

        $content = $download['content'];

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            $download['filename'],
            ['Content-Type' => $download['mime']],
        );
    }

    private function tenant(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $this->getRecord();

        return $tenant;
    }
}
