<?php

declare(strict_types=1);

namespace App\Filament\Resources\SchoolTypeResource\Pages;

use App\Filament\Resources\SchoolTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSchoolType extends CreateRecord
{
    protected static string $resource = SchoolTypeResource::class;
}
