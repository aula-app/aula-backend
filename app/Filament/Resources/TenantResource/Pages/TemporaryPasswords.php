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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Temporary passwords of a tenant, by room. Resetting sets a temporary
 * password for the users who never chose one; the downloads only render the
 * temporary passwords already set, as one PDF per room or a ZIP of all.
 *
 * Downloads are streamed, never stored: they hold passwords in plain text.
 *
 * @psalm-import-type RoomOverview from TemporaryPasswordService
 * @psalm-import-type Download from TemporaryPasswordExport
 */
class TemporaryPasswords extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    public const string RESET_ALL = 'resetAll';

    public const string DOWNLOAD_ALL = 'downloadAll';

    public const string RESET_ROOM = 'resetRoom';

    public const string DOWNLOAD_ROOM = 'downloadRoom';

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
            Action::make(self::RESET_ALL)
                ->label('Reset all passwords')
                ->icon('heroicon-o-key')
                ->color('danger')
                ->disabled(fn (): bool => $this->count('new') === 0)
                ->requiresConfirmation()
                ->modalHeading('Reset passwords in all rooms')
                ->modalDescription(fn (): string => $this->resetWarning($this->pendingUsers()))
                ->modalSubmitActionLabel('Reset passwords')
                ->action(fn () => $this->resetPasswords(null)),

            Action::make(self::DOWNLOAD_ALL)
                ->label('Download all (ZIP)')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->disabled(fn (): bool => $this->count('existing') === 0)
                ->action(fn (): ?StreamedResponse => $this->downloadPasswords(null)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect($this->rooms())->keyBy('key')->all())
            ->heading('Rooms')
            ->description('Active users who have not chosen a password, by room. Moderators, admins and SSO accounts are left out.')
            ->columns([
                TextColumn::make('room')
                    ->label('Room')
                    ->weight('medium'),
                TextColumn::make('new')
                    ->label('Without a password')
                    ->numeric(),
                TextColumn::make('existing')
                    ->label('Temporary password set')
                    ->numeric(),
            ])
            ->recordActions([
                Action::make(self::RESET_ROOM)
                    ->label('Reset passwords')
                    ->icon('heroicon-o-key')
                    ->color('danger')
                    ->disabled(fn (array $record): bool => $record['new'] === 0)
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => "Reset passwords in {$record['room']}")
                    ->modalDescription(fn (array $record): string => $this->resetWarning($record['new']))
                    ->modalSubmitActionLabel('Reset passwords')
                    ->action(fn (array $record) => $this->resetPasswords($record['key'])),

                Action::make(self::DOWNLOAD_ROOM)
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->disabled(fn (array $record): bool => $record['existing'] === 0)
                    ->action(fn (array $record): ?StreamedResponse => $this->downloadPasswords($record['key'])),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nothing to do')
            ->emptyStateDescription('Every active user of this tenant has already chosen a password.');
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

    private function resetWarning(int $users): string
    {
        return "{$users} ".($users === 1 ? 'user gets' : 'users get').' a temporary password. '
            .'Their set-password links stop working. This cannot be undone. '
            .'Users who already hold a temporary password or chose their own are not touched.';
    }

    /**
     * @param  'new'|'existing'  $column
     */
    private function count(string $column): int
    {
        return (int) array_sum(array_column($this->rooms(), $column));
    }

    /**
     * A user in several rooms counts once.
     */
    private function pendingUsers(): int
    {
        try {
            return app(TemporaryPasswordService::class)->summary($this->tenant())['new'];
        } catch (Throwable) {
            return $this->count('new');
        }
    }

    private function resetPasswords(?string $sheetKey): void
    {
        try {
            $result = app(TemporaryPasswordService::class)->issue($this->tenant(), $sheetKey);
        } catch (Throwable $e) {
            $this->failed('Resetting passwords failed', $e);

            return;
        }

        Log::info('Temporary passwords set', [
            'tenant' => $this->tenant()->instance_code,
            'room' => $sheetKey ?? 'all',
            'new' => $result['new'],
            'manager' => auth()->id(),
        ]);

        Notification::make()
            ->title('Passwords reset')
            ->body("{$result['new']} ".($result['new'] === 1 ? 'user' : 'users').' got a temporary password.')
            ->success()
            ->send();

        $this->resetTable();
    }

    private function downloadPasswords(?string $sheetKey): ?StreamedResponse
    {
        try {
            $export = app(TemporaryPasswordExport::class);
            $download = $sheetKey === null
                ? $export->all($this->tenant())
                : $export->room($this->tenant(), $sheetKey);
        } catch (Throwable $e) {
            $this->failed('Download failed', $e);

            return null;
        }

        if ($download === null) {
            Notification::make()
                ->title('Nothing to download')
                ->body('No user here holds a temporary password. Reset the passwords first.')
                ->warning()
                ->send();

            return null;
        }

        Log::info('Temporary passwords downloaded', [
            'tenant' => $this->tenant()->instance_code,
            'file' => $download['filename'],
            'manager' => auth()->id(),
        ]);

        $content = $download['content'];

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            $download['filename'],
            ['Content-Type' => $download['mime']],
        );
    }

    private function failed(string $title, Throwable $e): void
    {
        Log::error($title, [
            'tenant' => $this->tenant()->instance_code,
            'exception' => $e,
        ]);

        Notification::make()
            ->title($title)
            ->body('See the log for the error.')
            ->danger()
            ->send();
    }

    private function tenant(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $this->getRecord();

        return $tenant;
    }
}
