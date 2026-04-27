<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Services\TenantsService;
use Filament\Actions\Action as FormAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Set;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantResource extends Resource
{
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
                                        ->action(fn (Set $set) => $set(
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
                        ->url()
                        ->maxLength(255),

                    Textarea::make('contact_info')
                        ->label('Contact Info')
                        ->rows(3)
                        ->maxLength(1000),

                    TextInput::make('school_type')
                        ->label('Type of School')
                        ->maxLength(255),
                ]),

            Section::make('Primary Admin')
                ->schema([
                    TextInput::make('admin1_name')
                        ->label('Full Name')
                        ->maxLength(255),

                    TextInput::make('admin1_username')
                        ->label('Username')
                        ->required()
                        ->maxLength(255)
                        ->when(!$isCreate, fn (TextInput $f) => $f->disabled()->dehydrated(false)),

                    TextInput::make('admin1_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->when(!$isCreate, fn (TextInput $f) => $f->disabled()->dehydrated(false)),

                    TextInput::make('admin1_init_pass_url')
                        ->label('Password Setup URL')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                ]),

            Section::make('Secondary Admin')
                ->schema([
                    TextInput::make('admin2_name')
                        ->label('Full Name')
                        ->maxLength(255),

                    TextInput::make('admin2_username')
                        ->label('Username')
                        ->required()
                        ->maxLength(255)
                        ->when(!$isCreate, fn (TextInput $f) => $f->disabled()->dehydrated(false)),

                    TextInput::make('admin2_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->when(!$isCreate, fn (TextInput $f) => $f->disabled()->dehydrated(false)),

                    TextInput::make('admin2_init_pass_url')
                        ->label('Password Setup URL')
                        ->disabled()
                        ->dehydrated(false)
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
}
