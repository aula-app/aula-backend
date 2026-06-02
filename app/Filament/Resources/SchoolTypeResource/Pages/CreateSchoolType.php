<?php

declare(strict_types=1);

namespace App\Filament\Resources\SchoolTypeResource\Pages;

use App\Filament\Resources\SchoolTypeResource;
use App\Models\SchoolType;
use Filament\Resources\Pages\CreateRecord;

/**
 * @extends CreateRecord<SchoolType>
 */
final class CreateSchoolType extends CreateRecord
{
    protected static string $resource = SchoolTypeResource::class;
}
