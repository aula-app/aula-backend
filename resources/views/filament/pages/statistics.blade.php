<x-filament-panels::page>

    @if ($statistics !== null)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Results
                        <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                            {{ count($statistics) }} {{ Str::plural('tenant', count($statistics)) }} &middot; generated at {{ $statistics[0]['generated_at'] ?? '' }}
                        </span>
                    </h3>
                </div>

                <x-filament::button
                    wire:click="downloadPreview"
                    icon="heroicon-o-arrow-down-tray"
                    color="primary"
                    size="sm"
                >
                    Download CSV
                </x-filament::button>
            </div>

            <div class="fi-ta-ctn divide-y divide-gray-200 overflow-x-auto dark:divide-white/10">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                    <thead class="divide-y divide-gray-200 dark:divide-white/5">
                        <tr>
                            @foreach ([
                                'Instance Name', 'Instance Code', 'Users',
                                'Votes', 'Likes', 'Ideas', 'Comments',
                            ] as $heading)
                                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($statistics as $row)
                            <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                    <div class="fi-ta-col-wrp px-3 py-4">
                                        <span class="text-sm text-gray-950 dark:text-white font-medium">{{ $row['instance_name'] }}</span>
                                    </div>
                                </td>
                                <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                    <div class="fi-ta-col-wrp px-3 py-4">
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $row['instance_code'] }}</span>
                                    </div>
                                </td>
                                @foreach (['users_count', 'votes_count', 'likes_count', 'ideas_count', 'comments_count'] as $key)
                                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                        <div class="fi-ta-col-wrp px-3 py-4">
                                            @if ($row[$key] === null)
                                                <span class="text-xs text-danger-500">N/A</span>
                                            @else
                                                <span class="text-sm tabular-nums text-gray-950 dark:text-white">{{ number_format($row[$key]) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Use <strong>Generate &amp; Preview</strong> to load statistics for all tenants in the table, or <strong>Generate &amp; Download CSV</strong> to skip straight to the file.
            </p>
        </x-filament::section>
    @endif

</x-filament-panels::page>
