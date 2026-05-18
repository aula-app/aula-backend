<?php

declare(strict_types=1);

namespace App\Filament\Resources\SchoolTypeResource\Pages;

use App\Filament\Resources\SchoolTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSchoolType extends EditRecord
{
    protected static string $resource = SchoolTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
