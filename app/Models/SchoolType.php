<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolType extends Model
{
    protected $fillable = ['name'];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
