@php
    $insights = $this->insights;

    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $kg = fn ($value) => number_format((float) $value, 3, ',', '.') . ' Kg';
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';
    $period = \Carbon\Carbon::parse($insights['filters']['date_from'])->format('d M Y') . ' - ' . \Carbon\Carbon::parse($insights['filters']['date_until'])->format('d M Y');
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <form>
            {{ $this->form }}
        </form>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Ringkasan Periode</p>
                    <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $insights['filters']['outlet_name'] }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $period }}</p>
                </div>

                <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    Profit bersih tidak memasukkan stok tersisa. Inventory valuation ditampilkan sebagai aset terpisah.
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-l-4 border-gray-200 border-l-gray-400 bg-white p-4 shadow-sm dark:border-gray-800 dark:border-l-gray-500 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Omset Kasir</p>
                <p class="mt-2 text-2xl font-semibold">{{ $money($insights['sales']['gross_sales']) }}</p>
                <p class="mt-1 text-sm text-gray-500">100% penjualan di kasir</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 border-l-info-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Pendapatan Gunsas</p>
                <p class="mt-2 text-2xl font-semibold">{{ $money($insights['sales']['gunsas_revenue']) }}</p>
                <p class="mt-1 text-sm text-gray-500">Setelah potongan TipTop 15%</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 {{ $insights['profit']['net_profit'] >= 0 ? 'border-l-success-500' : 'border-l-danger-500' }} bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Profit Bersih</p>
                <p class="mt-2 text-2xl font-semibold {{ $insights['profit']['net_profit'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $money($insights['profit']['net_profit']) }}
                </p>
                <p class="mt-1 text-sm text-gray-500">Setelah HPP, expenses, loss retur final, opname</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 {{ $insights['profit']['net_margin'] >= 0 ? 'border-l-success-500' : 'border-l-danger-500' }} bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Margin Bersih</p>
                <p class="mt-2 text-2xl font-semibold {{ $insights['profit']['net_margin'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $percent($insights['profit']['net_margin']) }}
                </p>
                <p class="mt-1 text-sm text-gray-500">Profit bersih / pendapatan Gunsas</p>
            </div>

            <div class="rounded-lg border border-l-4 border-gray-200 border-l-warning-500 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Profit + Inventory</p>
                <p class="mt-2 text-2xl font-semibold">{{ $money($insights['profit']['net_asset_position']) }}</p>
                <p class="mt-1 text-sm text-gray-500">Posisi aset, bukan laba bersih</p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Inventory Valuation</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Nilai Stok Tersisa</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['inventory']['amount']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Total KG Stok</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['total_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Buah Utuh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['buah_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Kupas Fresh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['fresh_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Durpas Frozen</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['inventory']['frozen_kg']) }}</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Biaya & Modal</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">HPP Penjualan</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['hpp_sales']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Expenses</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['expenses']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Loss Opname</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['opname_loss']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Loss Opname KG</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['total_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Modal Buah Avg</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['avg_modal_buah']) }} / Kg</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Modal Fresh Avg</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['avg_modal_fresh']) }} / Kg</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Modal Frozen Avg</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['costs']['avg_modal_frozen']) }} / Kg</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Retur Supplier</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Retur Diajukan</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['returns']['asset_submitted']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">KG Diajukan</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['returns']['submitted_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Refund Diterima</dt><dd class="shrink-0 text-right font-medium text-success-600">{{ $money($insights['returns']['refund_received']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Loss Final</dt><dd class="shrink-0 text-right font-medium text-danger-600">{{ $money($insights['returns']['loss_final']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">KG Ditolak Supplier</dt><dd class="shrink-0 text-right font-medium text-danger-600">{{ $kg($insights['returns']['rejected_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Klaim Pending</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['returns']['pending_asset']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">KG Pending</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['returns']['pending_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Jumlah Pending</dt><dd class="shrink-0 text-right font-medium">{{ $insights['returns']['pending_count'] }} retur</dd></div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Volume Terjual</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Buah Utuh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['sales']['buah_sold_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Kupas Fresh</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['sales']['fresh_sold_kg']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Durpas Frozen</dt><dd class="shrink-0 text-right font-medium">{{ $kg($insights['sales']['frozen_sold_kg']) }}</dd></div>
                    <div class="border-t border-gray-200 pt-3 dark:border-gray-800"></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Profit Kotor</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['profit']['gross_profit']) }}</dd></div>
                    <div class="flex items-start justify-between gap-4"><dt class="text-gray-500">Potongan TipTop</dt><dd class="shrink-0 text-right font-medium">{{ $money($insights['sales']['tiptop_cut']) }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-semibold">Peta Loss KG</h3>
                    <p class="mt-1 text-sm text-gray-500">Memisahkan loss yang mengurangi profit dan susut proses yang sudah masuk ke modal average.</p>
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

            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($insights['loss_breakdown']['items'] as $item)
                    <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800">
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

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Detail Loss Opname</h3>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4"><span class="text-gray-600 dark:text-gray-300">Buah Utuh</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['buah_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-4"><span class="text-gray-600 dark:text-gray-300">Kupas Fresh</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['fresh_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-4"><span class="text-gray-600 dark:text-gray-300">Durpas Frozen</span><span class="shrink-0 text-right font-medium">{{ $kg($insights['costs']['opname_loss_kg']['frozen_kg']) }}</span></div>
                    <div class="flex items-start justify-between gap-4 border-t border-gray-200 pt-3 dark:border-gray-800"><span class="font-medium">Total KG Hilang</span><span class="shrink-0 text-right font-semibold">{{ $kg($insights['costs']['opname_loss_kg']['total_kg']) }}</span></div>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Outlet Dengan Omset Tertinggi</h3>
                <div class="mt-4 space-y-3 text-sm">
                    @forelse ($insights['top_outlets'] as $outlet)
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-600 dark:text-gray-300">{{ $outlet['name'] }}</span>
                            <span class="font-medium">{{ $money($outlet['revenue']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada penjualan pada periode ini.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Kategori Expense Terbesar</h3>
                <div class="mt-4 space-y-3 text-sm">
                    @forelse ($insights['expense_categories'] as $expense)
                        <div class="flex justify-between gap-4">
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
