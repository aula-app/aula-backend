<?php

/* use Illuminate\Http\Request; */
use Illuminate\Support\Facades\Route;

Route::name('internal.')
    ->prefix('/internal/')
    ->group(base_path('routes/api/internal.php'));

Route::name('aula.')
    ->prefix('/api/v2/')
    ->group(base_path('routes/api/aula.php'))
    ->middleware(['auth:api']); // , 'can:is-admin'
/*

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');
Route::middleware(['auth:api', 'can:is-admin'])
    ->prefix('api/v2/admin')
    ->name('admin.')
    ->group(base_path('routes/admin.php'));

Route::controller(RoomController::class)->group(function () {
    Route::get('/users', 'list');
    Route::post('/users', 'add');
    Route::get('/users/{user}', 'get');
    Route::put('/users/{user}', 'update');
    Route::delete('/users/{user}', 'remove');
});

Route::name('admin.')->prefix('admin')->namespace('Admin')->group(function () {
    Route::get('/dashboard', 'DashboardController@index')->name('dashboard');
    Route::get('/users', 'UserController@index')->name('users');
})->middleware(['auth:api']);

Route::prefix('api')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::get('/users', 'UserController@index');
        // Other API V1 routes
    });

    Route::prefix('v2')->group(function () {
        Route::get('/users', 'UserController@indexV2');
        // Other API V2 routes
    });
});
*/
