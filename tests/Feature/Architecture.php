<?php

namespace Tests\Feature;

arch()->preset()->laravel();
arch()->preset()->security()->ignoring('md5');

arch('globals')
    ->expect('App')
    ->toUseStrictTypes()
    ->not->toUse(['dd', 'dump', 'die']);

arch()
    ->expect('App\Models')
    ->toBeClasses()
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->toOnlyBeUsedIn([
        'App\UseCases',
        'App\Models',
        'App\Services',
        # internal stuff
        'App\Filament',
        'App\Console\Commands',
        'Database\Seeders',
        # TODO: the following uses should be fixed
        # auth related stuff (should go through services/use-cases)
        'App\Auth',
        'App\Http\Controllers\Auth',
        'App\Http\Controllers\Idp',
        # jobs (should go through services/use-cases)
        'App\Jobs',
    ])
    ->ignoring('App\Models\LegacyUser')
    ->ignoring('App\Models\Manager\AulaManagerUser')
    ->ignoring('App\Models\Manager\CentralClient');

arch()
    ->expect('App\Http')
    ->toOnlyBeUsedIn('App\Http');
