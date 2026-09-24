<?php

declare(strict_types=1);

use App\Models\Manager\AulaManagerUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('creates a manager user with a valid email and password', function () {
    $this->artisan('manager:create-user')
        ->expectsQuestion('Name', 'Alice')
        ->expectsQuestion('Email', 'alice@example.com')
        ->expectsQuestion('Password (min 8 characters)', 'supersecret')
        ->assertSuccessful();

    expect(AulaManagerUser::firstWhere('email', 'alice@example.com'))->not->toBeNull();
});

it('rejects an invalid email then accepts a valid one', function () {
    $this->artisan('manager:create-user')
        ->expectsQuestion('Name', 'Bob')
        ->expectsQuestion('Email', 'not-an-email')
        ->expectsOutput('Email should be valid.')
        ->expectsQuestion('Email', 'bob@example.com')
        ->expectsQuestion('Password (min 8 characters)', 'supersecret')
        ->assertSuccessful();
});

it('warns about a duplicate email without showing the invalid-email warning', function () {
    AulaManagerUser::create([
        'name'     => 'Existing',
        'email'    => 'dup@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->artisan('manager:create-user')
        ->expectsQuestion('Name', 'Carol')
        ->expectsQuestion('Email', 'dup@example.com')
        ->expectsOutput('A user with that email already exists.')
        ->doesntExpectOutput('Email should be valid.')
        ->expectsQuestion('Email', 'carol@example.com')
        ->expectsQuestion('Password (min 8 characters)', 'supersecret')
        ->assertSuccessful();
});

it('rejects a short password then accepts a valid one', function () {
    $this->artisan('manager:create-user')
        ->expectsQuestion('Name', 'Dave')
        ->expectsQuestion('Email', 'dave@example.com')
        ->expectsQuestion('Password (min 8 characters)', 'short')
        ->expectsOutput('Password must be at least 8 characters.')
        ->expectsQuestion('Password (min 8 characters)', 'supersecret')
        ->assertSuccessful();
});
