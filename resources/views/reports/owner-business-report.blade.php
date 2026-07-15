@php
    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $kg = fn ($value) => number_format((float) $value, 3, ',', '.') . ' Kg';
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';
    $period = \Carbon\Carbon::parse($insights['filters']['date_from'])->format('d M Y') . ' - ' . \Carbon\Carbon::parse($insights['filters']['date_until'])->format('d M Y');
    $largestExpense = collect($insights['expense_categories'] ?? [])->sortByDesc('total')->first();
    $worstOutlet = collect($insights['profit_by_outlet'] ?? [])->sortBy('net_profit')->first();
    $bestOutlet = collect($insights['profit_by_outlet'] ?? [])->sortByDesc('net_profit')->first();
    $notes = [];

    $notes[] = $insights['profit']['net_profit'] >= 0
        ? 'Profit periode ini positif ' . $money($insights['profit']['net_profit']) . ' dengan margin ' . $percent($insights['profit']['net_margin']) . '.'
        : 'Profit periode ini masih rugi ' . $money(abs($insights['profit']['net_profit'])) . '; cek HPP, expense, retur, dan loss opname.';

    if ($largestExpense) {
        $notes[] = 'Expense terbesar adalah ' . ($largestExpense['category'] ?? '-') . ' sebesar ' . $money($largestExpense['total'] ?? 0) . '.';
    }

    if (($insights['returns']['pending_count'] ?? 0) > 0) {
        $notes[] = 'Masih ada ' . $insights['returns']['pending_count'] . ' klaim retur pending senilai ' . $money($insights['returns']['pending_asset']) . '.';
    }

    if (($insights['inventory']['amount'] ?? 0) > 0) {
        $notes[] = 'Stok tersisa bernilai ' . $money($insights['inventory']['amount']) . '; ini aset terpisah, bukan laba bersih.';
    }

    if ($bestOutlet) {
        $notes[] = 'Outlet terbaik periode ini: ' . ($bestOutlet['outlet_name'] ?? '-') . ' dengan profit ' . $money($bestOutlet['net_profit'] ?? 0) . '.';
    }

    if ($worstOutlet && ($worstOutlet['net_profit'] ?? 0) < 0) {
        $notes[] = 'Outlet yang perlu perhatian: ' . ($worstOutlet['outlet_name'] ?? '-') . ' rugi ' . $money(abs($worstOutlet['net_profit'] ?? 0)) . '.';
    }
@endphp

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Bisnis Gunsas</title>
    <style>
        :root {
            color-scheme: light;
            --orange: #e86f00;
            --ink: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --soft: #f8fafc;
            --danger: #dc2626;
            --success: #16a34a;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f4f6;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, .92);
            border-bottom: 1px solid var(--line);
        }

        .button {
            border: 0;
            border-radius: 8px;
            background: var(--orange);
            color: white;
            cursor: pointer;
            font-weight: 700;
            padding: 9px 14px;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 18mm;
            background: white;
            box-shadow: 0 10px 35px rgba(15, 23, 42, .12);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 3px solid var(--orange);
            padding-bottom: 14px;
        }

        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 24px; line-height: 1.1; }
        h2 { font-size: 15px; margin-bottom: 8px; }
        .muted { color: var(--muted); }
        .right { text-align: right; }
        .positive { color: var(--success); }
        .negative { color: var(--danger); }

        .section { margin-top: 18px; }
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .card {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px;
            background: var(--soft);
        }
        .label {
            color: var(--muted);
            font-size: 11px;
            margin-bottom: 4px;
        }
        .value {
            font-size: 18px;
            font-weight: 800;
        }

        .notes {
            border: 1px solid #fed7aa;
            border-radius: 10px;
            background: #fff7ed;
            padding: 12px 14px;
        }
        .notes li { margin: 5px 0; }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #111827;
            color: white;
            font-size: 10px;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        th, td {
            border-bottom: 1px solid var(--line);
            padding: 7px 8px;
            vertical-align: top;
        }
        td.num, th.num { text-align: right; white-space: nowrap; }

        .two-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .section { break-inside: avoid; }
            @page { margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="button" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="page">
        <header class="header">
            <div>
                <p class="muted">Gunsas Jaya Berkah</p>
                <h1>Laporan Bisnis</h1>
                <p class="muted">{{ $insights['filters']['outlet_name'] }} | {{ $period }}</p>
            </div>
            <div class="right">
                <p class="muted">Dibuat</p>
                <strong>{{ now()->format('d M Y H:i') }}</strong>
            </div>
        </header>

        <section class="section cards">
            <div class="card">
                <div class="label">Omset Kasir</div>
                <div class="value">{{ $money($insights['sales']['gross_sales']) }}</div>
                <p class="muted">Sales net: {{ $money($insights['sales']['net_sales']) }}</p>
            </div>
            <div class="card">
                <div class="label">Pendapatan Gunsas</div>
                <div class="value">{{ $money($insights['sales']['gunsas_revenue']) }}</div>
                <p class="muted">Partner {{ $percent($insights['sales']['partner_share_percent']) }}</p>
            </div>
            <div class="card">
                <div class="label">Profit Bersih</div>
                <div class="value {{ $insights['profit']['net_profit'] >= 0 ? 'positive' : 'negative' }}">{{ $money($insights['profit']['net_profit']) }}</div>
                <p class="muted">Margin {{ $percent($insights['profit']['net_margin']) }}</p>
            </div>
            <div class="card">
                <div class="label">HPP Penjualan</div>
                <div class="value">{{ $money($insights['costs']['hpp_sales']) }}</div>
                <p class="muted">Modal barang terjual</p>
            </div>
            <div class="card">
                <div class="label">Expense</div>
                <div class="value">{{ $money($insights['costs']['expenses']) }}</div>
                <p class="muted">Direct + alokasi global</p>
            </div>
            <div class="card">
                <div class="label">Nilai Stok Tersisa</div>
                <div class="value">{{ $money($insights['inventory']['amount']) }}</div>
                <p class="muted">{{ $kg($insights['inventory']['total_kg']) }}</p>
            </div>
        </section>

        <section class="section notes">
            <h2>Catatan Manajemen</h2>
            <ol>
                @foreach ($notes as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ol>
        </section>

        <section class="section two-cols">
            <div>
                <h2>Pengurang Omset, Biaya & Loss</h2>
                <table>
                    <tbody>
                        <tr><td>Diskon (pengurang omset)</td><td class="num">{{ $money($insights['sales']['discount_amount']) }}</td></tr>
                        <tr><td>Inventory Terpakai</td><td class="num">{{ $money($insights['costs']['inventory_usage']) }}</td></tr>
                        <tr><td>Loss Retur Final</td><td class="num negative">{{ $money($insights['returns']['loss_final']) }}</td></tr>
                        <tr><td>Loss Opname</td><td class="num negative">{{ $money($insights['costs']['opname_loss']) }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div>
                <h2>Retur Supplier</h2>
                <table>
                    <tbody>
                        <tr><td>Retur Diajukan</td><td class="num">{{ $money($insights['returns']['asset_submitted']) }}</td></tr>
                        <tr><td>KG Diajukan</td><td class="num">{{ $kg($insights['returns']['submitted_kg']) }}</td></tr>
                        <tr><td>Refund Diterima</td><td class="num positive">{{ $money($insights['returns']['refund_received']) }}</td></tr>
                        <tr><td>KG Ditolak</td><td class="num negative">{{ $kg($insights['returns']['rejected_kg']) }}</td></tr>
                        <tr><td>Klaim Pending</td><td class="num">{{ $money($insights['returns']['pending_asset']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section">
            <h2>Penjualan Per Produk</h2>
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="num">KG</th>
                        <th class="num">Sales Net</th>
                        <th class="num">Bagian Gunsas</th>
                        <th class="num">Avg/Kg</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($insights['sales_by_product'] as $product)
                        <tr>
                            <td>{{ $product['product'] }}</td>
                            <td class="num">{{ $kg($product['kg']) }}</td>
                            <td class="num">{{ $money($product['net_sales']) }}</td>
                            <td class="num">{{ $money($product['gunsas_revenue']) }}</td>
                            <td class="num">{{ $money($product['avg_price_per_kg']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="section">
            <h2>Profit Per Outlet</h2>
            <table>
                <thead>
                    <tr>
                        <th>Outlet</th>
                        <th>Grup</th>
                        <th class="num">Sales Net</th>
                        <th class="num">Profit</th>
                        <th class="num">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($insights['profit_by_outlet'] as $outlet)
                        <tr>
                            <td>{{ $outlet['outlet_name'] }}</td>
                            <td>{{ $outlet['group_name'] }}</td>
                            <td class="num">{{ $money($outlet['net_sales']) }}</td>
                            <td class="num {{ $outlet['net_profit'] >= 0 ? 'positive' : 'negative' }}">{{ $money($outlet['net_profit']) }}</td>
                            <td class="num {{ $outlet['net_margin'] >= 0 ? 'positive' : 'negative' }}">{{ $percent($outlet['net_margin']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="section two-cols">
            <div>
                <h2>Efisiensi Produksi</h2>
                <table>
                    <tbody>
                        <tr><td>Input Buah</td><td class="num">{{ $kg($insights['production_efficiency']['input_kg']) }}</td></tr>
                        <tr><td>Daging Diperoleh</td><td class="num">{{ $kg($insights['production_efficiency']['usable_kg']) }}</td></tr>
                        <tr><td>Yield Daging</td><td class="num positive">{{ $percent($insights['production_efficiency']['yield_percentage']) }}</td></tr>
                        <tr><td>Susut Kulit & Biji</td><td class="num">{{ $kg($insights['production_efficiency']['shrink_kg']) }}</td></tr>
                        <tr><td>Pengkali Modal</td><td class="num">{{ number_format((float) $insights['production_efficiency']['multiplier_factor'], 2, ',', '.') }}x</td></tr>
                    </tbody>
                </table>
            </div>
            <div>
                <h2>Expense Terbesar</h2>
                <table>
                    <tbody>
                        @forelse (($insights['expense_categories'] ?? []) as $category)
                            <tr>
                                <td>{{ $category['category'] }}</td>
                                <td class="num">{{ $money($category['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">Tidak ada expense pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
