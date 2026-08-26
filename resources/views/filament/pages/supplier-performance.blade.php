@php
    $performance = $this->performanceData ?: $this->performance;
    $summary = $performance['summary'];
    $rows = $performance['rows'];

    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $kg = fn ($value) => number_format((float) $value, 3, ',', '.') . ' Kg';
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';

    $statusClass = function (string $status): string {
        return match ($status) {
            'Bagus' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400',
            'Perlu Dipantau' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400',
            default => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400',
        };
    };
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

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Supplier Performance</p>
                    <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $performance['filters']['period_label'] }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{ $performance['filters']['varian_label'] }}. Skor dihitung dari retur, loss, penerimaan supplier, dan refund.
                    </p>
                </div>

                <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    Supplier terbaca:
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $summary['supplier_count'] }}</span>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-xl border border-l-4 border-gray-200 border-l-info-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Pembelian</p>
                <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $kg($summary['purchase_kg']) }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $money($summary['purchase_amount']) }}</p>
            </div>

            <div class="rounded-xl border border-l-4 border-gray-200 border-l-primary-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Modal Avg</p>
                <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $money($summary['avg_price_per_kg']) }}</p>
                <p class="mt-1 text-sm text-gray-500">per Kg pembelian</p>
            </div>

            <div class="rounded-xl border border-l-4 border-gray-200 border-l-warning-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Retur Diajukan</p>
                <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $kg($summary['submitted_kg']) }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $percent($summary['return_rate']) }} dari pembelian</p>
            </div>

            <div class="rounded-xl border border-l-4 border-gray-200 border-l-success-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Refund Supplier</p>
                <p class="mt-2 text-2xl font-bold text-success-600">{{ $money($summary['refund']) }}</p>
                <p class="mt-1 text-sm text-gray-500">refund final</p>
            </div>

            <div class="rounded-xl border border-l-4 border-gray-200 border-l-danger-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Loss Final</p>
                <p class="mt-2 text-2xl font-bold {{ $summary['loss_final'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                    {{ $money($summary['loss_final']) }}
                </p>
                <p class="mt-1 text-sm text-gray-500">{{ $percent($summary['loss_rate']) }} dari pembelian</p>
            </div>

            <div class="rounded-xl border border-l-4 border-gray-200 border-l-gray-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Rasio Retur</p>
                <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $percent($summary['return_rate']) }}</p>
                <p class="mt-1 text-sm text-gray-500">kg retur / kg beli</p>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Supplier Terbaik</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($performance['best_suppliers'] as $supplier)
                        <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-3 py-3 dark:bg-gray-800">
                            <div>
                                <p class="font-semibold text-gray-950 dark:text-white">{{ $supplier['supplier_code'] }}</p>
                                <p class="text-sm text-gray-500">{{ $supplier['supplier_name'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-success-600">{{ number_format($supplier['score'], 1, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">return {{ $percent($supplier['return_rate']) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada data pembelian pada filter ini.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Risiko Terbesar</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($performance['risk_suppliers'] as $supplier)
                        <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-3 py-3 dark:bg-gray-800">
                            <div>
                                <p class="font-semibold text-gray-950 dark:text-white">{{ $supplier['supplier_code'] }}</p>
                                <p class="text-sm text-gray-500">{{ $kg($supplier['final_kg']) }} retur final</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold {{ $supplier['loss_final'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                    {{ $money($supplier['loss_final']) }}
                                </p>
                                <p class="text-xs text-gray-500">refund {{ $money($supplier['refund']) }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada retur pada filter ini.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-1 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Detail Per Supplier</h3>
                    <p class="text-sm text-gray-500">Loss final dihitung dari estimasi modal retur final dikurangi refund supplier.</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm" style="min-width: 1160px;">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950">
                            <th class="px-4 py-3 text-left font-medium">Supplier</th>
                            <th class="px-4 py-3 text-right font-medium">Pembelian</th>
                            <th class="px-4 py-3 text-right font-medium">Avg Modal</th>
                            <th class="px-4 py-3 text-right font-medium">Retur</th>
                            <th class="px-4 py-3 text-right font-medium">Diterima</th>
                            <th class="px-4 py-3 text-right font-medium">Ditolak</th>
                            <th class="px-4 py-3 text-right font-medium">Refund</th>
                            <th class="px-4 py-3 text-right font-medium">Loss</th>
                            <th class="px-4 py-3 text-right font-medium">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="px-4 py-4 align-middle">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $row['supplier_code'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $row['supplier_name'] }}</p>
                                    <span class="mt-2 inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusClass($row['status']) }}">
                                        {{ $row['status'] }}
                                    </span>
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <p class="font-bold text-gray-950 dark:text-white">{{ $kg($row['purchase_kg']) }}</p>
                                    <p class="text-xs text-gray-500">{{ $money($row['purchase_amount']) }}</p>
                                    <p class="text-xs text-gray-500">{{ number_format($row['purchase_butir'], 0, ',', '.') }} butir</p>
                                </td>

                                <td class="px-4 py-4 text-right align-middle font-semibold text-gray-950 dark:text-white">
                                    {{ $money($row['avg_price_per_kg']) }}
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <p class="font-bold text-warning-600">{{ $kg($row['submitted_kg']) }}</p>
                                    <p class="text-xs text-gray-500">{{ $percent($row['return_rate']) }} dari beli</p>
                                    <p class="text-xs text-gray-500">{{ $row['return_records'] }} retur</p>
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <p class="font-bold text-success-600">{{ $kg($row['accepted_kg']) }}</p>
                                    <p class="text-xs text-gray-500">{{ $percent($row['accepted_rate']) }}</p>
                                </td>

                                <td class="px-4 py-4 text-right align-middle font-semibold {{ $row['rejected_kg'] > 0 ? 'text-danger-600' : 'text-gray-500' }}">
                                    {{ $kg($row['rejected_kg']) }}
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <p class="font-bold text-success-600">{{ $money($row['refund']) }}</p>
                                    <p class="text-xs text-gray-500">cover {{ $percent($row['refund_coverage']) }}</p>
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <p class="font-bold {{ $row['loss_final'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                        {{ $money($row['loss_final']) }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $percent($row['loss_rate']) }}</p>
                                </td>

                                <td class="px-4 py-4 text-right align-middle">
                                    <p class="text-lg font-bold text-gray-950 dark:text-white">{{ number_format($row['score'], 1, ',', '.') }}</p>
                                    <p class="text-xs text-gray-500">/ 100</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500">
                                    Belum ada data supplier pada filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
