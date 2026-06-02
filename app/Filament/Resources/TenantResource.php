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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set as FilamentSet;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\EditAction;
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
                                $set('admin1_username', TenantResource::deriveUsernameFromEmail($state, $get('admin1_username')));
                                $set('admin1_username_manual', false);
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
                                $set('admin2_username', TenantResource::deriveUsernameFromEmail($state, $get('admin2_username')));
                                $set('admin2_username_manual', false);
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

    /**
     * @psalm-pure
     */
    private static function deriveUsernameFromEmail(?string $email, ?string $current): string
    {
        if ($email === null) {
            return '';
        }
        $part = ($pos = strpos($email, '@')) !== false ? substr($email, 0, $pos) : $email;
        // normalize to NFC
        if (function_exists('normalizer_normalize')) {
            $part = strval(normalizer_normalize($part, \Normalizer::FORM_C));
        }
        // allow Unicode letters, numbers, dot, underscore, hyphen
        $username = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $part);
        if (!is_string($username)) {
            $username = '';
        }
        return trim($username, '._-');
    }
}
