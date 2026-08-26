<x-filament-panels::page>
    @php
        $snapshot = $this->snapshot;
        $summary = $snapshot['summary'] ?? [];
        $rows = $snapshot['rows'] ?? [];
        $dateFromLabel = \Carbon\Carbon::parse($snapshot['filters']['date_from'] ?? $snapshot['filters']['date'] ?? now())->format('d M Y');
        $dateUntilLabel = \Carbon\Carbon::parse($snapshot['filters']['date_until'] ?? $snapshot['filters']['date'] ?? now())->format('d M Y');
        $dateLabel = $dateFromLabel === $dateUntilLabel ? $dateFromLabel : $dateFromLabel . ' - ' . $dateUntilLabel;
        $qty = fn ($value, string $unit = 'Kg') => number_format((float) $value, 3, ',', '.') . ' ' . $unit;
    @endphp

    <div class="space-y-4">
        <form wire:submit.prevent="applyFilters" class="space-y-3">
            {{ $this->form }}

            <div class="gunsas-action-row">
                <button
                    type="button"
                    wire:click="export"
                    wire:loading.attr="disabled"
                    wire:target="export"
                    class="gunsas-action-button rounded-lg bg-success-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-success-500 disabled:opacity-70"
                >
                    <span wire:loading.remove wire:target="export">Download Excel</span>
                    <span wire:loading wire:target="export">Menyiapkan...</span>
                </button>

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

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs text-gray-500">Posisi Stok</p>
                    <h2 class="text-xl font-semibold text-gray-950 dark:text-white">{{ $dateLabel }}</h2>
                    <p class="mt-1 text-sm text-gray-500">Stok awal dihitung sampai sebelum tanggal awal. Masuk, terjual, dan keluar lain dihitung selama periode. Opname fisik dibaca dari tanggal akhir jika ada. Olahan/jelek ditampilkan sebagai info produksi dan tidak mengurangi stok lagi. Ringkasan atas khusus produk durian berbasis KG.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-xs text-gray-500">Stok Awal</p>
                    <p class="font-semibold">{{ $qty($summary['start_qty'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-xs text-gray-500">Masuk</p>
                    <p class="font-semibold text-success-600">{{ $qty($summary['in_qty'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-xs text-gray-500">Terjual</p>
                    <p class="font-semibold">{{ $qty($summary['sold_qty'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-xs text-gray-500">Keluar Lain</p>
                    <p class="font-semibold">{{ $qty($summary['other_out_qty'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-xs text-gray-500">Olahan/Jelek</p>
                    <p class="font-semibold text-warning-600">{{ $qty($summary['olahan_reject_qty'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-xs text-gray-500">Stok Akhir</p>
                    <p class="font-semibold {{ ($summary['end_qty'] ?? 0) < 0 ? 'text-danger-600' : '' }}">{{ $qty($summary['end_qty'] ?? 0) }}</p>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                    <p class="text-xs text-gray-500">Selisih Opname</p>
                    <p class="font-semibold {{ ($summary['variance_qty'] ?? 0) < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $qty($summary['variance_qty'] ?? 0) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Detail Mutasi Stok</h3>
                <p class="mt-1 text-xs text-gray-500">Keluar lain berisi retur, produksi/konversi, pengiriman balik gudang, atau inventory terpakai. Olahan/jelek hanya info hasil produksi, bukan pengurang stok tambahan.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1240px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800">
                        <tr>
                            <th class="px-4 py-3 font-medium">Outlet</th>
                            <th class="px-4 py-3 font-medium">Produk</th>
                            <th class="px-4 py-3 text-right font-medium">Stok Awal</th>
                            <th class="px-4 py-3 text-right font-medium">Masuk</th>
                            <th class="px-4 py-3 text-right font-medium">Terjual</th>
                            <th class="px-4 py-3 text-right font-medium">Keluar Lain</th>
                            <th class="px-4 py-3 text-right font-medium">Olahan/Jelek</th>
                            <th class="px-4 py-3 text-right font-medium">Stok Akhir</th>
                            <th class="px-4 py-3 text-right font-medium">Opname Fisik</th>
                            <th class="px-4 py-3 text-right font-medium">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            @php
                                $unit = $row['unit'] ?? 'Kg';
                                $variance = $row['variance_qty'];
                                $detail = $row['detail'] ?? [];
                                $olahanRejectQty = (float) ($detail['olahan_reject'] ?? 0);
                                $detailParts = collect([
                                    ($detail['shipment_in'] ?? 0) > 0 ? 'Kirim masuk ' . $qty($detail['shipment_in'], $unit) : null,
                                    ($detail['production_in'] ?? 0) > 0 ? 'Produksi masuk ' . $qty($detail['production_in'], $unit) : null,
                                    ($detail['conversion_in'] ?? 0) > 0 ? 'Konversi masuk ' . $qty($detail['conversion_in'], $unit) : null,
                                    ($detail['shipment_out'] ?? 0) > 0 ? 'Balik gudang ' . $qty($detail['shipment_out'], $unit) : null,
                                    ($detail['return'] ?? 0) > 0 ? 'Retur ' . $qty($detail['return'], $unit) : null,
                                    ($detail['production_out'] ?? 0) > 0 ? 'Diproduksi ' . $qty($detail['production_out'], $unit) : null,
                                    ($detail['conversion_out'] ?? 0) > 0 ? 'Dikonversi ' . $qty($detail['conversion_out'], $unit) : null,
                                    ($detail['consumed'] ?? 0) > 0 ? 'Terpakai ' . $qty($detail['consumed'], $unit) : null,
                                ])->filter()->join(' | ');
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $row['outlet_name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $row['group_name'] }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $row['product_name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $row['product_type'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ $qty($row['start_qty'], $unit) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-success-600">{{ $qty($row['in_qty'], $unit) }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ $qty($row['sold_qty'], $unit) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="font-medium">{{ $qty($row['other_out_qty'], $unit) }}</div>
                                    @if ($detailParts !== '')
                                        <div class="text-xs text-gray-500">{{ $detailParts }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-warning-600">
                                    {{ $olahanRejectQty > 0 ? $qty($olahanRejectQty, $unit) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold {{ $row['end_qty'] < 0 ? 'text-danger-600' : 'text-gray-950 dark:text-white' }}">{{ $qty($row['end_qty'], $unit) }}</td>
                                <td class="px-4 py-3 text-right font-medium">
                                    {{ $row['physical_qty'] === null ? '-' : $qty($row['physical_qty'], $unit) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold {{ ($variance ?? 0) < 0 ? 'text-danger-600' : 'text-success-600' }}">
                                    {{ $variance === null ? '-' : $qty($variance, $unit) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">
                                    Belum ada data stok pada filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
