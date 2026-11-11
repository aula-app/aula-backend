<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Tenant;
use App\Services\TemporaryPasswordPdf;
use App\Services\TemporaryPasswordService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Issues temporary passwords for a tenant and downloads them as a PDF.
 *
 * The PDF is streamed, never stored: it holds passwords in plain text.
 */
class TemporaryPasswordsAction
{
    public const string NAME = 'temporaryPasswords';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Temporary passwords')
            ->icon('heroicon-o-key')
            ->color('warning')
            // Password login is refused for an SSO-only tenant.
            ->visible(fn (Tenant $record): bool => ! $record->sso_required)
            ->requiresConfirmation()
            ->modalHeading(fn (Tenant $record): string => "Temporary passwords for {$record->name}")
            ->modalDescription(fn (Tenant $record): string => self::describe($record))
            ->modalSubmitActionLabel('Issue and download PDF')
            ->action(fn (Tenant $record): ?StreamedResponse => self::run($record));
    }

    public static function describe(Tenant $tenant): string
    {
        try {
            $summary = app(TemporaryPasswordService::class)->summary($tenant);
        } catch (Throwable $e) {
            Log::error('Counting users for temporary passwords failed', [
                'tenant' => $tenant->instance_code,
                'exception' => $e,
            ]);

            return 'The users of this tenant could not be read. See the log.';
        }

        return "{$summary['new']} active users have no password of their own and get a new temporary password. "
            ."{$summary['existing']} already hold a temporary password and keep it. "
            .'Users who chose a password, moderators, admins and SSO accounts are not touched. '
            .'The PDF lists the passwords in plain text.';
    }

    public static function run(Tenant $tenant): ?StreamedResponse
    {
        try {
            $service = app(TemporaryPasswordService::class);
            $result = $service->issue($tenant);
            $sheets = $service->handoutSheets($result['sheets']);

            if ($sheets === []) {
                Notification::make()
                    ->title('Nothing to do')
                    ->body('Every active user of this tenant has already chosen a password.')
                    ->warning()
                    ->send();

                return null;
            }

            $pdf = app(TemporaryPasswordPdf::class)->render($tenant, $sheets);
        } catch (Throwable $e) {
            Log::error('Issuing temporary passwords failed', [
                'tenant' => $tenant->instance_code,
                'exception' => $e,
            ]);

            Notification::make()
                ->title('Temporary passwords failed')
                ->body('Running the action again lists the passwords already issued. See the log for the error.')
                ->danger()
                ->send();

            return null;
        }

        Log::info('Temporary passwords issued', [
            'tenant' => $tenant->instance_code,
            'new' => $result['new'],
            'existing' => $result['existing'],
            'manager' => auth()->id(),
        ]);

        Notification::make()
            ->title('Temporary passwords issued')
            ->body("{$result['new']} new, {$result['existing']} kept.")
            ->success()
            ->send();

        $filename = 'temp-passwords-'.Str::slug((string) $tenant->instance_code).'-'.now()->format('Y-m-d_His').'.pdf';

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf;
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
