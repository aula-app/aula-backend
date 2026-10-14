<?php

declare(strict_types=1);

use App\Services\TenantStatisticsService;

$sampleRow = fn (array $overrides = []) => array_merge([
    'instance_name' => 'Test School',
    'instance_code' => 'test1',
    'generated_at' => '2026-01-01 12:00:00',
    'users_count' => 42,
    'votes_count' => 10,
    'likes_count' => 5,
    'ideas_count' => 3,
    'comments_count' => 7,
], $overrides);

it('generates csv with correct headers in order', function () {
    $csv = (new TenantStatisticsService)->generateCsv([]);

    $headers = str_getcsv(explode("\n", trim($csv))[0], ',', '"', '\\');

    expect($headers)->toBe([
        'Instance Name',
        'Instance Code',
        'Generated Date',
        'Total Users',
        'Total Votes',
        'Total Likes',
        'Total Ideas',
        'Total Comments',
    ]);
});

it('produces only a header row when no stats are provided', function () {
    $csv = (new TenantStatisticsService)->generateCsv([]);

    $lines = array_values(array_filter(explode("\n", $csv)));

    expect($lines)->toHaveCount(1);
});

it('generates one data row per tenant', function () use ($sampleRow) {
    $csv = (new TenantStatisticsService)->generateCsv([$sampleRow(), $sampleRow(['instance_code' => 'other1'])]);

    $lines = array_values(array_filter(explode("\n", $csv)));

    expect($lines)->toHaveCount(3); // header + 2 rows
});

it('maps tenant data to the correct csv columns', function () use ($sampleRow) {
    $csv = (new TenantStatisticsService)->generateCsv([$sampleRow()]);

    $lines = array_values(array_filter(explode("\n", $csv)));
    $row = str_getcsv($lines[1], ',', '"', '\\');

    expect($row[0])->toBe('Test School')
        ->and($row[1])->toBe('test1')
        ->and($row[2])->toBe('2026-01-01 12:00:00')
        ->and($row[3])->toBe('42')
        ->and($row[4])->toBe('10')
        ->and($row[5])->toBe('5')
        ->and($row[6])->toBe('3')
        ->and($row[7])->toBe('7');
});

it('renders null counts as empty strings in csv', function () use ($sampleRow) {
    $csv = (new TenantStatisticsService)->generateCsv([$sampleRow([
        'users_count' => null,
        'votes_count' => null,
        'likes_count' => null,
        'ideas_count' => null,
        'comments_count' => null,
    ])]);

    $lines = array_values(array_filter(explode("\n", $csv)));
    $row = str_getcsv($lines[1], ',', '"', '\\');

    expect($row[3])->toBe('')
        ->and($row[4])->toBe('')
        ->and($row[5])->toBe('')
        ->and($row[6])->toBe('')
        ->and($row[7])->toBe('');
});

it('preserves instance name and code even when all counts are null', function () use ($sampleRow) {
    $csv = (new TenantStatisticsService)->generateCsv([$sampleRow([
        'instance_name' => 'Broken School',
        'instance_code' => 'brkn1',
        'users_count' => null,
        'votes_count' => null,
        'likes_count' => null,
        'ideas_count' => null,
        'comments_count' => null,
    ])]);

    $lines = array_values(array_filter(explode("\n", $csv)));
    $row = str_getcsv($lines[1], ',', '"', '\\');

    expect($row[0])->toBe('Broken School')
        ->and($row[1])->toBe('brkn1');
});
