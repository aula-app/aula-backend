<?php

use Illuminate\Support\Facades\Route;

Route::get('/users/', function () {
    return response()->json(['users' => [['name' => 'The Arm']]]);
});
