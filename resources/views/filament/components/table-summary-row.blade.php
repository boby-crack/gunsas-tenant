@php
    $items = $items ?? [];
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); gap: 12px;">
        @foreach ($items as $item)
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $item['value'] }}</p>

                @if (! empty($item['description']))
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['description'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
