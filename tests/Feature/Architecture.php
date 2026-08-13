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
    ->toOnlyBeUsedIn('App\UseCases')
    ->ignoring('App\Models\LegacyUser')
    ->ignoring('App\Models\Manager\AulaManagerUser')
    ->ignoring('App\Models\Manager\CentralClient');

arch()
    ->expect('App\Http')
    ->toOnlyBeUsedIn('App\Http');
