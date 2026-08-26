@php
    $insights = $this->insightsData ?: $this->insights;

    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $kg = fn ($value) => number_format((float) $value, 3, ',', '.') . ' Kg';
    $qty = fn ($product) => (($product['unit'] ?? 'kg') === 'kg')
        ? $kg($product['quantity'] ?? $product['kg'] ?? 0)
        : number_format((float) ($product['quantity'] ?? 0), 3, ',', '.') . ' ' . ($product['unit'] ?? '');
    $avgUnit = fn ($product) => $money(($product['unit'] ?? 'kg') === 'kg'
        ? ($product['avg_price_per_kg'] ?? 0)
        : ($product['avg_price_per_unit'] ?? 0));
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';
    $period = \Carbon\Carbon::parse($insights['filters']['date_from'])->format('d M Y') . ' - ' . \Carbon\Carbon::parse($insights['filters']['date_until'])->format('d M Y');
    $profitOutletRows = collect($insights['profit_by_outlet'] ?? []);
    $profitOutletTotals = [
        'net_sales' => $profitOutletRows->sum('net_sales'),
        'gunsas_revenue' => $profitOutletRows->sum('gunsas_revenue'),
        'hpp_sales' => $profitOutletRows->sum('hpp_sales'),
        'expenses' => $profitOutletRows->sum('expenses'),
        'return_loss' => $profitOutletRows->sum('return_loss'),
        'return_kg' => $profitOutletRows->sum('return_kg'),
        'opname_loss' => $profitOutletRows->sum('opname_loss'),
        'opname_loss_kg' => $profitOutletRows->sum('opname_loss_kg'),
        'inventory_usage' => $profitOutletRows->sum('inventory_usage'),
        'net_profit' => $profitOutletRows->sum('net_profit'),
    ];
    $profitOutletTotals['net_margin'] = $profitOutletTotals['gunsas_revenue'] > 0
        ? ($profitOutletTotals['net_profit'] / $profitOutletTotals['gunsas_revenue']) * 100
        : 0;
    $salesProductRows = collect($insights['sales_by_product'] ?? []);
    $salesProductTotals = [
        'kg' => $salesProductRows->sum('kg'),
        'gross_sales' => $salesProductRows->sum('gross_sales'),
        'net_sales' => $salesProductRows->sum('net_sales'),
        'gunsas_revenue' => $salesProductRows->sum('gunsas_revenue'),
    ];
    $salesProductTotals['avg_price_per_kg'] = $salesProductTotals['kg'] > 0
        ? $salesProductTotals['net_sales'] / $salesProductTotals['kg']
        : 0;
    $totalProfitCosts = (float) ($insights['costs']['hpp_sales'] ?? 0)
        + (float) ($insights['costs']['expenses'] ?? 0)
        + (float) ($insights['costs']['inventory_usage'] ?? 0)
        + (float) ($insights['returns']['loss_final'] ?? 0)
        + (float) ($insights['costs']['opname_loss'] ?? 0);
    $returnRecovery = $insights['returns']['recovery'] ?? [];
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

        <div x-data="{ tab: 'ringkasan' }" class="space-y-4">
            @php
                $tabs = [
                    'ringkasan' => 'Ringkasan',
                    'penjualan' => 'Penjualan',
                    'biaya' => 'Biaya & Modal',
                    'loss' => 'Retur & Loss',
                    'outlet' => 'Outlet',
                    'purchase' => 'Purchase',
                    'stok' => 'Stok Teknis',
                ];
            @endphp

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex min-w-max gap-1">
                    @foreach ($tabs as $key => $label)
                        <button
                            type="button"
                            x-on:click="tab = '{{ $key }}'"
                            class="rounded-md px-3 py-2 text-sm font-semibold transition"
                            x-bind:class="tab === '{{ $key }}'
                                ? 'bg-primary-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div x-show="tab === 'ringkasan'" class="space-y-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Ringkasan Periode</p>
                            <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $insights['filters']['outlet_name'] }}</h2>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $period }}</span>
                                <span class="rounded-md bg-warning-50 px-2 py-1 font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">{{ $insights['filters']['product_label'] ?? 'Semua Produk / Semua Varian' }}</span>
                            </div>
                        </div>

                        <div class="grid gap-2 text-xs sm:grid-cols-2 xl:w-[560px]">
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                <p class="font-semibold text-gray-950 dark:text-white">Profit bersih</p>
                                <p class="mt-1 text-gray-500">Laba periode berjalan setelah HPP, expense, inventory terpakai, retur final, dan opname.</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                <p class="font-semibold text-gray-950 dark:text-white">Profit + stok</p>
                                <p class="mt-1 text-gray-500">Posisi bisnis kalau nilai stok tersisa ikut dilihat sebagai aset, bukan laba kas.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="space-y-2">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Pendapatan</h3>
                            <p class="text-xs text-gray-500">Alur uang dari kasir sampai menjadi bagian Gunsas.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs font-medium text-gray-500">1. Omset kasir</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $money($insights['sales']['gross_sales']) }}</p>
                            <p class="mt-1 text-xs text-gray-500">Total penjualan kotor di kasir.</p>
                        </div>

                        <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 shadow-sm dark:border-warning-500/20 dark:bg-warning-500/10">
                            <p class="text-xs font-medium text-warning-700 dark:text-warning-400">2. Omset setelah potongan</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $money($insights['sales']['net_sales']) }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Diskon {{ $money($insights['sales']['discount_amount']) }} + sales return {{ $money($insights['sales']['sales_return_amount'] ?? 0) }}.</p>
                        </div>

                        <div class="rounded-lg border border-info-200 bg-info-50 p-4 shadow-sm dark:border-info-500/20 dark:bg-info-500/10">
                            <p class="text-xs font-medium text-info-700 dark:text-info-400">3. Bagian Gunsas</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $money($insights['sales']['gunsas_revenue']) }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Setelah bagi hasil partner {{ number_format((float) $insights['sales']['partner_share_percent'], 2, ',', '.') }}%.</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Profit Periode</h3>
                        <p class="text-xs text-gray-500">Fokus ke laba/rugi operasional periode yang dipilih.</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs text-gray-500">Profit bersih</p>
                            <p class="mt-1 text-2xl font-semibold {{ $insights['profit']['net_profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ $money($insights['profit']['net_profit']) }}</p>
                            <p class="mt-1 text-xs text-gray-500">Bagian Gunsas dikurangi semua beban profit.</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs text-gray-500">Margin bersih</p>
                            <p class="mt-1 text-2xl font-semibold {{ $insights['profit']['net_margin'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ $percent($insights['profit']['net_margin']) }}</p>
                            <p class="mt-1 text-xs text-gray-500">Profit bersih / bagian Gunsas.</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs text-gray-500">Recovery fresh terjual</p>
                            <p class="mt-1 text-2xl font-semibold text-success-600">{{ $kg($returnRecovery['sold_kg'] ?? 0) }}</p>
                            <p class="mt-1 text-xs text-gray-500">HPP tidak dibebankan {{ $money($returnRecovery['hpp_saved_amount'] ?? 0) }}.</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Aset & Stok</h3>
                        <p class="text-xs text-gray-500">Nilai stok tersisa ditampilkan sebagai aset, bukan laba kas.</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs text-gray-500">Inventory valuation</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $money($insights['inventory']['amount'] ?? 0) }}</p>
                            <p class="mt-1 text-xs text-gray-500">Estimasi nilai stok tersisa pada akhir periode.</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs text-gray-500">Posisi setelah stok</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $money($insights['profit']['net_asset_position'] ?? 0) }}</p>
                            <p class="mt-1 text-xs text-gray-500">Profit bersih + inventory valuation. Fresh recovery tersisa {{ $kg($insights['inventory']['fresh_recovery_kg'] ?? 0) }} tidak dihitung modal.</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Beban, Stok, dan Barang Masuk</h3>
                        <p class="text-xs text-gray-500">Bagian ini dipakai untuk menjelaskan kenapa profit naik atau turun.</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs text-gray-500">Total pengurang profit</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $money($totalProfitCosts) }}</p>
                            <div class="mt-3 space-y-1 text-xs text-gray-500">
                                <div class="flex justify-between gap-3"><span>HPP</span><span class="font-medium text-gray-950 dark:text-white">{{ $money($insights['costs']['hpp_sales'] ?? 0) }}</span></div>
                                <div class="flex justify-between gap-3"><span>Expense</span><span class="font-medium text-gray-950 dark:text-white">{{ $money($insights['costs']['expenses'] ?? 0) }}</span></div>
                                <div class="flex justify-between gap-3"><span>Inventory terpakai</span><span class="font-medium text-gray-950 dark:text-white">{{ $money($insights['costs']['inventory_usage'] ?? 0) }}</span></div>
                                <div class="flex justify-between gap-3"><span>Loss retur + opname</span><span class="font-medium text-danger-600">{{ $money(((float) ($insights['returns']['loss_final'] ?? 0)) + ((float) ($insights['costs']['opname_loss'] ?? 0))) }}</span></div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs text-gray-500">Biaya operasional</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $money($insights['costs']['expenses'] ?? 0) }}</p>
                            <div class="mt-3 space-y-1 text-xs text-gray-500">
                                <div class="flex justify-between gap-3"><span>Langsung ke outlet</span><span class="font-medium text-gray-950 dark:text-white">{{ $money($insights['costs']['direct_expenses'] ?? 0) }}</span></div>
                                <div class="flex justify-between gap-3"><span>Alokasi pusat/grup</span><span class="font-medium text-gray-950 dark:text-white">{{ $money($insights['costs']['allocated_global_expenses'] ?? 0) }}</span></div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <p class="text-xs text-gray-500">Barang dikirim ke outlet</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $money($insights['shipments']['total_modal'] ?? 0) }}</p>
                            <div class="mt-3 space-y-1 text-xs text-gray-500">
                                <div class="flex justify-between gap-3"><span>Berat durian</span><span class="font-medium text-gray-950 dark:text-white">{{ $kg($insights['shipments']['durian_kg'] ?? 0) }}</span></div>
                                <div class="flex justify-between gap-3"><span>Butir durian</span><span class="font-medium text-gray-950 dark:text-white">{{ number_format((float) ($insights['shipments']['durian_butir'] ?? 0), 0, ',', '.') }} Btr</span></div>
                                <div class="flex justify-between gap-3"><span>Jumlah kiriman</span><span class="font-medium text-gray-950 dark:text-white">{{ $insights['shipments']['records'] ?? 0 }} kiriman</span></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div x-show="tab === 'outlet'" class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold">Profit per Outlet</h3>
                    <p class="mt-1 text-xs text-gray-500">Profit outlet dihitung dari pendapatan Gunsas setelah bagi hasil, lalu dikurangi HPP, expense outlet, inventory terpakai, retur final, dan loss opname.</p>
                </div>
                <p class="text-xs text-gray-500">Expense pusat/global tidak dibebankan ke outlet.</p>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-xs">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        <tr>
                            <th class="px-2 py-2 font-medium">Outlet</th>
                            <th class="px-2 py-2 font-medium">Grup</th>
                            <th class="px-2 py-2 text-right font-medium">Sales Net</th>
                            <th class="px-2 py-2 text-right font-medium">Pendapatan Gunsas</th>
                            <th class="px-2 py-2 text-right font-medium">HPP</th>
                            <th class="px-2 py-2 text-right font-medium">Expense</th>
                            <th class="px-2 py-2 text-right font-medium">Retur Loss</th>
                            <th class="px-2 py-2 text-right font-medium">Opname Loss</th>
                            <th class="px-2 py-2 text-right font-medium">Inventory</th>
                            <th class="px-2 py-2 text-right font-medium">Profit Bersih</th>
                            <th class="px-2 py-2 text-right font-medium">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($profitOutletRows as $row)
                            <tr>
                                <td class="px-2 py-2 font-medium text-gray-950 dark:text-white">{{ $row['outlet_name'] }}</td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ $row['group_name'] }}</td>
                                <td class="px-2 py-2 text-right">{{ $money($row['net_sales']) }}</td>
                                <td class="px-2 py-2 text-right font-medium text-info-600">{{ $money($row['gunsas_revenue']) }}</td>
                                <td class="px-2 py-2 text-right">{{ $money($row['hpp_sales']) }}</td>
                                <td class="px-2 py-2 text-right">{{ $money($row['expenses']) }}</td>
                                <td class="px-2 py-2 text-right">
                                    <div class="{{ $row['return_loss'] > 0 ? 'text-danger-600' : 'text-gray-500' }}">{{ $money($row['return_loss']) }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $kg($row['return_kg']) }}</div>
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <div class="{{ $row['opname_loss'] > 0 ? 'text-danger-600' : 'text-gray-500' }}">{{ $money($row['opname_loss']) }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $kg($row['opname_loss_kg']) }}</div>
                                </td>
                                <td class="px-2 py-2 text-right">{{ $money($row['inventory_usage']) }}</td>
                                <td class="px-2 py-2 text-right font-semibold {{ $row['net_profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ $money($row['net_profit']) }}
                                </td>
                                <td class="px-2 py-2 text-right font-medium {{ $row['net_margin'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ $percent($row['net_margin']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-3 py-8 text-center text-gray-500">Belum ada aktivitas outlet pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($profitOutletRows->isNotEmpty())
                        <tfoot class="border-t border-gray-300 bg-gray-50 font-semibold dark:border-gray-700 dark:bg-gray-950">
                            <tr>
                                <td class="px-2 py-3 text-gray-950 dark:text-white">TOTAL</td>
                                <td class="px-2 py-3 text-gray-500">{{ $profitOutletRows->count() }} outlet</td>
                                <td class="px-2 py-3 text-right">{{ $money($profitOutletTotals['net_sales']) }}</td>
                                <td class="px-2 py-3 text-right text-info-600">{{ $money($profitOutletTotals['gunsas_revenue']) }}</td>
                                <td class="px-2 py-3 text-right">{{ $money($profitOutletTotals['hpp_sales']) }}</td>
                                <td class="px-2 py-3 text-right">{{ $money($profitOutletTotals['expenses']) }}</td>
                                <td class="px-2 py-3 text-right">
                                    <div class="{{ $profitOutletTotals['return_loss'] > 0 ? 'text-danger-600' : 'text-gray-500' }}">{{ $money($profitOutletTotals['return_loss']) }}</div>
                                    <div class="text-[11px] font-normal text-gray-500">{{ $kg($profitOutletTotals['return_kg']) }}</div>
                                </td>
                                <td class="px-2 py-3 text-right">
                                    <div class="{{ $profitOutletTotals['opname_loss'] > 0 ? 'text-danger-600' : 'text-gray-500' }}">{{ $money($profitOutletTotals['opname_loss']) }}</div>
                                    <div class="text-[11px] font-normal text-gray-500">{{ $kg($profitOutletTotals['opname_loss_kg']) }}</div>
                                </td>
                                <td class="px-2 py-3 text-right">{{ $money($profitOutletTotals['inventory_usage']) }}</td>
                                <td class="px-2 py-3 text-right {{ $profitOutletTotals['net_profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ $money($profitOutletTotals['net_profit']) }}
                                </td>
                                <td class="px-2 py-3 text-right {{ $profitOutletTotals['net_margin'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                    {{ $percent($profitOutletTotals['net_margin']) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
            </div>

            <div x-show="tab === 'purchase'" class="space-y-4">
        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 520px), 1fr));">
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold">Purchase Summary</h3>
                        <p class="mt-1 text-xs text-gray-500">Pembelian pusat pada periode ini. Tidak mengikuti filter outlet karena purchase belum dialokasikan langsung ke cabang.</p>
                    </div>
                    <p class="text-xs text-gray-500">{{ $insights['purchases']['records'] ?? 0 }} nota</p>
                </div>

                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Total Purchase</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['purchases']['total_amount'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Purchase Durian</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['purchases']['durian_amount'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Purchase Inventory</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['purchases']['inventory_amount'] ?? 0) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Total Buah Dibeli</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['purchases']['durian_kg'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Total Butir</dt><dd class="shrink-0 text-right font-medium">{{ number_format((float) ($insights['purchases']['durian_butir'] ?? 0), 0, ',', '.') }} Btr</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Avg Beli Durian</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['purchases']['avg_price_per_kg'] ?? 0) }} / Kg</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Supplier Purchase Terbesar</h3>
                <div class="mt-3 space-y-2 text-xs">
                    @forelse (($insights['purchases']['by_supplier'] ?? []) as $supplier)
                        <div class="rounded-md bg-gray-50 p-2 dark:bg-gray-950">
                            <div class="flex justify-between gap-3">
                                <span class="font-medium text-gray-950 dark:text-white">{{ $supplier['supplier'] }}</span>
                                <span class="font-semibold">{{ $money($supplier['amount']) }}</span>
                            </div>
                            <div class="mt-1 flex justify-between gap-3 text-gray-500">
                                <span>{{ $kg($supplier['kg']) }} | {{ number_format((float) $supplier['butir'], 0, ',', '.') }} Btr</span>
                                <span>Avg {{ $money($supplier['avg_price_per_kg']) }}/Kg</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada purchase pada periode ini.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Purchase per Varian</h3>
                <div class="mt-3 space-y-2 text-xs">
                    @forelse (($insights['purchases']['by_variety'] ?? []) as $variety)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-600 dark:text-gray-300">{{ $variety['variety'] }} ({{ $kg($variety['kg']) }})</span>
                            <span class="font-medium">{{ $money($variety['amount']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada purchase durian pada periode ini.</p>
                    @endforelse
                </div>
            </div>
        </div>
            </div>

            <div x-show="tab === 'biaya'" class="space-y-4">
        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 520px), 1fr));">
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Inventory Valuation</h3>
                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Nilai Stok Tersisa</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['inventory']['amount']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Total KG Stok</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['total_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Buah Utuh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['buah_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Kupas Fresh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['fresh_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Durpas Frozen</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['frozen_kg']) }}</dd></div>
                    @if (($insights['inventory']['inventory_item_amount'] ?? 0) > 0)
                        <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Produk jualan non-durian</dt>
                            <dd class="shrink-0 text-right font-medium">{{ $money($insights['inventory']['inventory_item_amount']) }}</dd>
                        </div>
                        @foreach (($insights['inventory']['inventory_item_items'] ?? []) as $item)
                            <div class="flex items-start justify-between gap-3 pl-3">
                                <dt class="text-gray-500">{{ $item['name'] }} ({{ number_format((float) $item['qty'], 3, ',', '.') }} {{ $item['unit'] }})</dt>
                                <dd class="shrink-0 text-right font-medium">{{ $money($item['amount']) }}</dd>
                            </div>
                        @endforeach
                    @endif
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Biaya & Modal</h3>
                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">HPP Penjualan</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['hpp_sales']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3 pl-3"><dt class="text-gray-500">- Buah / Fresh / Frozen</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['hpp_breakdown']['buah'] ?? 0) }} / {{ $money($insights['costs']['hpp_breakdown']['fresh'] ?? 0) }} / {{ $money($insights['costs']['hpp_breakdown']['frozen'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Expenses</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['expenses']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3 pl-3"><dt class="text-gray-500">- Direct outlet</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['direct_expenses'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3 pl-3"><dt class="text-gray-500">- Alokasi pusat/global</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['allocated_global_expenses'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Inventory Terpakai Operasional</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['inventory_usage']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Loss Fisik Minus</dt><dd class="shrink-0 text-right font-medium text-danger-600">{{ $money($insights['costs']['opname_loss_kg']['gross_amount'] ?? $insights['costs']['opname_loss']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3 pl-3"><dt class="text-gray-500">- Produk jualan non-durian</dt><dd class="shrink-0 text-right font-medium text-danger-600">{{ $money($insights['costs']['opname_loss_kg']['inventory_item_amount'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Koreksi Stok Minus</dt><dd class="shrink-0 text-right font-medium text-success-600">- {{ $money($insights['costs']['opname_loss_kg']['correction_amount'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="font-medium">Net Loss Opname</dt><dd class="shrink-0 text-right font-semibold">{{ $money($insights['costs']['opname_loss']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Loss Opname KG</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['total_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Modal Buah Avg</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['avg_modal_buah']) }} / Kg</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Modal Fresh Avg</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['avg_modal_fresh']) }} / Kg</dd></div>
                    <div class="flex items-start justify-between gap-3 pl-3"><dt class="text-gray-500">- Pengkali modal efektif</dt><dd class="shrink-0 text-right font-medium">{{ number_format((float) ($insights['costs']['avg_modal_fresh_breakdown']['effective_multiplier'] ?? 0), 2, ',', '.') }}x</dd></div>
                    <div class="flex items-start justify-between gap-3 pl-3"><dt class="text-gray-500">- Output return dikecualikan</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['avg_modal_fresh_breakdown']['return_output_kg_excluded'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Modal Frozen Avg</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['avg_modal_frozen']) }} / Kg</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Retur Supplier</h3>
                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Retur Diajukan</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['returns']['asset_submitted']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">KG Diajukan</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['returns']['submitted_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Refund Diterima</dt><dd class="shrink-0 text-right font-medium text-success-600">{{ $money($insights['returns']['refund_received']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Loss Final</dt><dd class="shrink-0 text-right font-medium text-danger-600">{{ $money($insights['returns']['loss_final']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">KG Ditolak Supplier</dt><dd class="shrink-0 text-right font-medium text-danger-600">{{ $kg($insights['returns']['rejected_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Fresh dari Return</dt><dd class="shrink-0 text-right font-medium">{{ $kg($returnRecovery['fresh_kg'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Fresh Recovery Terjual</dt><dd class="shrink-0 text-right font-medium text-success-600">{{ $kg($returnRecovery['sold_kg'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Fresh Recovery Tersisa</dt><dd class="shrink-0 text-right font-medium">{{ $kg($returnRecovery['remaining_kg'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Olahan dari Return</dt><dd class="shrink-0 text-right font-medium">{{ $kg($returnRecovery['olahan_kg'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">HPP Tidak Dibebankan</dt><dd class="shrink-0 text-right font-medium text-success-600">{{ $money($returnRecovery['hpp_saved_amount'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Rugi Final Setelah Refund</dt><dd class="shrink-0 text-right font-medium text-danger-600">{{ $money($insights['returns']['loss_final']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Klaim Pending</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['returns']['pending_asset']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">KG Pending</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['returns']['pending_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Jumlah Pending</dt><dd class="shrink-0 text-right font-medium">{{ $insights['returns']['pending_count'] }} retur</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Efisiensi Produksi</h3>
                <p class="mt-1 text-xs text-gray-500">Buah utuh menjadi kupas fresh dan olahan/reject.</p>
                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Produksi Tercatat</dt><dd class="shrink-0 text-right font-medium">{{ $insights['production_efficiency']['production_count'] }} batch</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Input Buah Utuh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['production_efficiency']['input_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Total Daging Diperoleh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['production_efficiency']['usable_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Kupas Fresh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['production_efficiency']['fresh_kg']) }} / {{ $percent($insights['production_efficiency']['fresh_yield_percentage']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Olahan / Reject</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['production_efficiency']['olahan_kg']) }} / {{ $percent($insights['production_efficiency']['olahan_yield_percentage']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Susut Kulit & Biji</dt><dd class="shrink-0 text-right font-medium text-warning-600">{{ $kg($insights['production_efficiency']['shrink_kg']) }} / {{ $percent($insights['production_efficiency']['shrinkage_percentage']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Yield Daging</dt><dd class="shrink-0 text-right font-medium text-success-600">{{ $percent($insights['production_efficiency']['yield_percentage']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800"><dt class="font-medium">Pengkali Produksi Fisik</dt><dd class="shrink-0 text-right font-semibold">{{ number_format((float) $insights['production_efficiency']['multiplier_factor'], 2, ',', '.') }}x</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Volume Terjual</h3>
                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Buah Utuh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['sales']['buah_sold_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Kupas Fresh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['sales']['fresh_sold_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Durpas Frozen</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['sales']['frozen_sold_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Profit Kotor</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['profit']['gross_profit']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Bagi Hasil Partner</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['sales']['partner_cut']) }}</dd></div>
                </dl>
            </div>
        </div>
            </div>

            <div x-show="tab === 'penjualan'" class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold">Laporan Penjualan Per Produk</h3>
                    <p class="mt-1 text-xs text-gray-500">Breakdown berdasarkan kategori produk dan varian durian pada periode yang dipilih.</p>
                </div>
                <p class="text-sm text-gray-500">Sales setelah diskon dibagi proporsional dari subtotal produk.</p>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-xs">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        <tr>
                            <th class="px-2 py-2 font-medium">Produk</th>
                            <th class="px-2 py-2 font-medium">Kategori</th>
                            <th class="px-2 py-2 font-medium">Varian</th>
                            <th class="px-2 py-2 text-right font-medium">Qty</th>
                            <th class="px-2 py-2 text-right font-medium">Omset</th>
                            <th class="px-2 py-2 text-right font-medium">Sales Net</th>
                            <th class="px-2 py-2 text-right font-medium">Bagian Gunsas</th>
                            <th class="px-2 py-2 text-right font-medium">Avg/Satuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($salesProductRows as $product)
                            <tr>
                                <td class="px-2 py-2 font-medium text-gray-950 dark:text-white">{{ $product['product'] }}</td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ $product['category'] }}</td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ $product['variety'] }}</td>
                                <td class="px-2 py-2 text-right font-medium">{{ $qty($product) }}</td>
                                <td class="px-2 py-2 text-right">{{ $money($product['gross_sales']) }}</td>
                                <td class="px-2 py-2 text-right font-medium">{{ $money($product['net_sales']) }}</td>
                                <td class="px-2 py-2 text-right font-medium text-success-600">{{ $money($product['gunsas_revenue']) }}</td>
                                <td class="px-2 py-2 text-right">{{ $avgUnit($product) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-gray-500">Belum ada penjualan pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($salesProductRows->isNotEmpty())
                        <tfoot class="border-t border-gray-300 text-xs font-semibold dark:border-gray-700">
                            <tr>
                                <td colspan="3" class="px-2 py-3 text-gray-950 dark:text-white">Total</td>
                                <td class="px-2 py-3 text-right">{{ $kg($salesProductTotals['kg']) }}</td>
                                <td class="px-2 py-3 text-right">{{ $money($salesProductTotals['gross_sales']) }}</td>
                                <td class="px-2 py-3 text-right">{{ $money($salesProductTotals['net_sales']) }}</td>
                                <td class="px-2 py-3 text-right text-success-600">{{ $money($salesProductTotals['gunsas_revenue']) }}</td>
                                <td class="px-2 py-3 text-right">{{ $money($salesProductTotals['avg_price_per_kg']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold">Rata-rata Penjualan per Hari</h3>
                    <p class="mt-1 text-xs text-gray-500">Total KG terjual dibagi jumlah hari pada periode filter.</p>
                </div>
                <p class="text-xs text-gray-500">{{ $period }}</p>
            </div>

            @php
                $salesDays = max(1, \Carbon\Carbon::parse($insights['filters']['date_from'])->startOfDay()->diffInDays(\Carbon\Carbon::parse($insights['filters']['date_until'])->startOfDay()) + 1);
                $avgBuahPerDay = (float) $insights['sales']['buah_sold_kg'] / $salesDays;
                $avgFreshPerDay = (float) $insights['sales']['fresh_sold_kg'] / $salesDays;
                $avgFrozenPerDay = (float) $insights['sales']['frozen_sold_kg'] / $salesDays;
            @endphp

            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950">
                    <p class="text-xs text-gray-500">Buah Utuh / Hari</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $kg($avgBuahPerDay) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Total {{ $kg($insights['sales']['buah_sold_kg']) }} / {{ $salesDays }} hari</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950">
                    <p class="text-xs text-gray-500">Kupas Fresh / Hari</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $kg($avgFreshPerDay) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Total {{ $kg($insights['sales']['fresh_sold_kg']) }} / {{ $salesDays }} hari</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950">
                    <p class="text-xs text-gray-500">Durpas Frozen / Hari</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $kg($avgFrozenPerDay) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Total {{ $kg($insights['sales']['frozen_sold_kg']) }} / {{ $salesDays }} hari</p>
                </div>
            </div>
        </div>
            </div>

            <div x-show="tab === 'loss'" class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold">Peta Loss KG</h3>
                    <p class="mt-1 text-xs text-gray-500">Memisahkan loss yang mengurangi profit dan susut proses yang sudah masuk ke modal average.</p>
                </div>
                <div class="grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
                        <p class="text-gray-500">Loss Langsung</p>
                        <p class="font-semibold text-danger-600">{{ $kg($insights['loss_breakdown']['direct_loss_kg']) }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
                        <p class="text-gray-500">Susut Proses</p>
                        <p class="font-semibold">{{ $kg($insights['loss_breakdown']['process_shrink_kg']) }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
                        <p class="text-gray-500">Total Terdeteksi</p>
                        <p class="font-semibold">{{ $kg($insights['loss_breakdown']['total_kg']) }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($insights['loss_breakdown']['items'] as $item)
                    <div class="rounded-lg border border-gray-200 p-3 text-xs dark:border-gray-800">
                        <p class="font-medium">{{ $item['label'] }}</p>
                        <div class="mt-3 space-y-2">
                            <div class="flex justify-between gap-3"><span class="text-gray-500">KG</span><span class="font-semibold">{{ $kg($item['kg']) }}</span></div>
                            <div class="flex justify-between gap-3"><span class="text-gray-500">Estimasi nilai</span><span class="font-semibold">{{ $money($item['amount']) }}</span></div>
                            <div class="flex justify-between gap-3"><span class="text-gray-500">Efek</span><span class="text-right font-medium">{{ $item['impact'] }}</span></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 520px), 1fr));">
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Detail Loss Opname</h3>
                <div class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Buah Utuh</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['buah_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Kupas Fresh</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['fresh_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Durpas Frozen</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['frozen_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800"><span class="font-medium">Total KG Hilang</span><span class="shrink-0 text-right font-semibold">{{ $kg($insights['costs']['opname_loss_kg']['total_kg']) }}</span></div>
                    @if (! empty($insights['costs']['opname_loss_kg']['variant_details']))
                        <div class="border-t border-gray-200 pt-3 dark:border-gray-800">
                            <p class="mb-2 font-medium">Rincian Varian</p>
                            <div class="space-y-2">
                                @foreach ($insights['costs']['opname_loss_kg']['variant_details'] as $detail)
                                    <div class="rounded-md bg-gray-50 p-2 dark:bg-gray-950">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-medium">{{ $detail['variant'] }}</p>
                                                <p class="text-[11px] text-gray-500">{{ $detail['product_label'] }}</p>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                @if (($detail['loss_kg'] ?? 0) > 0)
                                                    <p class="font-semibold text-danger-600">{{ $kg($detail['loss_kg']) }}</p>
                                                @endif
                                                @if (($detail['correction_kg'] ?? 0) > 0)
                                                    <p class="text-[11px] font-medium text-success-600">Koreksi {{ $kg($detail['correction_kg']) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if (($insights['costs']['opname_loss_kg']['inventory_item_amount'] ?? 0) > 0)
                        <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                        <div class="flex items-start justify-between gap-3"><span class="font-medium">Loss Produk Jualan</span><span class="shrink-0 text-right font-semibold text-danger-600">{{ $money($insights['costs']['opname_loss_kg']['inventory_item_amount']) }}</span></div>
                        @foreach (($insights['costs']['opname_loss_kg']['inventory_item_items'] ?? []) as $item)
                            <div class="flex items-start justify-between gap-3 pl-3">
                                <span class="text-gray-600 dark:text-gray-300">{{ $item['name'] }} ({{ number_format((float) $item['qty'], 3, ',', '.') }} {{ $item['unit'] }})</span>
                                <span class="shrink-0 text-right font-medium">{{ $money($item['amount']) }}</span>
                            </div>
                        @endforeach
                    @endif
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Total stok plus fisik</span><span class="shrink-0 text-right font-medium text-success-600">{{ $kg($insights['costs']['opname_loss_kg']['plus_total_kg'] ?? 0) }}</span></div>
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Plus normal / aset tambahan</span><span class="shrink-0 text-right font-medium text-success-600">{{ $kg($insights['costs']['opname_loss_kg']['plus_normal_kg'] ?? 0) }}</span></div>
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Yang mengurangi loss karena stok sistem minus</span><span class="shrink-0 text-right font-medium text-success-600">{{ $kg($insights['costs']['opname_loss_kg']['correction_total_kg'] ?? 0) }}</span></div>
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Nilai pengurang loss</span><span class="shrink-0 text-right font-medium text-success-600">- {{ $money($insights['costs']['opname_loss_kg']['correction_amount'] ?? 0) }}</span></div>
                    <div class="flex items-start justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800"><span class="font-medium">Loss Opname Setelah Koreksi</span><span class="shrink-0 text-right font-semibold">{{ $money($insights['costs']['opname_loss']) }}</span></div>
                    <details class="rounded-md bg-gray-50 p-3 text-[11px] dark:bg-gray-950">
                        <summary class="cursor-pointer font-medium">Cara hitung loss opname</summary>
                        <div class="mt-3 space-y-2 text-gray-600 dark:text-gray-300">
                            <p>Total stok plus fisik adalah semua selisih plus di stock opname. Hanya bagian plus yang menutup stok sistem minus yang dipakai sebagai pengurang loss.</p>
                            <p>Loss fisik minus dihitung dari selisih minus dikali modal average tiap kategori: buah pakai modal buah, fresh pakai modal fresh, frozen pakai modal frozen.</p>
                            <div class="flex items-start justify-between gap-3">
                                <span>Total stok plus fisik</span>
                                <span class="shrink-0 text-right font-medium text-success-600">{{ $kg($insights['costs']['opname_loss_kg']['plus_total_kg'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <span>Loss fisik minus</span>
                                <span class="shrink-0 text-right font-medium">{{ $money($insights['costs']['opname_loss_kg']['gross_amount'] ?? $insights['costs']['opname_loss']) }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <span>Koreksi dari stok sistem minus</span>
                                <span class="shrink-0 text-right font-medium text-success-600">- {{ $money($insights['costs']['opname_loss_kg']['correction_amount'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-3 border-t border-gray-200 pt-2 font-semibold dark:border-gray-800">
                                <span>Loss opname setelah koreksi</span>
                                <span class="shrink-0 text-right">{{ $money($insights['costs']['opname_loss']) }}</span>
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Inventory Terpakai Operasional</h3>
                <div class="mt-3 space-y-2 text-xs">
                    @forelse ($insights['costs']['inventory_usage_items'] as $item)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-600 dark:text-gray-300">{{ $item['name'] }} ({{ number_format((float) $item['qty'], 3, ',', '.') }} {{ $item['unit'] }})</span>
                            <span class="font-medium">{{ $money($item['amount']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada pemakaian inventory pada periode ini.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Outlet Dengan Omset Tertinggi</h3>
                <div class="mt-3 space-y-2 text-xs">
                    @forelse ($insights['top_outlets'] as $outlet)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-600 dark:text-gray-300">{{ $outlet['name'] }}</span>
                            <span class="font-medium">{{ $money($outlet['revenue']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada penjualan pada periode ini.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Kategori Expense Terbesar</h3>
                <div class="mt-3 space-y-2 text-xs">
                    @forelse ($insights['expense_categories'] as $expense)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-600 dark:text-gray-300">{{ $expense['category'] }}</span>
                            <span class="font-medium">{{ $money($expense['total']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada expense pada periode ini.</p>
                    @endforelse
                </div>
            </div>
        </div>
            </div>

            <div x-show="tab === 'stok'" class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h3 class="text-sm font-semibold">Pergerakan Stok Outlet</h3>
                <p class="mt-1 text-xs text-gray-500">Data teknis kiriman, penjualan, proses, retur, dan opname. Dipakai untuk audit stok kalau angka ringkasan perlu dicek ulang.</p>
            </div>

            <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-gray-500">Stok Awal</p>
                    <p class="font-semibold">{{ $kg($insights['stock_movement']['summary']['start_kg'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-gray-500">Masuk</p>
                    <p class="font-semibold">{{ $kg($insights['stock_movement']['summary']['received_kg'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-gray-500">Terjual</p>
                    <p class="font-semibold">{{ $kg($insights['stock_movement']['summary']['sold_kg'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-gray-500">Estimasi Sisa</p>
                    <p class="font-semibold">{{ $kg($insights['stock_movement']['summary']['estimated_stock_kg'] ?? 0) }}</p>
                </div>
                @php
                    $stockHasOpname = ($insights['stock_movement']['summary']['physical_stock_rows'] ?? 0) > 0;
                    $stockVariance = $insights['stock_movement']['summary']['variance_kg'] ?? null;
                @endphp
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-gray-500">Selisih Opname</p>
                    <p class="font-semibold {{ $stockHasOpname ? (($stockVariance ?? 0) < 0 ? 'text-danger-600' : 'text-success-600') : 'text-gray-500' }}">
                        {{ $stockHasOpname ? $kg($stockVariance ?? 0) : '-' }}
                    </p>
                </div>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[1080px] text-left text-xs">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        <tr>
                            <th class="px-2 py-2 font-medium">Outlet</th>
                            <th class="px-2 py-2 font-medium">Produk</th>
                            <th class="px-2 py-2 text-right font-medium">Stok Awal</th>
                            <th class="px-2 py-2 text-right font-medium">Masuk</th>
                            <th class="px-2 py-2 text-right font-medium">Terjual</th>
                            <th class="px-2 py-2 text-right font-medium">Keluar Lain</th>
                            <th class="px-2 py-2 text-right font-medium">Estimasi Sisa</th>
                            <th class="px-2 py-2 text-right font-medium">Opname Terakhir</th>
                            <th class="px-2 py-2 text-right font-medium">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse (($insights['stock_movement']['rows'] ?? []) as $row)
                            @php
                                $otherOut = (float) $row['out_kg'] - (float) $row['sold_kg'];
                                $hasPhysicalStock = (bool) ($row['has_physical_stock'] ?? false);
                                $variance = $hasPhysicalStock ? (float) $row['variance_kg'] : null;
                            @endphp
                            <tr>
                                <td class="px-2 py-2">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $row['outlet_name'] }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $row['group_name'] }}</div>
                                </td>
                                <td class="px-2 py-2 font-medium">{{ $row['product_label'] }}</td>
                                <td class="px-2 py-2 text-right font-medium">{{ $kg($row['start_kg'] ?? 0) }}</td>
                                <td class="px-2 py-2 text-right">
                                    <div class="font-medium">{{ $kg($row['received_kg']) }}</div>
                                    <div class="text-[11px] text-gray-500">
                                        Kirim {{ $kg($row['shipment_in_kg']) }}
                                        @if (($row['production_in_kg'] + $row['conversion_in_kg']) > 0)
                                            | Proses {{ $kg($row['production_in_kg'] + $row['conversion_in_kg']) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-right font-medium">{{ $kg($row['sold_kg']) }}</td>
                                <td class="px-2 py-2 text-right">
                                    <div class="font-medium">{{ $kg($otherOut) }}</div>
                                    <div class="text-[11px] text-gray-500">
                                        @if ($row['return_kg'] > 0)
                                            Retur {{ $kg($row['return_kg']) }}
                                        @endif
                                        @if (($row['production_out_kg'] + $row['conversion_out_kg']) > 0)
                                            {{ $row['return_kg'] > 0 ? ' | ' : '' }}
                                            @if ($row['conversion_out_kg'] > 0 && $row['production_out_kg'] <= 0)
                                                Konversi frozen {{ $kg($row['conversion_out_kg']) }}
                                            @elseif ($row['production_out_kg'] > 0 && $row['conversion_out_kg'] <= 0)
                                                Produksi {{ $kg($row['production_out_kg']) }}
                                            @else
                                                Proses {{ $kg($row['production_out_kg'] + $row['conversion_out_kg']) }}
                                            @endif
                                        @endif
                                        @if ($row['shipment_out_kg'] > 0)
                                            {{ ($row['return_kg'] + $row['production_out_kg'] + $row['conversion_out_kg']) > 0 ? ' | ' : '' }}Tarik {{ $kg($row['shipment_out_kg']) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-right font-semibold">{{ $kg($row['estimated_stock_kg']) }}</td>
                                <td class="px-2 py-2 text-right font-medium {{ $hasPhysicalStock ? '' : 'text-gray-500' }}">
                                    {{ $hasPhysicalStock ? $kg($row['physical_stock_kg']) : '-' }}
                                </td>
                                <td class="px-2 py-2 text-right font-semibold {{ $hasPhysicalStock ? (($variance ?? 0) < 0 ? 'text-danger-600' : 'text-success-600') : 'text-gray-500' }}">
                                    {{ $hasPhysicalStock ? $kg($variance ?? 0) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-gray-500">Belum ada pergerakan stok pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>



