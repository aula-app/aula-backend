<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolTypeResource\Pages;
use App\Models\SchoolType;
use Filament\Forms\Components\TextInput;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @extends Resource<SchoolType, ResourceConfiguration>
 */
final class SchoolTypeResource extends Resource
{
    /** @var class-string<SchoolType>|null */
    protected static ?string $model = SchoolType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'School Types';

    protected static ?string $modelLabel = 'School Type';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
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

                TextColumn::make('tenants_count')
                    ->label('Tenants')
                    ->counts('tenants')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolTypes::route('/'),
            'create' => Pages\CreateSchoolType::route('/create'),
            'edit' => Pages\EditSchoolType::route('/{record}/edit'),
        ];
    }
}
