@php
    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $kg = fn ($value) => number_format((float) $value, 3, ',', '.') . ' Kg';
    $qty = fn ($value) => number_format((float) $value, 3, ',', '.');
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';
    $barWidth = fn ($value) => number_format(max(0, min(100, (float) $value)), 2, '.', '');
    $signedClass = fn ($value) => (float) $value < 0 ? 'negative' : ((float) $value > 0 ? 'positive' : '');
    $negativeClass = fn ($value) => (float) $value > 0 ? 'negative' : '';

    $filters = $insights['filters'] ?? [];
    $sales = $insights['sales'] ?? [];
    $profit = $insights['profit'] ?? [];
    $costs = $insights['costs'] ?? [];
    $returns = $insights['returns'] ?? [];
    $inventory = $insights['inventory'] ?? [];
    $shipments = $insights['shipments'] ?? [];
    $purchases = $insights['purchases'] ?? [];
    $production = $insights['production_efficiency'] ?? [];
    $loss = $insights['loss_breakdown'] ?? [];

    $period = \Carbon\Carbon::parse($filters['date_from'] ?? now()->startOfMonth())->format('d M Y')
        . ' - '
        . \Carbon\Carbon::parse($filters['date_until'] ?? now())->format('d M Y');

    $salesProductRows = collect($insights['sales_by_product'] ?? []);
    $salesProductTotals = [
        'kg' => $salesProductRows->sum(fn ($row) => (float) ($row['kg'] ?? 0)),
        'quantity' => $salesProductRows->sum(fn ($row) => (float) ($row['quantity'] ?? 0)),
        'gross_sales' => $salesProductRows->sum(fn ($row) => (float) ($row['gross_sales'] ?? 0)),
        'net_sales' => $salesProductRows->sum(fn ($row) => (float) ($row['net_sales'] ?? 0)),
        'gunsas_revenue' => $salesProductRows->sum(fn ($row) => (float) ($row['gunsas_revenue'] ?? 0)),
    ];
    $salesProductTotals['avg_price_per_kg'] = $salesProductTotals['kg'] > 0
        ? $salesProductTotals['net_sales'] / $salesProductTotals['kg']
        : 0;

    $profitOutletRows = collect($insights['profit_by_outlet'] ?? []);
    $profitOutletTotals = [
        'net_sales' => $profitOutletRows->sum(fn ($row) => (float) ($row['net_sales'] ?? 0)),
        'gunsas_revenue' => $profitOutletRows->sum(fn ($row) => (float) ($row['gunsas_revenue'] ?? 0)),
        'hpp_sales' => $profitOutletRows->sum(fn ($row) => (float) ($row['hpp_sales'] ?? 0)),
        'expenses' => $profitOutletRows->sum(fn ($row) => (float) ($row['expenses'] ?? 0)),
        'return_loss' => $profitOutletRows->sum(fn ($row) => (float) ($row['return_loss'] ?? 0)),
        'opname_loss' => $profitOutletRows->sum(fn ($row) => (float) ($row['opname_loss'] ?? 0)),
        'inventory_usage' => $profitOutletRows->sum(fn ($row) => (float) ($row['inventory_usage'] ?? 0)),
        'net_profit' => $profitOutletRows->sum(fn ($row) => (float) ($row['net_profit'] ?? 0)),
    ];
    $profitOutletTotals['net_margin'] = $profitOutletTotals['gunsas_revenue'] > 0
        ? ($profitOutletTotals['net_profit'] / $profitOutletTotals['gunsas_revenue']) * 100
        : 0;

    $returnRecovery = $returns['recovery'] ?? [];
    $returnRecoveryAmount = (float) ($profit['return_recovery_hpp_saved'] ?? 0);
    $profitAfterRecovery = (float) ($profit['net_profit'] ?? 0) + $returnRecoveryAmount;
    $assetPositionAfterRecovery = $profitAfterRecovery + (float) ($inventory['amount'] ?? 0);
    $totalProfitCosts = (float) ($costs['hpp_sales'] ?? 0)
        + (float) ($costs['expenses'] ?? 0)
        + (float) ($costs['inventory_usage'] ?? 0)
        + (float) ($returns['loss_final'] ?? 0)
        + (float) ($costs['opname_loss'] ?? 0);

    $weeklySales = collect(data_get($insights, 'trends.weekly_sales', []));
    $monthlySales = collect(data_get($insights, 'trends.monthly_sales', []));
    $shortMoney = function ($value): string {
        $value = (float) $value;
        $abs = abs($value);

        if ($abs >= 1000000000) {
            return 'Rp ' . number_format($value / 1000000000, 1, ',', '.') . ' M';
        }

        if ($abs >= 1000000) {
            return 'Rp ' . number_format($value / 1000000, 0, ',', '.') . ' jt';
        }

        if ($abs >= 1000) {
            return 'Rp ' . number_format($value / 1000, 0, ',', '.') . ' rb';
        }

        return 'Rp ' . number_format($value, 0, ',', '.');
    };
    $lineChart = function ($source, string $valueKey = 'net_sales'): array {
        $rows = collect($source)->values();
        $width = 720;
        $height = 220;
        $left = 58;
        $right = 24;
        $top = 22;
        $bottom = 42;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $max = max(1, $rows->max(fn ($row) => (float) ($row[$valueKey] ?? 0)) ?: 0);
        $count = max(1, $rows->count());
        $coords = $rows->map(function ($row, int $index) use ($count, $left, $top, $plotWidth, $plotHeight, $max, $valueKey) {
            $x = $left + ($count === 1 ? $plotWidth / 2 : ($index / ($count - 1)) * $plotWidth);
            $value = (float) ($row[$valueKey] ?? 0);
            $y = $top + $plotHeight - (($value / $max) * $plotHeight);

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'value' => $value,
                'label' => (string) ($row['label'] ?? ''),
                'period' => (string) ($row['period'] ?? ''),
            ];
        })->all();

        $points = collect($coords)->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
        $area = $coords
            ? $left . ',' . ($top + $plotHeight) . ' ' . $points . ' ' . ($left + $plotWidth) . ',' . ($top + $plotHeight)
            : '';
        $labelEvery = max(1, (int) ceil($count / 6));
        $labels = collect($coords)
            ->filter(fn ($point, int $index) => $index === 0 || $index === $count - 1 || $index % $labelEvery === 0)
            ->values()
            ->all();

        return compact('width', 'height', 'left', 'right', 'top', 'bottom', 'plotWidth', 'plotHeight', 'max', 'coords', 'points', 'area', 'labels');
    };
    $weeklyChart = $lineChart($weeklySales);
    $monthlyChart = $lineChart($monthlySales);
    $lossItems = collect($loss['items'] ?? []);
    $expenseCategories = collect($insights['expense_categories'] ?? []);
    $inventoryUsageItems = collect($costs['inventory_usage_items'] ?? []);
@endphp

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Business Insights</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        :root {
            color-scheme: light;
            --ink: #111827;
            --muted: #5f6b7a;
            --line: #d9e0ea;
            --soft: #f8fafc;
            --soft-2: #edf2f7;
            --orange: #e86f00;
            --blue: #0284c7;
            --danger: #dc2626;
            --success: #16a34a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f4f6;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5px;
            line-height: 1.35;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            justify-content: flex-end;
            padding: 10px 18px;
            background: rgba(255, 255, 255, .94);
            border-bottom: 1px solid var(--line);
        }

        .button {
            border: 0;
            border-radius: 8px;
            background: var(--orange);
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            padding: 9px 14px;
        }

        .page {
            width: 297mm;
            max-width: calc(100vw - 28px);
            margin: 14px auto;
            padding: 20px 24px;
            background: #fff;
            border: 1px solid var(--line);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--ink);
        }

        h1, h2, h3, p {
            margin: 0;
        }

        h1 {
            font-size: 24px;
            letter-spacing: 0;
        }

        h2 {
            margin-bottom: 3px;
            font-size: 15px;
        }

        h3 {
            font-size: 12px;
        }

        .muted {
            color: var(--muted);
        }

        .scope {
            margin-top: 6px;
            font-weight: 700;
        }

        .badge {
            display: inline-block;
            margin: 2px 0 2px 6px;
            padding: 5px 8px;
            border-radius: 8px;
            background: var(--soft-2);
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
        }

        .section {
            margin-top: 16px;
            page-break-inside: avoid;
        }

        .section-title {
            margin-bottom: 8px;
        }

        .grid {
            display: grid;
            gap: 10px;
        }

        .grid-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .grid-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .grid-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .card {
            min-width: 0;
            padding: 11px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
        }

        .card.soft {
            background: var(--soft);
        }

        .card-label {
            color: var(--muted);
            font-size: 10.5px;
        }

        .value {
            margin-top: 4px;
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
        }

        .card-note {
            margin-top: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 4px 0;
        }

        .line strong {
            white-space: nowrap;
        }

        .positive {
            color: var(--success);
        }

        .negative {
            color: var(--danger);
        }

        .chart {
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            page-break-inside: avoid;
        }

        .chart-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .chart-total {
            color: var(--orange);
            font-size: 14px;
            font-weight: 800;
            text-align: right;
            white-space: nowrap;
        }

        .svg-chart {
            display: block;
            width: 100%;
            height: 210px;
            margin-top: 4px;
        }

        .axis-label {
            fill: var(--muted);
            font-size: 8.5px;
        }

        .chart-line {
            fill: none;
            stroke: var(--orange);
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .chart-line.blue {
            stroke: var(--blue);
        }

        .chart-area {
            fill: rgba(232, 111, 0, .14);
        }

        .chart-area.blue {
            fill: rgba(2, 132, 199, .14);
        }

        .chart-dot {
            fill: #fff;
            stroke: var(--orange);
            stroke-width: 3;
        }

        .chart-dot.blue {
            stroke: var(--blue);
        }

        .chart-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 10px;
        }

        .chart-stat {
            padding: 8px;
            border-radius: 8px;
            background: var(--soft);
        }

        .chart-stat span {
            display: block;
            color: var(--muted);
            font-size: 9px;
        }

        .chart-stat strong {
            display: block;
            margin-top: 2px;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th {
            padding: 8px 8px;
            background: var(--ink);
            color: #fff;
            font-size: 9.5px;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            padding: 7px 8px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
            word-break: break-word;
        }

        tfoot td {
            background: var(--soft);
            font-weight: 800;
        }

        .right {
            text-align: right;
        }

        .nowrap {
            white-space: nowrap;
        }

        .split {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .compact-list {
            display: grid;
            gap: 5px;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--line);
        }

        .formula {
            padding: 10px 12px;
            border-left: 4px solid var(--orange);
            background: var(--soft);
            color: #334155;
        }

        @media print {
            body {
                background: #fff;
            }

            .no-print {
                display: none;
            }

            .page {
                width: auto;
                max-width: none;
                margin: 0;
                padding: 0;
                border: 0;
            }

            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="page">
        <header class="header">
            <div>
                <h1>Laporan Business Insights</h1>
                <p class="muted">{{ $period }}</p>
                <p class="scope">
                    {{ $filters['outlet_name'] ?? 'Semua Outlet' }}
                    @if (! empty($filters['durian_variety_name']))
                        / {{ $filters['durian_variety_name'] }}
                    @endif
                    @if (! empty($filters['product_label']))
                        / {{ $filters['product_label'] }}
                    @endif
                </p>
            </div>
            <div class="right">
                <span class="badge">Bagi hasil rata-rata {{ $percent($sales['partner_share_percent'] ?? 0) }}</span>
                <span class="badge">Dibuat {{ now()->format('d M Y H:i') }}</span>
                <p class="muted" style="margin-top: 8px; max-width: 360px;">
                    Profit bersih membaca operasional periode. Inventory valuation ditampilkan sebagai nilai aset stok akhir, bukan laba kas.
                </p>
            </div>
        </header>

        <section class="section">
            <div class="section-title">
                <h2>Penjualan</h2>
                <p class="muted">Urutan membaca: omset kasir, potongan, lalu bagian Gunsas.</p>
            </div>
            <div class="grid grid-3">
                <div class="card">
                    <p class="card-label">1. Omset kasir</p>
                    <p class="value">{{ $money($sales['gross_sales'] ?? 0) }}</p>
                    <p class="card-note">Total penjualan sebelum potongan.</p>
                </div>
                <div class="card">
                    <p class="card-label">2. Sales net</p>
                    <p class="value">{{ $money($sales['net_sales'] ?? 0) }}</p>
                    <p class="card-note">
                        Diskon {{ $money($sales['discount_amount'] ?? 0) }}
                        @if ((float) ($sales['sales_return_amount'] ?? 0) > 0)
                            / sales return {{ $money($sales['sales_return_amount'] ?? 0) }}
                        @endif
                    </p>
                </div>
                <div class="card">
                    <p class="card-label">3. Bagian Gunsas</p>
                    <p class="value">{{ $money($sales['gunsas_revenue'] ?? 0) }}</p>
                    <p class="card-note">Setelah bagi hasil partner.</p>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-title">
                <h2>Profit Periode</h2>
                <p class="muted">Fokus ke laba/rugi operasional periode yang dipilih.</p>
            </div>
            <div class="grid grid-4">
                <div class="card soft">
                    <p class="card-label">Profit bersih</p>
                    <p class="value {{ $signedClass($profit['net_profit'] ?? 0) }}">{{ $money($profit['net_profit'] ?? 0) }}</p>
                    <p class="card-note">Bagian Gunsas dikurangi semua beban profit.</p>
                </div>
                <div class="card soft">
                    <p class="card-label">Margin bersih</p>
                    <p class="value {{ $signedClass($profit['net_margin'] ?? 0) }}">{{ $percent($profit['net_margin'] ?? 0) }}</p>
                    <p class="card-note">Profit bersih / bagian Gunsas.</p>
                </div>
                <div class="card soft">
                    <p class="card-label">Profit setelah recovery retur</p>
                    <p class="value {{ $signedClass($profitAfterRecovery) }}">{{ $money($profitAfterRecovery) }}</p>
                    <p class="card-note">Recovery retur {{ $money($returnRecoveryAmount) }}.</p>
                </div>
                <div class="card soft">
                    <p class="card-label">Profit + nilai stok setelah recovery</p>
                    <p class="value {{ $signedClass($assetPositionAfterRecovery) }}">{{ $money($assetPositionAfterRecovery) }}</p>
                    <p class="card-note">Profit recovery ditambah inventory valuation.</p>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-title">
                <h2>Beban, Stok, dan Barang Masuk</h2>
                <p class="muted">Bagian ini dipakai untuk menjelaskan kenapa profit naik atau turun.</p>
            </div>
            <div class="grid grid-3">
                <div class="card">
                    <p class="card-label">Total pengurang profit</p>
                    <p class="value">{{ $money($totalProfitCosts) }}</p>
                    <div class="line"><span>HPP</span><strong>{{ $money($costs['hpp_sales'] ?? 0) }}</strong></div>
                    <div class="line"><span>Expense</span><strong>{{ $money($costs['expenses'] ?? 0) }}</strong></div>
                    <div class="line"><span>Inventory terpakai</span><strong>{{ $money($costs['inventory_usage'] ?? 0) }}</strong></div>
                    <div class="line"><span>Loss retur + opname</span><strong class="{{ $negativeClass(($returns['loss_final'] ?? 0) + ($costs['opname_loss'] ?? 0)) }}">{{ $money(($returns['loss_final'] ?? 0) + ($costs['opname_loss'] ?? 0)) }}</strong></div>
                </div>
                <div class="card">
                    <p class="card-label">Inventory valuation</p>
                    <p class="value">{{ $money($inventory['amount'] ?? 0) }}</p>
                    <div class="line"><span>Buah utuh</span><strong>{{ $kg($inventory['buah_kg'] ?? 0) }}</strong></div>
                    <div class="line"><span>Kupas fresh</span><strong>{{ $kg($inventory['fresh_kg'] ?? 0) }}</strong></div>
                    <div class="line"><span>Durpas frozen</span><strong>{{ $kg($inventory['frozen_kg'] ?? 0) }}</strong></div>
                </div>
                <div class="card">
                    <p class="card-label">Barang dikirim ke outlet</p>
                    <p class="value">{{ $money($shipments['total_modal'] ?? 0) }}</p>
                    <div class="line"><span>Berat durian</span><strong>{{ $kg($shipments['total_kg'] ?? 0) }}</strong></div>
                    <div class="line"><span>Butir durian</span><strong>{{ $qty($shipments['total_butir'] ?? 0) }} Btr</strong></div>
                    <div class="line"><span>Jumlah kiriman</span><strong>{{ number_format((int) ($shipments['count'] ?? 0), 0, ',', '.') }} kiriman</strong></div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-title">
                <h2>Tren Penjualan</h2>
                <p class="muted">Grafik memakai sales net. Mingguan menampilkan 4 minggu terakhir, bulanan menampilkan 4 bulan terakhir sampai tanggal akhir filter.</p>
            </div>
            <div class="grid grid-2">
                <div class="chart">
                    <div class="chart-head">
                        <div>
                            <h3>Penjualan Mingguan</h3>
                            <p class="muted">4 minggu terakhir dari tanggal akhir filter.</p>
                        </div>
                        <div class="chart-total">{{ $money($weeklySales->sum('net_sales')) }}</div>
                    </div>
                    @if ($weeklySales->isNotEmpty())
                        <svg class="svg-chart" viewBox="0 0 {{ $weeklyChart['width'] }} {{ $weeklyChart['height'] }}" role="img" aria-label="Grafik penjualan mingguan">
                            @foreach ([0, .25, .5, .75, 1] as $tick)
                                @php
                                    $y = $weeklyChart['top'] + (1 - $tick) * $weeklyChart['plotHeight'];
                                    $tickValue = $weeklyChart['max'] * $tick;
                                @endphp
                                <line x1="{{ $weeklyChart['left'] }}" y1="{{ $y }}" x2="{{ $weeklyChart['left'] + $weeklyChart['plotWidth'] }}" y2="{{ $y }}" stroke="#e5e7eb" stroke-width="1" />
                                <text x="0" y="{{ $y + 3 }}" class="axis-label">{{ $shortMoney($tickValue) }}</text>
                            @endforeach
                            <line x1="{{ $weeklyChart['left'] }}" y1="{{ $weeklyChart['top'] + $weeklyChart['plotHeight'] }}" x2="{{ $weeklyChart['left'] + $weeklyChart['plotWidth'] }}" y2="{{ $weeklyChart['top'] + $weeklyChart['plotHeight'] }}" stroke="#94a3b8" stroke-width="1.2" />
                            <polygon points="{{ $weeklyChart['area'] }}" class="chart-area" />
                            <polyline points="{{ $weeklyChart['points'] }}" class="chart-line" />
                            @foreach ($weeklyChart['coords'] as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" class="chart-dot" />
                            @endforeach
                            @foreach ($weeklyChart['labels'] as $point)
                                <text x="{{ $point['x'] }}" y="{{ $weeklyChart['height'] - 12 }}" text-anchor="middle" class="axis-label">{{ $point['label'] }}</text>
                            @endforeach
                        </svg>
                        <div class="chart-stats">
                            <div class="chart-stat"><span>Tertinggi</span><strong>{{ $money($weeklySales->max('net_sales') ?? 0) }}</strong></div>
                            <div class="chart-stat"><span>Rata-rata</span><strong>{{ $money($weeklySales->avg('net_sales') ?? 0) }}</strong></div>
                            <div class="chart-stat"><span>Total KG</span><strong>{{ $kg($weeklySales->sum('kg')) }}</strong></div>
                        </div>
                    @else
                        <p class="muted" style="margin-top: 8px;">Belum ada data penjualan.</p>
                    @endif
                </div>
                <div class="chart">
                    <div class="chart-head">
                        <div>
                            <h3>Perbandingan Bulanan</h3>
                            <p class="muted">4 bulan terakhir sampai bulan tanggal akhir filter.</p>
                        </div>
                        <div class="chart-total">{{ $money($monthlySales->sum('net_sales')) }}</div>
                    </div>
                    @if ($monthlySales->isNotEmpty())
                        <svg class="svg-chart" viewBox="0 0 {{ $monthlyChart['width'] }} {{ $monthlyChart['height'] }}" role="img" aria-label="Grafik penjualan bulanan">
                            @foreach ([0, .25, .5, .75, 1] as $tick)
                                @php
                                    $y = $monthlyChart['top'] + (1 - $tick) * $monthlyChart['plotHeight'];
                                    $tickValue = $monthlyChart['max'] * $tick;
                                @endphp
                                <line x1="{{ $monthlyChart['left'] }}" y1="{{ $y }}" x2="{{ $monthlyChart['left'] + $monthlyChart['plotWidth'] }}" y2="{{ $y }}" stroke="#e5e7eb" stroke-width="1" />
                                <text x="0" y="{{ $y + 3 }}" class="axis-label">{{ $shortMoney($tickValue) }}</text>
                            @endforeach
                            <line x1="{{ $monthlyChart['left'] }}" y1="{{ $monthlyChart['top'] + $monthlyChart['plotHeight'] }}" x2="{{ $monthlyChart['left'] + $monthlyChart['plotWidth'] }}" y2="{{ $monthlyChart['top'] + $monthlyChart['plotHeight'] }}" stroke="#94a3b8" stroke-width="1.2" />
                            <polygon points="{{ $monthlyChart['area'] }}" class="chart-area blue" />
                            <polyline points="{{ $monthlyChart['points'] }}" class="chart-line blue" />
                            @foreach ($monthlyChart['coords'] as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" class="chart-dot blue" />
                            @endforeach
                            @foreach ($monthlyChart['labels'] as $point)
                                <text x="{{ $point['x'] }}" y="{{ $monthlyChart['height'] - 12 }}" text-anchor="middle" class="axis-label">{{ $point['label'] }}</text>
                            @endforeach
                        </svg>
                        <div class="chart-stats">
                            <div class="chart-stat"><span>Tertinggi</span><strong>{{ $money($monthlySales->max('net_sales') ?? 0) }}</strong></div>
                            <div class="chart-stat"><span>Rata-rata</span><strong>{{ $money($monthlySales->avg('net_sales') ?? 0) }}</strong></div>
                            <div class="chart-stat"><span>Total KG</span><strong>{{ $kg($monthlySales->sum('kg')) }}</strong></div>
                        </div>
                    @else
                        <p class="muted" style="margin-top: 8px;">Belum ada data penjualan.</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-title">
                <h2>Penjualan Per Produk</h2>
                <p class="muted">Top produk berdasarkan sales net. Baris total mencakup semua produk pada filter.</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 24%;">Produk</th>
                        <th style="width: 12%;">Kategori</th>
                        <th style="width: 12%;">Varian</th>
                        <th class="right" style="width: 13%;">Qty</th>
                        <th class="right" style="width: 13%;">Omset</th>
                        <th class="right" style="width: 13%;">Sales Net</th>
                        <th class="right" style="width: 13%;">Bagian Gunsas</th>
                        <th class="right" style="width: 10%;">Avg</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesProductRows->take(10) as $row)
                        @php
                            $isInventoryProduct = ($row['category'] ?? null) === 'Produk Jualan';
                            $unit = $row['unit'] ?? 'unit';
                            $displayQty = $isInventoryProduct
                                ? $qty($row['quantity'] ?? 0) . ' ' . $unit
                                : $kg($row['kg'] ?? 0);
                            $displayAvg = $isInventoryProduct
                                ? $money($row['avg_price_per_unit'] ?? 0) . ' / ' . $unit
                                : $money($row['avg_price_per_kg'] ?? 0) . ' / Kg';
                        @endphp
                        <tr>
                            <td><strong>{{ $row['product'] ?? '-' }}</strong></td>
                            <td>{{ $row['category'] ?? '-' }}</td>
                            <td>{{ $row['variety'] ?? '-' }}</td>
                            <td class="right nowrap">{{ $displayQty }}</td>
                            <td class="right nowrap">{{ $money($row['gross_sales'] ?? 0) }}</td>
                            <td class="right nowrap">{{ $money($row['net_sales'] ?? 0) }}</td>
                            <td class="right nowrap">{{ $money($row['gunsas_revenue'] ?? 0) }}</td>
                            <td class="right nowrap">{{ $displayAvg }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">Belum ada penjualan pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="right">{{ $kg($salesProductTotals['kg']) }}</td>
                        <td class="right">{{ $money($salesProductTotals['gross_sales']) }}</td>
                        <td class="right">{{ $money($salesProductTotals['net_sales']) }}</td>
                        <td class="right">{{ $money($salesProductTotals['gunsas_revenue']) }}</td>
                        <td class="right">{{ $money($salesProductTotals['avg_price_per_kg']) }} / Kg</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <section class="section">
            <div class="section-title">
                <h2>Profit Per Outlet</h2>
                <p class="muted">Expense pusat/global tidak dibebankan ke outlet kecuali ada scope alokasi.</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 18%;">Outlet</th>
                        <th class="right">Sales Net</th>
                        <th class="right">Bagian Gunsas</th>
                        <th class="right">HPP</th>
                        <th class="right">Expense</th>
                        <th class="right">Retur + Opname</th>
                        <th class="right">Inventory</th>
                        <th class="right">Profit</th>
                        <th class="right">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profitOutletRows->take(10) as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['outlet_name'] ?? '-' }}</strong><br>
                                <span class="muted">{{ $row['group_name'] ?? '-' }}</span>
                            </td>
                            <td class="right nowrap">{{ $money($row['net_sales'] ?? 0) }}</td>
                            <td class="right nowrap">{{ $money($row['gunsas_revenue'] ?? 0) }}</td>
                            <td class="right nowrap">{{ $money($row['hpp_sales'] ?? 0) }}</td>
                            <td class="right nowrap">{{ $money($row['expenses'] ?? 0) }}</td>
                            <td class="right nowrap {{ $negativeClass(($row['return_loss'] ?? 0) + ($row['opname_loss'] ?? 0)) }}">
                                {{ $money(($row['return_loss'] ?? 0) + ($row['opname_loss'] ?? 0)) }}
                            </td>
                            <td class="right nowrap">{{ $money($row['inventory_usage'] ?? 0) }}</td>
                            <td class="right nowrap {{ $signedClass($row['net_profit'] ?? 0) }}">{{ $money($row['net_profit'] ?? 0) }}</td>
                            <td class="right nowrap {{ $signedClass($row['net_margin'] ?? 0) }}">{{ $percent($row['net_margin'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="muted">Belum ada data outlet pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="right">{{ $money($profitOutletTotals['net_sales']) }}</td>
                        <td class="right">{{ $money($profitOutletTotals['gunsas_revenue']) }}</td>
                        <td class="right">{{ $money($profitOutletTotals['hpp_sales']) }}</td>
                        <td class="right">{{ $money($profitOutletTotals['expenses']) }}</td>
                        <td class="right {{ $negativeClass($profitOutletTotals['return_loss'] + $profitOutletTotals['opname_loss']) }}">
                            {{ $money($profitOutletTotals['return_loss'] + $profitOutletTotals['opname_loss']) }}
                        </td>
                        <td class="right">{{ $money($profitOutletTotals['inventory_usage']) }}</td>
                        <td class="right {{ $signedClass($profitOutletTotals['net_profit']) }}">{{ $money($profitOutletTotals['net_profit']) }}</td>
                        <td class="right {{ $signedClass($profitOutletTotals['net_margin']) }}">{{ $percent($profitOutletTotals['net_margin']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <section class="section split">
            <div class="card">
                <h2>Retur Supplier</h2>
                <div class="line"><span>Retur diajukan</span><strong>{{ $money($returns['submitted_amount'] ?? 0) }}</strong></div>
                <div class="line"><span>KG diajukan</span><strong>{{ $kg($returns['submitted_kg'] ?? 0) }}</strong></div>
                <div class="line"><span>Refund diterima</span><strong>{{ $money($returns['refund_received'] ?? 0) }}</strong></div>
                <div class="line"><span>Sisa rugi setelah refund</span><strong class="{{ $negativeClass($returns['loss_final'] ?? 0) }}">{{ $money($returns['loss_final'] ?? 0) }}</strong></div>
                <div class="line"><span>KG ditolak supplier</span><strong class="{{ $negativeClass($returns['rejected_kg'] ?? 0) }}">{{ $kg($returns['rejected_kg'] ?? 0) }}</strong></div>
                <hr>
                <div class="line"><span>Fresh dari retur</span><strong>{{ $kg($returnRecovery['fresh_kg'] ?? 0) }}</strong></div>
                <div class="line"><span>Olahan dari retur</span><strong>{{ $kg($returnRecovery['olahan_kg'] ?? 0) }}</strong></div>
                <div class="line"><span>Recovery retur</span><strong>{{ $money($returnRecoveryAmount) }}</strong></div>
            </div>
            <div class="card">
                <h2>Detail Loss Opname</h2>
                <div class="line"><span>Buah utuh</span><strong>{{ $kg(data_get($costs, 'opname_loss_kg.buah_kg', 0)) }}</strong></div>
                <div class="line"><span>Kupas fresh</span><strong>{{ $kg(data_get($costs, 'opname_loss_kg.fresh_kg', 0)) }}</strong></div>
                <div class="line"><span>Durpas frozen</span><strong>{{ $kg(data_get($costs, 'opname_loss_kg.frozen_kg', 0)) }}</strong></div>
                <div class="line"><span>Total KG hilang</span><strong>{{ $kg(data_get($costs, 'opname_loss_kg.total_kg', 0)) }}</strong></div>
                <div class="line"><span>Koreksi stok plus</span><strong>{{ $kg(data_get($costs, 'opname_loss_kg.positive_correction_kg', 0)) }}</strong></div>
                <div class="line"><span>Nilai koreksi plus</span><strong>{{ $money(data_get($costs, 'opname_loss_kg.positive_correction_amount', 0)) }}</strong></div>
                <div class="line"><span>Net loss opname</span><strong class="{{ $negativeClass($costs['opname_loss'] ?? 0) }}">{{ $money($costs['opname_loss'] ?? 0) }}</strong></div>
                @if ($lossItems->isNotEmpty())
                    <hr>
                    @foreach ($lossItems->take(5) as $item)
                        <div class="line">
                            <span>{{ $item['label'] ?? '-' }}</span>
                            <strong>{{ $kg($item['kg'] ?? 0) }}</strong>
                        </div>
                    @endforeach
                @endif
            </div>
        </section>

        <section class="section split">
            <div class="card">
                <h2>Expense Terbesar</h2>
                <div class="compact-list">
                    @forelse ($expenseCategories as $row)
                        <div class="list-item">
                            <span>{{ $row['category'] ?? '-' }}</span>
                            <strong>{{ $money($row['total'] ?? 0) }}</strong>
                        </div>
                    @empty
                        <p class="muted">Belum ada expense pada periode ini.</p>
                    @endforelse
                </div>
            </div>
            <div class="card">
                <h2>Purchase dan Inventory Terpakai</h2>
                <div class="line"><span>Total purchase</span><strong>{{ $money($purchases['total_amount'] ?? 0) }}</strong></div>
                <div class="line"><span>Total KG durian</span><strong>{{ $kg($purchases['total_kg'] ?? 0) }}</strong></div>
                <div class="line"><span>Avg purchase/Kg</span><strong>{{ $money($purchases['avg_price_per_kg'] ?? 0) }} / Kg</strong></div>
                <hr>
                @forelse ($inventoryUsageItems->take(5) as $item)
                    <div class="line">
                        <span>{{ $item['name'] ?? '-' }} ({{ $qty($item['qty'] ?? 0) }} {{ $item['unit'] ?? 'unit' }})</span>
                        <strong>{{ $money($item['amount'] ?? 0) }}</strong>
                    </div>
                @empty
                    <p class="muted">Belum ada inventory terpakai pada periode ini.</p>
                @endforelse
            </div>
        </section>

        <section class="section split">
            <div class="card">
                <h2>Efisiensi Produksi</h2>
                <div class="line"><span>Produksi tercatat</span><strong>{{ number_format((int) ($production['count'] ?? 0), 0, ',', '.') }} batch</strong></div>
                <div class="line"><span>Input buah utuh</span><strong>{{ $kg($production['input_buah_kg'] ?? 0) }}</strong></div>
                <div class="line"><span>Total daging diperoleh</span><strong>{{ $kg($production['total_meat_kg'] ?? 0) }}</strong></div>
                <div class="line"><span>Kupas fresh</span><strong>{{ $kg($production['fresh_kg'] ?? 0) }} / {{ $percent($production['fresh_yield_percent'] ?? 0) }}</strong></div>
                <div class="line"><span>Olahan / reject</span><strong>{{ $kg($production['olahan_kg'] ?? 0) }} / {{ $percent($production['olahan_yield_percent'] ?? 0) }}</strong></div>
                <div class="line"><span>Pengkali produksi fisik</span><strong>{{ number_format((float) ($production['multiplier_factor'] ?? 0), 2, ',', '.') }}x</strong></div>
            </div>
            <div class="formula">
                <h2>Catatan Perhitungan</h2>
                <p>Sales net = omset kasir - diskon - sales return.</p>
                <p>Bagian Gunsas = sales net setelah bagi hasil partner.</p>
                <p>Profit bersih = bagian Gunsas - HPP - expense - inventory terpakai - loss retur final - loss opname.</p>
                <p>Recovery retur ditampilkan terpisah agar terlihat berapa nilai modal yang berhasil diselamatkan dari barang retur.</p>
                <p>Inventory valuation adalah estimasi nilai stok tersisa pada akhir periode, dibaca sebagai aset.</p>
            </div>
        </section>
    </main>
</body>
</html>
