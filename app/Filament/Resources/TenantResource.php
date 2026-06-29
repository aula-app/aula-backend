<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\SchoolType;
use App\Models\Tenant;
use App\Services\TenantsService;
use Filament\Actions\Action as FormAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput\Actions\CopyAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\ResourceConfiguration;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set as FilamentSet;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @extends Resource<Tenant,ResourceConfiguration>
 */
class TenantResource extends Resource
{
    /**
     * @var ?class-string<Tenant>
     */
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'Tenant';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $isCreate = $schema->getOperation() === 'create';

        return $schema->components([
            Section::make('Basic Information')
                ->schema([
                    Hidden::make('admin1_username_manual')->default(fn (mixed $state, callable $set, Get $get) => ! empty($get('admin1_username')))->reactive(),
                    Hidden::make('admin2_username_manual')->default(fn (mixed $state, callable $set, Get $get) => ! empty($get('admin2_username')))->reactive(),

                    TextInput::make('name')
                        ->label('School / Organisation Name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('instance_code')
                        ->label('Instance Code')
                        ->when(
                            $isCreate,
                            fn (TextInput $field) => $field
                                ->disabled()
                                ->dehydrated(true)
                                ->helperText('Auto-generated. Click ↺ to get a new one.')
                                ->suffixAction(
                                    FormAction::make('regenerate')
                                        ->icon('heroicon-o-arrow-path')
                                        ->tooltip('Generate a new instance code')
                                        ->action(fn (FilamentSet $set): mixed => $set(
                                            'instance_code',
                                            app(TenantsService::class)->generateUniqueInstanceCode()
                                        ))
                                ),
                            fn (TextInput $field) => $field
                                ->disabled()
                                ->dehydrated(false)
                        ),

                    TextInput::make('api_base_url')
                        ->label('API Base URL')
                        ->default(config('app.url'))
                        ->placeholder(config('app.url'))
                        ->url()
                        ->maxLength(255),

                    Textarea::make('contact_info')
                        ->label('Contact Info')
                        ->rows(3)
                        ->maxLength(1000),

                    Select::make('school_type_id')
                        ->label('Type of School')
                        ->options(fn () => SchoolType::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                ]),

            Section::make('Primary Admin')
                ->schema([
                    TextInput::make('admin1_name')
                        ->label('Full Name')
                        ->maxLength(255),

                    TextInput::make('admin1_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                            if ($get('admin1_username_manual') === false || empty($get('admin1_username'))) {
                                $derived = TenantResource::deriveUsernameFromEmail($state);
                                if ($derived !== '') {
                                    $set('admin1_username', $derived);
                                    $set('admin1_username_manual', false);
                                }
                            }
                        })
                        ->maxLength(255),

                    TextInput::make('admin1_username')
                        ->label('Username')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (?string $state, callable $set): void {
                            $set('admin1_username_manual', true);
                        })
                        ->maxLength(255),

                    TextInput::make('admin1_init_pass_url')
                        ->label('Password Setup URL')
                        ->disabled()
                        ->dehydrated(false)
                        ->suffixAction(CopyAction::make())
                        ->visibleOn('edit'),
                ]),

            Section::make('Secondary Admin')
                ->schema([
                    TextInput::make('admin2_name')
                        ->label('Full Name')
                        ->maxLength(255),

                    TextInput::make('admin2_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                            if ($get('admin2_username_manual') === false || empty($get('admin2_username'))) {
                                $derived = TenantResource::deriveUsernameFromEmail($state);
                                if ($derived !== '') {
                                    $set('admin2_username', $derived);
                                    $set('admin2_username_manual', false);
                                }
                            }
                        })
                        ->maxLength(255),

                    TextInput::make('admin2_username')
                        ->label('Username')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (?string $state, callable $set): void {
                            $set('admin2_username_manual', true);
                        })
                        ->maxLength(255),

                    TextInput::make('admin2_init_pass_url')
                        ->label('Password Setup URL')
                        ->disabled()
                        ->dehydrated(false)
                        ->suffixAction(CopyAction::make())
                        ->visibleOn('edit'),
                ]),

            Section::make('Single Sign-On')
                ->description('OIDC/Keycloak integration. Leave SSO disabled to keep this tenant on legacy username+password login only.')
                ->collapsed(fn (?Tenant $record) => !($record?->sso_enabled))
                ->schema([
                    Toggle::make('sso_enabled')
                        ->label('SSO enabled')
                        ->helperText('Show the SSO login button on the login page.')
                        ->default(false),

                    TextInput::make('sso_provider')
                        ->label('IdP alias in Keycloak')
                        ->helperText('Optional. The Keycloak identity-provider alias for this school (used as kc_idp_hint). Leave empty to land on the realm login page.')
                        ->maxLength(64),

                    Toggle::make('sso_force_logout')
                        ->label('End Keycloak session on logout')
                        ->helperText('When on, clicking Logout in aula also ends the Keycloak session (RP-initiated logout).')
                        ->default(true),

                    Toggle::make('sso_required')
                        ->label('SSO required (no password login)')
                        ->helperText('When on, refuse legacy username+password login for everyone in this tenant. Only flip on AFTER all users have completed account linking — while on, the link flow itself is unreachable.')
                        ->default(false),

                    Toggle::make('sso_require_email_verified')
                        ->label('Require verified email from IdP')
                        ->helperText('When on (default), reject SSO logins whose id_token does not assert email_verified=true. Turn off only when the IdP is trusted to control all email addresses (e.g., school-issued addresses with no self-registration).')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('instance_code')
                    ->label('Instance Code')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('api_base_url')
                    ->label('API URL')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('admin1_email')
                    ->label('Primary Admin Email')
                    ->searchable(),

                TextColumn::make('admin2_email')
                    ->label('Secondary Admin Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('sso_enabled')
                    ->label('SSO')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('sso_required')
                    ->label('SSO only')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    private static function deriveUsernameFromEmail(?string $email): string
    {
        if ($email === null) {
            return '';
        }
        $part = ($pos = strpos($email, '@')) !== false ? substr($email, 0, $pos) : $email;
        // normalize to NFC (skip if normalization fails on malformed input)
        if (function_exists('normalizer_normalize')) {
            $normalized = normalizer_normalize($part, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $part = $normalized;
            }
        }
        // allow Unicode letters, numbers, dot, underscore, hyphen
        $username = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $part);
        if (!is_string($username)) {
            $username = '';
        }
        return trim($username, '._-');
    }
}
