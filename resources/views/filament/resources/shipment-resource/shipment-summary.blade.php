@php
    $qty = fn (float $value, int $decimals = 3): string => number_format($value, $decimals, ',', '.');
    $money = fn (float $value): string => 'IDR ' . number_format($value, 2, '.', ',');
    $items = [
        ['label' => 'Total Kirim', 'value' => $qty((float) $summary['sent_butir'], 0) . ' btr / ' . $qty((float) $summary['sent_kg']) . ' Kg'],
        ['label' => 'Total Terima', 'value' => $qty((float) $summary['received_butir'], 0) . ' btr / ' . $qty((float) $summary['received_kg']) . ' Kg'],
        ['label' => 'Produk Inventory', 'value' => 'Kirim ' . $qty((float) $summary['item_sent']) . ' / Terima ' . $qty((float) $summary['item_received'])],
        ['label' => 'Avg Berat Buah', 'value' => $qty((float) $summary['avg_weight']) . ' Kg'],
        ['label' => 'Total Modal', 'value' => $money((float) $summary['total_modal'])],
    ];
@endphp

@include('filament.components.table-summary-row', ['items' => $items])
