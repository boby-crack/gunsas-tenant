@php
    $simulation = $this->simulationData ?: $this->simulation;

    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $kg = fn ($value) => number_format((float) $value, 3, ',', '.') . ' Kg';
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';
    $period = \Carbon\Carbon::parse($simulation['filters']['date_from'])->format('d M Y') . ' - ' . \Carbon\Carbon::parse($simulation['filters']['date_until'])->format('d M Y');
    $isPriceMode = $simulation['mode'] === 'price_to_target';
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <form wire:submit.prevent="applyFilters" class="space-y-3">
            {{ $this->form }}

            <div class="gunsas-action-row">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="applyFilters"
                    class="gunsas-action-button rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="applyFilters">Terapkan Filter</span>
                    <span wire:loading wire:target="applyFilters">Menerapkan...</span>
                </button>
            </div>
        </form>

        @if ($simulation['warnings'] !== [])
            <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950/40 dark:text-warning-200">
                <p class="font-semibold">Catatan Simulasi</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($simulation['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Price Simulator</p>
                    <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $isPriceMode ? 'Target Omzet dari Harga Input' : 'Saran Harga dari Target Margin' }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        Basis histori {{ $period }}, forecast {{ $simulation['filters']['forecast_days'] }} hari.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-lg bg-gray-50 px-3 py-2 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        Target margin <span class="font-semibold text-gray-950 dark:text-white">{{ $percent($simulation['filters']['target_margin_percent']) }}</span>
                    </span>
                    <span class="rounded-lg bg-gray-50 px-3 py-2 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        Bagian Gunsas <span class="font-semibold text-gray-950 dark:text-white">{{ $percent($simulation['filters']['gunsas_share_percent']) }}</span>
                    </span>
                    <span class="rounded-lg bg-gray-50 px-3 py-2 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        Cadangan diskon/return <span class="font-semibold text-gray-950 dark:text-white">{{ $percent($simulation['filters']['adjustment_percent']) }}</span>
                    </span>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-4">
            <div class="rounded-xl border border-l-4 border-gray-200 border-l-warning-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Omzet Kasir Target</p>
                <p class="mt-2 text-3xl font-bold text-gray-950 dark:text-white">{{ $money($simulation['recommended']['required_cashier_sales']) }}</p>
                <p class="mt-1 text-sm text-gray-500">Sebelum diskon/return</p>
            </div>

            <div class="rounded-xl border border-l-4 border-gray-200 border-l-primary-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Omzet Net Target</p>
                <p class="mt-2 text-3xl font-bold text-primary-600">{{ $money($simulation['recommended']['required_net_sales']) }}</p>
                <p class="mt-1 text-sm text-gray-500">Setelah diskon/return</p>
            </div>

            <div class="rounded-xl border border-l-4 border-gray-200 border-l-info-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Total KG Target</p>
                <p class="mt-2 text-3xl font-bold text-gray-950 dark:text-white">{{ $kg($simulation['recommended']['forecast_kg']) }}</p>
                <p class="mt-1 text-sm text-gray-500">Dibagi mengikuti histori</p>
            </div>

            <div class="rounded-xl border border-l-4 border-gray-200 border-l-success-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Profit & Margin</p>
                <p class="mt-2 text-3xl font-bold {{ $simulation['recommended']['net_profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $money($simulation['recommended']['net_profit']) }}
                </p>
                <p class="mt-1 text-sm text-gray-500">Margin {{ $percent($simulation['recommended']['net_margin']) }}</p>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-1 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ $isPriceMode ? 'Breakdown Target dari Harga Input' : 'Rekomendasi Harga per Produk' }}
                    </h3>
                    <p class="text-sm text-gray-500">
                        {{ $isPriceMode
                            ? 'KG target dihitung dari harga yang kamu isi dan komposisi penjualan historis.'
                            : 'Harga kosong dihitung otomatis agar target margin tercapai pada forecast ini.' }}
                    </p>
                </div>

                <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    Overhead forecast: <span class="font-semibold text-gray-950 dark:text-white">{{ $money($simulation['costs']['forecast_overhead']) }}</span>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm" style="min-width: 980px;">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950">
                            <th class="px-4 py-3 text-left font-medium">Produk</th>
                            <th class="px-4 py-3 text-left font-medium">Porsi Histori</th>
                            <th class="px-4 py-3 text-right font-medium">Target KG</th>
                            <th class="px-4 py-3 text-right font-medium">Omzet Kasir</th>
                            <th class="px-4 py-3 text-right font-medium">Omzet Net</th>
                            <th class="px-4 py-3 text-right font-medium">Harga Kasir/Kg</th>
                            <th class="px-4 py-3 text-right font-medium">Modal/Kg</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($simulation['product_breakdown'] as $product)
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="px-4 py-4 align-middle">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $product['label'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">Histori {{ $kg($product['historical_kg']) }}</p>
                                </td>

                                <td class="px-4 py-4 align-middle">
                                    <div class="flex min-w-36 items-center gap-3">
                                        <div class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="h-2 rounded-full bg-primary-500" style="width: {{ min(100, max(0, $product['mix_percent'])) }}%"></div>
                                        </div>
                                        <span class="w-16 text-right font-bold text-primary-600 dark:text-primary-400">
                                            {{ $percent($product['mix_percent']) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-4 text-right align-middle font-bold text-gray-950 dark:text-white">
                                    {{ $kg($product['target_kg']) }}
                                </td>

                                <td class="px-4 py-4 text-right align-middle font-bold text-gray-950 dark:text-white">
                                    {{ $money($product['target_cashier_sales']) }}
                                </td>

                                <td class="px-4 py-4 text-right align-middle font-semibold text-gray-700 dark:text-gray-200">
                                    {{ $money($product['target_net_sales']) }}
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <p class="font-bold text-gray-950 dark:text-white">{{ $money($product['cashier_price_per_kg']) }}</p>
                                    <p class="text-xs {{ $product['is_price_from_user'] ? 'text-success-600 dark:text-success-400' : 'text-gray-500' }}">
                                        {{ $product['is_price_from_user'] ? 'Input' : 'Saran' }}
                                    </p>
                                </td>

                                <td class="px-4 py-4 text-right align-middle font-semibold text-gray-700 dark:text-gray-200">
                                    {{ $money($product['unit_modal']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Breakdown Biaya Forecast</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">HPP target</dt>
                        <dd class="font-semibold">{{ $money($simulation['costs']['hpp_forecast']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Overhead forecast</dt>
                        <dd class="font-semibold">{{ $money($simulation['costs']['forecast_overhead']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Overhead per Kg</dt>
                        <dd class="font-semibold">{{ $money($simulation['costs']['overhead_per_kg']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-gray-200 pt-3 dark:border-gray-800">
                        <dt class="font-medium">Total biaya</dt>
                        <dd class="font-bold">{{ $money($simulation['costs']['total_cost']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Pendapatan Gunsas target</dt>
                        <dd class="font-semibold text-info-600">{{ $money($simulation['recommended']['required_gunsas_revenue']) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Cara Membaca</h3>
                <div class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    @if ($isPriceMode)
                        <p>
                            Karena ada harga yang diisi, simulator memakai harga itu untuk menghitung berapa omzet dan KG yang dibutuhkan agar margin target tercapai.
                        </p>
                        <p>
                            Jika salah satu harga produk kosong, produk itu otomatis memakai harga saran sistem supaya simulasi tetap lengkap.
                        </p>
                    @else
                        <p>
                            Karena harga dikosongkan, simulator menghitung harga kasir per produk dari modal, overhead forecast, bagi hasil outlet, cadangan diskon/return, dan target margin.
                        </p>
                        <p>
                            KG tiap produk mengikuti rata-rata histori selama periode basis data, lalu diproyeksikan ke jumlah hari forecast.
                        </p>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
