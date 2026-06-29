<?php

declare(strict_types=1);

use App\Filament\Resources\TenantResource;

function deriveUsername(?string $email): string
{
    $method = new ReflectionMethod(TenantResource::class, 'deriveUsernameFromEmail');
    $method->setAccessible(true);

    return $method->invoke(null, $email);
}

it('returns empty string for null', function () {
    expect(deriveUsername(null))->toBe('');
});

it('strips the domain part', function () {
    expect(deriveUsername('john.doe@example.com'))->toBe('john.doe');
});

it('treats a value without @ as the whole local part', function () {
    expect(deriveUsername('foo'))->toBe('foo');
});

it('replaces disallowed characters with underscore', function () {
    expect(deriveUsername('a b@example.com'))->toBe('a_b');
});

it('trims leading and trailing dots, underscores and hyphens', function () {
    expect(deriveUsername('.foo.@example.com'))->toBe('foo');
});

it('returns empty string when the local part has only stripped characters', function () {
    expect(deriveUsername('---@example.com'))->toBe('');
});

it('returns empty string when the local part is empty', function () {
    expect(deriveUsername('@example.com'))->toBe('');
});

it('preserves unicode letters', function () {
    expect(deriveUsername('ñoño@example.com'))->toBe('ñoño');
});
