<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        :name="$title"
        details="past {{ $this->periodForHumans() }}"
    />

    <x-pulse::scroll :expand="$expand" wire:poll.visible.30s="">
        @if ($entries->isEmpty())
            <x-pulse::no-results />
        @else
            <div class="space-y-3">
                @foreach ($entries as $row)
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-gray-900 dark:text-gray-100">{{ $row->label }}</div>
                            @if ($row->detail)
                                <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $row->detail }}</div>
                            @endif
                        </div>
                        <div class="shrink-0 text-right tabular-nums">
                            <div class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ number_format($row->sum ?: $row->count) }}
                            </div>
                            @if ($row->avg !== null)
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format($row->avg, 1) }}ms avg
                                    @if ($row->max)
                                        / {{ number_format($row->max) }}ms max
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
