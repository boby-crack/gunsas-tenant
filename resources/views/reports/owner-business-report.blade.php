@php
    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $kg = fn ($value) => number_format((float) $value, 3, ',', '.') . ' Kg';
    $qty = fn ($value) => number_format((float) $value, 3, ',', '.');
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';
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
    $stockMovement = $insights['stock_movement'] ?? ['summary' => [], 'rows' => []];

    $period = \Carbon\Carbon::parse($filters['date_from'] ?? now()->startOfMonth())->format('d M Y')
        . ' - '
        . \Carbon\Carbon::parse($filters['date_until'] ?? now())->format('d M Y');

    $returnRecovery = $returns['recovery'] ?? [];
    $totalProfitCosts = (float) ($costs['hpp_sales'] ?? 0)
        + (float) ($costs['expenses'] ?? 0)
        + (float) ($costs['inventory_usage'] ?? 0)
        + (float) ($returns['loss_final'] ?? 0)
        + (float) ($costs['opname_loss'] ?? 0);

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

    $lossItems = collect($loss['items'] ?? []);
    $stockRows = collect($stockMovement['rows'] ?? []);
    $stockSummary = $stockMovement['summary'] ?? [];
    $expenseCategories = collect($insights['expense_categories'] ?? []);
    $inventoryUsageItems = collect($costs['inventory_usage_items'] ?? []);
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
            --muted: #64748b;
            --line: #dbe1ea;
            --soft: #f8fafc;
            --soft-orange: #fff7ed;
            --danger: #dc2626;
            --success: #16a34a;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f3f4f6;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5px;
            line-height: 1.36;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, .94);
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
            width: 297mm;
            max-width: calc(100vw - 32px);
            margin: 18px auto;
            padding: 12mm;
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
        h2 { font-size: 15px; }
        h3 { font-size: 12px; }
        .muted { color: var(--muted); }
        .right { text-align: right; }
        .positive { color: var(--success); }
        .negative { color: var(--danger); }
        .nowrap { white-space: nowrap; }

        .section { margin-top: 18px; }
        .section-heading {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 8px;
        }

        .note {
            border: 1px solid #fed7aa;
            border-radius: 10px;
            background: var(--soft-orange);
            padding: 11px 13px;
        }

        .note ul {
            margin: 7px 0 0;
            padding-left: 18px;
        }

        .note li { margin: 3px 0; }

        .card-grid {
            display: grid;
            gap: 8px;
        }

        .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

        .card {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
            background: var(--soft);
            min-height: 68px;
        }

        .card .label {
            color: var(--muted);
            font-size: 10.5px;
            margin-bottom: 4px;
        }

        .card .value {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.18;
        }

        .breakdown {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 4px 12px;
            margin-top: 8px;
        }

        .breakdown span:nth-child(odd) { color: var(--muted); }
        .breakdown span:nth-child(even) {
            text-align: right;
            font-weight: 700;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #111827;
            color: white;
            font-size: 8.7px;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        th, td {
            border-bottom: 1px solid var(--line);
            padding: 4px 5px;
            vertical-align: top;
        }

        td:not(.num),
        th:not(.num) {
            overflow-wrap: anywhere;
            word-break: normal;
        }

        tbody tr.total td,
        tfoot td {
            background: #f1f5f9;
            font-weight: 800;
        }

        td.num, th.num {
            text-align: right;
            white-space: nowrap;
        }

        .compact-table {
            table-layout: fixed;
            font-size: 9.4px;
        }

        .compact-table th,
        .compact-table td {
            padding: 4px 4px;
        }

        .compact-table td.num,
        .compact-table th.num {
            white-space: normal;
        }

        .profit-table { font-size: 8.6px; }
        .stock-table { font-size: 8.4px; }

        .small-table th,
        .small-table td {
            padding: 5px 6px;
        }

        .page-break { break-before: page; }

        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .page {
                width: auto;
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .table-wrap { overflow: visible; }
            .section { break-inside: avoid; }
            table { break-inside: auto; }
            tr { break-inside: avoid; }
            @page { size: A4 landscape; margin: 8mm; }
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
                <p class="muted">{{ $filters['outlet_name'] ?? 'Semua Outlet' }} | {{ $period }}</p>
                <p><strong>{{ $filters['product_label'] ?? 'Semua Kategori / Semua Produk' }}</strong></p>
            </div>
            <div class="right">
                <p class="muted">Dibuat</p>
                <strong>{{ now()->format('d M Y H:i') }}</strong>
            </div>
        </header>

        <section class="section note">
            <h2>Cara Baca Singkat</h2>
            <ul>
                <li>Profit bersih = bagian Gunsas dikurangi HPP, expense, inventory terpakai, loss retur final, dan loss opname.</li>
                <li>Profit + nilai stok menunjukkan posisi aset. Angka ini bukan laba bersih kas.</li>
                <li>Sales return dicatat sebagai pengurang sales net, bukan loss terpisah.</li>
                <li>Recovery retur mengurangi HPP saat fresh recovery benar-benar terjual.</li>
            </ul>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <h2>Pendapatan</h2>
                    <p class="muted">Urutan dari omset kasir sampai bagian Gunsas.</p>
                </div>
            </div>
            <div class="card-grid grid-3">
                <div class="card">
                    <div class="label">1. Omset kasir</div>
                    <div class="value">{{ $money($sales['gross_sales'] ?? 0) }}</div>
                    <p class="muted">100% penjualan di kasir.</p>
                </div>
                <div class="card">
                    <div class="label">2. Omset setelah potongan</div>
                    <div class="value">{{ $money($sales['net_sales'] ?? 0) }}</div>
                    <p class="muted">Diskon {{ $money($sales['discount_amount'] ?? 0) }} + sales return {{ $money($sales['sales_return_amount'] ?? 0) }}.</p>
                </div>
                <div class="card">
                    <div class="label">3. Bagian Gunsas</div>
                    <div class="value">{{ $money($sales['gunsas_revenue'] ?? 0) }}</div>
                    <p class="muted">Setelah bagi hasil partner {{ $percent($sales['partner_share_percent'] ?? 0) }}.</p>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <h2>Profit Periode</h2>
                    <p class="muted">Fokus ke laba/rugi operasional periode yang dipilih.</p>
                </div>
            </div>
            <div class="card-grid grid-3">
                <div class="card">
                    <div class="label">Profit bersih</div>
                    <div class="value {{ $signedClass($profit['net_profit'] ?? 0) }}">{{ $money($profit['net_profit'] ?? 0) }}</div>
                    <p class="muted">Bagian Gunsas dikurangi semua beban profit.</p>
                </div>
                <div class="card">
                    <div class="label">Margin bersih</div>
                    <div class="value {{ $signedClass($profit['net_margin'] ?? 0) }}">{{ $percent($profit['net_margin'] ?? 0) }}</div>
                    <p class="muted">Profit bersih / bagian Gunsas.</p>
                </div>
                <div class="card">
                    <div class="label">Recovery fresh terjual</div>
                    <div class="value positive">{{ $kg($returnRecovery['sold_kg'] ?? 0) }}</div>
                    <p class="muted">HPP tidak dibebankan {{ $money($returnRecovery['hpp_saved_amount'] ?? 0) }}.</p>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <h2>Aset & Stok</h2>
                    <p class="muted">Nilai stok tersisa ditampilkan sebagai aset, bukan laba kas.</p>
                </div>
            </div>
            <div class="card-grid grid-2">
                <div class="card">
                    <div class="label">Inventory valuation</div>
                    <div class="value">{{ $money($inventory['amount'] ?? 0) }}</div>
                    <p class="muted">Estimasi nilai stok tersisa pada akhir periode.</p>
                </div>
                <div class="card">
                    <div class="label">Posisi setelah stok</div>
                    <div class="value {{ $signedClass($profit['net_asset_position'] ?? 0) }}">{{ $money($profit['net_asset_position'] ?? 0) }}</div>
                    <p class="muted">Profit bersih + inventory valuation. Fresh recovery tersisa {{ $kg($inventory['fresh_recovery_kg'] ?? 0) }} tidak dihitung modal.</p>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <h2>Beban, Stok, dan Barang Masuk</h2>
                    <p class="muted">Bagian ini menjelaskan kenapa profit naik atau turun.</p>
                </div>
            </div>
            <div class="card-grid grid-3">
                <div class="card">
                    <div class="label">Total pengurang profit</div>
                    <div class="value">{{ $money($totalProfitCosts) }}</div>
                    <div class="breakdown">
                        <span>HPP</span><span>{{ $money($costs['hpp_sales'] ?? 0) }}</span>
                        <span>Expense</span><span>{{ $money($costs['expenses'] ?? 0) }}</span>
                        <span>Inventory terpakai</span><span>{{ $money($costs['inventory_usage'] ?? 0) }}</span>
                        <span>Loss retur + opname</span><span class="negative">{{ $money(($returns['loss_final'] ?? 0) + ($costs['opname_loss'] ?? 0)) }}</span>
                    </div>
                </div>
                <div class="card">
                    <div class="label">Biaya operasional</div>
                    <div class="value">{{ $money($costs['expenses'] ?? 0) }}</div>
                    <div class="breakdown">
                        <span>Langsung ke outlet</span><span>{{ $money($costs['direct_expenses'] ?? 0) }}</span>
                        <span>Alokasi pusat/grup</span><span>{{ $money($costs['allocated_global_expenses'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="card">
                    <div class="label">Barang dikirim ke outlet</div>
                    <div class="value">{{ $money($shipments['total_modal'] ?? 0) }}</div>
                    <div class="breakdown">
                        <span>Berat durian</span><span>{{ $kg($shipments['durian_kg'] ?? 0) }}</span>
                        <span>Butir durian</span><span>{{ $qty($shipments['durian_butir'] ?? 0) }} Btr</span>
                        <span>Jumlah kiriman</span><span>{{ (int) ($shipments['records'] ?? 0) }} kiriman</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <h2>Stok dan Inventory Valuation</h2>
                    <p class="muted">Nilai stok tersisa ditampilkan sebagai aset terpisah.</p>
                </div>
            </div>
            <div class="card-grid grid-3">
                <div class="card">
                    <div class="label">Nilai stok tersisa</div>
                    <div class="value">{{ $money($inventory['amount'] ?? 0) }}</div>
                    <p class="muted">Total {{ $kg($inventory['total_kg'] ?? 0) }}</p>
                </div>
                <div class="card">
                    <div class="label">Stok durian</div>
                    <div class="breakdown">
                        <span>Buah utuh</span><span>{{ $kg($inventory['buah_kg'] ?? 0) }}</span>
                        <span>Kupas fresh</span><span>{{ $kg($inventory['fresh_kg'] ?? 0) }}</span>
                        <span>Durpas frozen</span><span>{{ $kg($inventory['frozen_kg'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="card">
                    <div class="label">Modal average</div>
                    <div class="breakdown">
                        <span>Buah</span><span>{{ $money($costs['avg_modal_buah'] ?? 0) }} / Kg</span>
                        <span>Fresh</span><span>{{ $money($costs['avg_modal_fresh'] ?? 0) }} / Kg</span>
                        <span>Frozen</span><span>{{ $money($costs['avg_modal_frozen'] ?? 0) }} / Kg</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <h2>Retur dan Loss</h2>
                    <p class="muted">Memisahkan loss langsung dan susut proses.</p>
                </div>
                <div class="right muted">
                    Loss langsung {{ $kg($loss['direct_loss_kg'] ?? 0) }} |
                    Susut proses {{ $kg($loss['process_shrink_kg'] ?? 0) }}
                </div>
            </div>
            <div class="card-grid grid-3">
                <div class="card">
                    <div class="label">Retur supplier</div>
                    <div class="breakdown">
                        <span>Retur diajukan</span><span>{{ $money($returns['asset_submitted'] ?? 0) }}</span>
                        <span>KG diajukan</span><span>{{ $kg($returns['submitted_kg'] ?? 0) }}</span>
                        <span>Refund diterima</span><span>{{ $money($returns['refund_received'] ?? 0) }}</span>
                        <span>Loss final</span><span class="{{ $negativeClass($returns['loss_final'] ?? 0) }}">{{ $money($returns['loss_final'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="card">
                    <div class="label">Recovery return</div>
                    <div class="breakdown">
                        <span>Fresh dari return</span><span>{{ $kg($returnRecovery['fresh_kg'] ?? 0) }}</span>
                        <span>Fresh recovery terjual</span><span class="positive">{{ $kg($returnRecovery['sold_kg'] ?? 0) }}</span>
                        <span>Fresh recovery tersisa</span><span>{{ $kg($returnRecovery['remaining_kg'] ?? 0) }}</span>
                        <span>Olahan dari return</span><span>{{ $kg($returnRecovery['olahan_kg'] ?? 0) }}</span>
                        <span>HPP tidak dibebankan</span><span class="positive">{{ $money($returnRecovery['hpp_saved_amount'] ?? 0) }}</span>
                        <span>Rugi final setelah refund</span><span class="{{ $negativeClass($returns['loss_final'] ?? 0) }}">{{ $money($returns['loss_final'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="card">
                    <div class="label">Klaim pending</div>
                    <div class="value">{{ $money($returns['pending_asset'] ?? 0) }}</div>
                    <p class="muted">{{ $kg($returns['pending_kg'] ?? 0) }} | {{ (int) ($returns['pending_count'] ?? 0) }} retur</p>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <h2>Penjualan Per Produk</h2>
                    <p class="muted">Sales net dibagi proporsional dari subtotal produk.</p>
                </div>
            </div>
            <div class="table-wrap">
            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Kategori</th>
                        <th>Varian</th>
                        <th class="num">Qty</th>
                        <th class="num">Omset</th>
                        <th class="num">Sales Net</th>
                        <th class="num">Bagian Gunsas</th>
                        <th class="num">Avg</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesProductRows as $row)
                        <tr>
                            <td><strong>{{ $row['product'] ?? '-' }}</strong></td>
                            <td>{{ $row['category'] ?? '-' }}</td>
                            <td>{{ $row['variety'] ?? '-' }}</td>
                            <td class="num">
                                @if (($row['kg'] ?? 0) > 0)
                                    {{ $kg($row['kg'] ?? 0) }}
                                @else
                                    {{ $qty($row['quantity'] ?? 0) }} {{ $row['unit'] ?? $row['secondary_unit'] ?? 'unit' }}
                                @endif
                            </td>
                            <td class="num">{{ $money($row['gross_sales'] ?? 0) }}</td>
                            <td class="num">{{ $money($row['net_sales'] ?? 0) }}</td>
                            <td class="num">{{ $money($row['gunsas_revenue'] ?? 0) }}</td>
                            <td class="num">
                                @if (($row['kg'] ?? 0) > 0)
                                    {{ $money($row['avg_price_per_kg'] ?? 0) }} / Kg
                                @else
                                    {{ $money($row['avg_price_per_unit'] ?? 0) }} / {{ $row['unit'] ?? $row['secondary_unit'] ?? 'unit' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Belum ada penjualan pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="num">{{ $kg($salesProductTotals['kg']) }} / {{ $qty($salesProductTotals['quantity']) }} unit</td>
                        <td class="num">{{ $money($salesProductTotals['gross_sales']) }}</td>
                        <td class="num">{{ $money($salesProductTotals['net_sales']) }}</td>
                        <td class="num">{{ $money($salesProductTotals['gunsas_revenue']) }}</td>
                        <td class="num">{{ $money($salesProductTotals['avg_price_per_kg']) }} / Kg</td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </section>

        <section class="section page-break">
            <div class="section-heading">
                <div>
                    <h2>Profit Per Outlet</h2>
                    <p class="muted">Expense pusat/global tidak dibebankan ke outlet kecuali ada scope alokasi.</p>
                </div>
            </div>
            <div class="table-wrap">
            <table class="compact-table profit-table">
                <thead>
                    <tr>
                        <th>Outlet</th>
                        <th>Grup</th>
                        <th class="num">Sales Net</th>
                        <th class="num">Bagian Gunsas</th>
                        <th class="num">HPP</th>
                        <th class="num">Expense</th>
                        <th class="num">Retur Loss</th>
                        <th class="num">Opname Loss</th>
                        <th class="num">Inventory</th>
                        <th class="num">Profit</th>
                        <th class="num">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profitOutletRows as $row)
                        <tr>
                            <td><strong>{{ $row['outlet_name'] ?? '-' }}</strong></td>
                            <td>{{ $row['group_name'] ?? '-' }}</td>
                            <td class="num">{{ $money($row['net_sales'] ?? 0) }}</td>
                            <td class="num">{{ $money($row['gunsas_revenue'] ?? 0) }}</td>
                            <td class="num">{{ $money($row['hpp_sales'] ?? 0) }}</td>
                            <td class="num">{{ $money($row['expenses'] ?? 0) }}</td>
                            <td class="num {{ $negativeClass($row['return_loss'] ?? 0) }}">{{ $money($row['return_loss'] ?? 0) }}</td>
                            <td class="num {{ $negativeClass($row['opname_loss'] ?? 0) }}">{{ $money($row['opname_loss'] ?? 0) }}</td>
                            <td class="num">{{ $money($row['inventory_usage'] ?? 0) }}</td>
                            <td class="num {{ $signedClass($row['net_profit'] ?? 0) }}">{{ $money($row['net_profit'] ?? 0) }}</td>
                            <td class="num {{ $signedClass($row['net_margin'] ?? 0) }}">{{ $percent($row['net_margin'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11">Belum ada data outlet pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="num">{{ $money($profitOutletTotals['net_sales']) }}</td>
                        <td class="num">{{ $money($profitOutletTotals['gunsas_revenue']) }}</td>
                        <td class="num">{{ $money($profitOutletTotals['hpp_sales']) }}</td>
                        <td class="num">{{ $money($profitOutletTotals['expenses']) }}</td>
                        <td class="num {{ $negativeClass($profitOutletTotals['return_loss']) }}">{{ $money($profitOutletTotals['return_loss']) }}</td>
                        <td class="num {{ $negativeClass($profitOutletTotals['opname_loss']) }}">{{ $money($profitOutletTotals['opname_loss']) }}</td>
                        <td class="num">{{ $money($profitOutletTotals['inventory_usage']) }}</td>
                        <td class="num {{ $signedClass($profitOutletTotals['net_profit']) }}">{{ $money($profitOutletTotals['net_profit']) }}</td>
                        <td class="num {{ $signedClass($profitOutletTotals['net_margin']) }}">{{ $percent($profitOutletTotals['net_margin']) }}</td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </section>

        <section class="section">
            <div class="card-grid grid-2">
                <div>
                    <h2>Efisiensi Produksi</h2>
                    <table class="small-table">
                        <tbody>
                            <tr><td>Produksi tercatat</td><td class="num">{{ (int) ($production['production_count'] ?? 0) }} batch</td></tr>
                            <tr><td>Input buah utuh</td><td class="num">{{ $kg($production['input_kg'] ?? 0) }}</td></tr>
                            <tr><td>Total daging diperoleh</td><td class="num">{{ $kg($production['usable_kg'] ?? 0) }}</td></tr>
                            <tr><td>Kupas fresh</td><td class="num">{{ $kg($production['fresh_kg'] ?? 0) }} / {{ $percent($production['fresh_yield_percentage'] ?? 0) }}</td></tr>
                            <tr><td>Olahan / reject</td><td class="num">{{ $kg($production['olahan_kg'] ?? 0) }} / {{ $percent($production['olahan_yield_percentage'] ?? 0) }}</td></tr>
                            <tr><td>Susut kulit & biji</td><td class="num">{{ $kg($production['shrink_kg'] ?? 0) }} / {{ $percent($production['shrinkage_percentage'] ?? 0) }}</td></tr>
                            <tr><td>Yield daging</td><td class="num">{{ $percent($production['yield_percentage'] ?? 0) }}</td></tr>
                            <tr><td>Pengkali produksi fisik</td><td class="num">{{ number_format((float) ($production['multiplier_factor'] ?? 0), 2, ',', '.') }}x</td></tr>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h2>Expense Terbesar</h2>
                    <table class="small-table">
                        <tbody>
                            @forelse ($expenseCategories as $row)
                                <tr>
                                    <td>{{ $row['category'] ?? '-' }}</td>
                                    <td class="num">{{ $money($row['total'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">Tidak ada expense pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="card-grid grid-2">
                <div>
                    <h2>Total Purchase</h2>
                    <table class="small-table">
                        <tbody>
                            <tr><td>Total purchase</td><td class="num">{{ $money($purchases['total_amount'] ?? 0) }}</td></tr>
                            <tr><td>Total KG durian</td><td class="num">{{ $kg($purchases['durian_kg'] ?? 0) }}</td></tr>
                            <tr><td>Total butir durian</td><td class="num">{{ $qty($purchases['durian_butir'] ?? 0) }} Btr</td></tr>
                            <tr><td>Avg purchase/Kg</td><td class="num">{{ $money($purchases['avg_price_per_kg'] ?? 0) }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h2>Inventory Terpakai / Produk Non-durian</h2>
                    <table class="small-table">
                        <tbody>
                            @forelse ($inventoryUsageItems->take(12) as $row)
                                <tr>
                                    <td>{{ $row['name'] ?? '-' }} <span class="muted">({{ $qty($row['qty'] ?? 0) }} {{ $row['unit'] ?? 'unit' }})</span></td>
                                    <td class="num">{{ $money($row['amount'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2">Tidak ada inventory terpakai pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <h2>Peta Loss KG</h2>
                    <p class="muted">Loss langsung mengurangi profit. Susut proses masuk ke pembentukan modal average.</p>
                </div>
            </div>
            <div class="table-wrap">
            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Jenis Loss</th>
                        <th>Efek</th>
                        <th class="num">KG</th>
                        <th class="num">Estimasi Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lossItems as $item)
                        <tr>
                            <td><strong>{{ $item['label'] ?? '-' }}</strong></td>
                            <td>{{ $item['effect'] ?? '-' }}</td>
                            <td class="num">{{ $kg($item['kg'] ?? 0) }}</td>
                            <td class="num">{{ $money($item['amount'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Tidak ada data loss pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </section>

        <section class="section page-break">
            <div class="section-heading">
                <div>
                    <h2>Lampiran Pergerakan Stok Outlet</h2>
                    <p class="muted">Data teknis kiriman, penjualan, proses, retur, dan opname untuk audit angka stok.</p>
                </div>
            </div>
            <div class="card-grid grid-4">
                <div class="card">
                    <div class="label">Stok awal</div>
                    <div class="value">{{ $kg($stockSummary['start_kg'] ?? 0) }}</div>
                </div>
                <div class="card">
                    <div class="label">Masuk</div>
                    <div class="value">{{ $kg($stockSummary['received_kg'] ?? 0) }}</div>
                </div>
                <div class="card">
                    <div class="label">Terjual</div>
                    <div class="value">{{ $kg($stockSummary['sold_kg'] ?? 0) }}</div>
                </div>
                <div class="card">
                    <div class="label">Selisih opname</div>
                    <div class="value {{ $signedClass($stockSummary['variance_kg'] ?? 0) }}">{{ $kg($stockSummary['variance_kg'] ?? 0) }}</div>
                </div>
            </div>
            <div class="table-wrap section">
            <table class="compact-table stock-table">
                <thead>
                    <tr>
                        <th>Outlet</th>
                        <th>Produk</th>
                        <th class="num">Stok Awal</th>
                        <th class="num">Masuk</th>
                        <th class="num">Terjual</th>
                        <th class="num">Keluar Lain</th>
                        <th class="num">Estimasi Sisa</th>
                        <th class="num">Opname Terakhir</th>
                        <th class="num">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockRows as $row)
                        <tr>
                            <td><strong>{{ $row['outlet_name'] ?? '-' }}</strong><br><span class="muted">{{ $row['group_name'] ?? '-' }}</span></td>
                            <td>{{ $row['product_label'] ?? '-' }}</td>
                            <td class="num">{{ $kg($row['start_kg'] ?? 0) }}</td>
                            <td class="num">
                                {{ $kg($row['received_kg'] ?? 0) }}
                                <br><span class="muted">Kirim {{ $kg($row['shipment_in_kg'] ?? 0) }} | Proses {{ $kg(($row['production_in_kg'] ?? 0) + ($row['conversion_in_kg'] ?? 0)) }}</span>
                            </td>
                            <td class="num">{{ $kg($row['sold_kg'] ?? 0) }}</td>
                            <td class="num">
                                {{ $kg($row['out_kg'] ?? 0) }}
                                <br><span class="muted">Retur {{ $kg($row['return_kg'] ?? 0) }} | Proses {{ $kg(($row['production_out_kg'] ?? 0) + ($row['conversion_out_kg'] ?? 0)) }}</span>
                            </td>
                            <td class="num">{{ $kg($row['estimated_stock_kg'] ?? 0) }}</td>
                            <td class="num">{{ $kg($row['physical_stock_kg'] ?? 0) }}</td>
                            <td class="num {{ $signedClass($row['variance_kg'] ?? 0) }}">{{ $kg($row['variance_kg'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">Tidak ada data pergerakan stok pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </section>
    </main>
</body>
</html>
