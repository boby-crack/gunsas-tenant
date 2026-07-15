<x-filament-panels::page class="fi-dashboard-page">
    <form
        method="GET"
        action="{{ url()->current() }}"
        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Grup Outlet</span>
                <select
                    name="filters[outlet_group]"
                    onchange="this.form.submit()"
                    class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                >
                    <option value="">Semua Grup</option>
                    @foreach ($this->getOutletGroupOptions() as $value => $label)
                        <option value="{{ $value }}" @selected(($this->filters['outlet_group'] ?? null) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Filter Tampilan Data Cabang</span>
                <select
                    name="filters[outlet_id]"
                    onchange="this.form.submit()"
                    class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                >
                    <option value="">Semua Outlet (Tampilan Global)</option>
                    @foreach ($this->getOutletOptions() as $value => $label)
                        <option value="{{ $value }}" @selected((string) ($this->filters['outlet_id'] ?? '') === (string) $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal Mulai</span>
                <input
                    type="date"
                    name="filters[date_from]"
                    value="{{ $this->filters['date_from'] ?? now()->startOfMonth()->toDateString() }}"
                    onchange="this.form.submit()"
                    class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal Akhir</span>
                <input
                    type="date"
                    name="filters[date_until]"
                    value="{{ $this->filters['date_until'] ?? now()->toDateString() }}"
                    onchange="this.form.submit()"
                    class="mt-2 block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />
            </label>
        </div>
    </form>

    @php
        $widgetData = [
            'filters' => $this->filters,
            'dashboardFilters' => $this->filters,
            ...$this->getWidgetData(),
        ];

        $targetRows = $this->getTargetPerformanceRows();
    @endphp

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="$widgetData"
        :widgets="$this->getKpiWidgets()"
    />

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Kesimpulan Target Sales per Outlet</h2>
        </div>

        @if (count($targetRows) === 0)
            <div class="px-6 py-14 text-center text-sm font-medium text-gray-500 dark:text-gray-400">
                Tidak ada outlet pada filter ini.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Outlet</th>
                            <th class="px-6 py-3 font-semibold">Target</th>
                            <th class="px-6 py-3 font-semibold">Realisasi</th>
                            <th class="px-6 py-3 font-semibold">Pencapaian</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Sisa / Lebih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($targetRows as $row)
                            <tr>
                                <td class="px-6 py-4 font-semibold text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                <td class="px-6 py-4 text-gray-950 dark:text-white">{{ $this->formatCurrency($row['target']) }}</td>
                                <td class="px-6 py-4 text-gray-950 dark:text-white">{{ $this->formatCurrency($row['actual']) }}</td>
                                <td class="px-6 py-4">
                                    @php
                                        $achievementColor = $row['achievement'] >= 100 ? 'bg-green-500/10 text-green-600 dark:text-green-400' : ($row['achievement'] >= 80 ? 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400' : 'bg-red-500/10 text-red-600 dark:text-red-400');
                                    @endphp
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $achievementColor }}">
                                        {{ number_format($row['achievement'], 1, ',', '.') }}%
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ $row['status'] }}</td>
                                <td class="px-6 py-4 font-semibold {{ $row['gap'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $this->formatCurrency($row['gap']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="$widgetData"
        :widgets="$this->getChartWidgets()"
    />
</x-filament-panels::page>
