@php
    $insights = $this->insightsData ?: $this->insights;

    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $kg = fn ($value) => number_format((float) $value, 3, ',', '.') . ' Kg';
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';
    $period = \Carbon\Carbon::parse($insights['filters']['date_from'])->format('d M Y') . ' - ' . \Carbon\Carbon::parse($insights['filters']['date_until'])->format('d M Y');
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <form>
            {{ $this->form }}
        </form>

        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Ringkasan Periode</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $insights['filters']['outlet_name'] }}</h2>
                    <p class="text-xs text-gray-500">{{ $period }}</p>
                </div>

                <div class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    Profit bersih tidak memasukkan stok tersisa. Inventory valuation ditampilkan sebagai aset terpisah.
                </div>
            </div>
        </div>

        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));">
            <div class="rounded-lg border border-l-4 border-gray-200 border-l-gray-400 bg-white p-3 shadow-sm dark:border-gray-800 dark:border-l-gray-500 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Omset Kasir</p>
                <p class="mt-1 text-2xl font-semibold">{{ $money($insights['sales']['gross_sales']) }}</p>
                <p class="text-xs text-gray-500">100% penjualan di kasir</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 border-l-warning-500 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Sales Net</p>
                <p class="mt-1 text-2xl font-semibold">{{ $money($insights['sales']['net_sales']) }}</p>
                <p class="text-xs text-gray-500">Diskon: {{ $money($insights['sales']['discount_amount']) }} | Return: {{ $money($insights['sales']['sales_return_amount'] ?? 0) }}</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 border-l-info-500 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Pendapatan Gunsas</p>
                <p class="mt-1 text-2xl font-semibold">{{ $money($insights['sales']['gunsas_revenue']) }}</p>
                <p class="text-xs text-gray-500">Bagi hasil partner {{ number_format((float) $insights['sales']['partner_share_percent'], 2, ',', '.') }}%</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 {{ $insights['profit']['net_profit'] >= 0 ? 'border-l-success-500' : 'border-l-danger-500' }} bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Profit Bersih</p>
                <p class="mt-1 text-2xl font-semibold {{ $insights['profit']['net_profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $money($insights['profit']['net_profit']) }}
                </p>
                    <p class="text-xs text-gray-500">Setelah HPP, expenses, inventory, retur final, opname</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 {{ $insights['profit']['net_margin'] >= 0 ? 'border-l-success-500' : 'border-l-danger-500' }} bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Margin Bersih</p>
                <p class="mt-1 text-2xl font-semibold {{ $insights['profit']['net_margin'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $percent($insights['profit']['net_margin']) }}
                </p>
                <p class="text-xs text-gray-500">Profit bersih / pendapatan Gunsas</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 border-l-warning-500 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs text-gray-500">Profit + Inventory</p>
                <p class="mt-1 text-2xl font-semibold">{{ $money($insights['profit']['net_asset_position']) }}</p>
                <p class="text-xs text-gray-500">Posisi aset, bukan laba bersih</p>
            </div>
        </div>

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
                        @forelse ($insights['profit_by_outlet'] as $row)
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
                </table>
            </div>
        </div>

        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(520px, 1fr));">
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Inventory Valuation</h3>
                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Nilai Stok Tersisa</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['inventory']['amount']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Total KG Stok</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['total_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Buah Utuh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['buah_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Kupas Fresh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['fresh_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Durpas Frozen</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['frozen_kg']) }}</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Biaya & Modal</h3>
                <dl class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">HPP Penjualan</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['hpp_sales']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Expenses</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['expenses']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3 pl-3"><dt class="text-gray-500">- Direct outlet</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['direct_expenses'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3 pl-3"><dt class="text-gray-500">- Alokasi pusat/global</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['allocated_global_expenses'] ?? 0) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Inventory Terpakai</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['inventory_usage']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Loss Opname</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['opname_loss']) }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Loss Opname KG</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['total_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Modal Buah Avg</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['avg_modal_buah']) }} / Kg</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-gray-500">Modal Fresh Avg</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['avg_modal_fresh']) }} / Kg</dd></div>
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
                    <div class="flex items-start justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800"><dt class="font-medium">Pengkali Modal</dt><dd class="shrink-0 text-right font-semibold">{{ number_format((float) $insights['production_efficiency']['multiplier_factor'], 2, ',', '.') }}x</dd></div>
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
                            <th class="px-2 py-2 text-right font-medium">KG</th>
                            <th class="px-2 py-2 text-right font-medium">Omset</th>
                            <th class="px-2 py-2 text-right font-medium">Sales Net</th>
                            <th class="px-2 py-2 text-right font-medium">Bagian Gunsas</th>
                            <th class="px-2 py-2 text-right font-medium">Avg/Kg</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($insights['sales_by_product'] as $product)
                            <tr>
                                <td class="px-2 py-2 font-medium text-gray-950 dark:text-white">{{ $product['product'] }}</td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ $product['category'] }}</td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ $product['variety'] }}</td>
                                <td class="px-2 py-2 text-right font-medium">{{ $kg($product['kg']) }}</td>
                                <td class="px-2 py-2 text-right">{{ $money($product['gross_sales']) }}</td>
                                <td class="px-2 py-2 text-right font-medium">{{ $money($product['net_sales']) }}</td>
                                <td class="px-2 py-2 text-right font-medium text-success-600">{{ $money($product['gunsas_revenue']) }}</td>
                                <td class="px-2 py-2 text-right">{{ $money($product['avg_price_per_kg']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-gray-500">Belum ada penjualan pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
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

            <div class="mt-3 grid gap-3 md:grid-cols-3">
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

            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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

        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(520px, 1fr));">
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Detail Loss Opname</h3>
                <div class="mt-3 space-y-2 text-xs">
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Buah Utuh</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['buah_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Kupas Fresh</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['fresh_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-3"><span class="text-gray-600 dark:text-gray-300">Durpas Frozen</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['frozen_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800"><span class="font-medium">Total KG Hilang</span><span class="shrink-0 text-right font-semibold">{{ $kg($insights['costs']['opname_loss_kg']['total_kg']) }}</span></div>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-sm font-semibold">Inventory Terpakai</h3>
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
</x-filament-panels::page>



