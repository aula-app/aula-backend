<x-filament-panels::page>
    @php($rooms = $this->rooms())

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header px-6 py-4">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Rooms
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                    {{ count($rooms) }} with users who never chose a password
                </span>
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Downloading a room gives its users without a password a temporary one. Users who already hold a temporary
                password keep it. Users who chose a password, moderators, admins and SSO accounts are never listed.
                The files hold passwords in plain text.
            </p>
        </div>

        @if ($rooms === [])
            <div class="px-6 py-8 text-sm text-gray-500 dark:text-gray-400">
                Every active user of this tenant has already chosen a password.
            </div>
        @else
            <div class="fi-ta-ctn divide-y divide-gray-200 overflow-x-auto dark:divide-white/10">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                    <thead>
                        <tr>
                            @foreach (['Room', 'Get a new password', 'Already hold one', ''] as $heading)
                                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($rooms as $room)
                            <tr wire:key="room-{{ $room['key'] }}" class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-3 py-4 sm:first-of-type:ps-6 text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $room['room'] }}
                                </td>
                                <td class="px-3 py-4 text-sm tabular-nums text-gray-950 dark:text-white">{{ $room['new'] }}</td>
                                <td class="px-3 py-4 text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ $room['existing'] }}</td>
                                <td class="px-3 py-4 sm:last-of-type:pe-6 text-end">
                                    <x-filament::button
                                        wire:click="downloadRoom(@js($room['key']))"
                                        :wire:confirm="$room['new'] > 0 ? $this->confirmation($room['new']) : null"
                                        wire:loading.attr="disabled"
                                        icon="heroicon-o-arrow-down-tray"
                                        color="gray"
                                        size="sm"
                                    >
                                        PDF
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
