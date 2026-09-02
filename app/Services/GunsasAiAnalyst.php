<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GunsasAiAnalyst
{
    public function answer(string $question, array $insights, array $databaseContext = [], array $conversation = []): string
    {
        if ($answer = $this->deterministicAnswer($question, $insights, $databaseContext)) {
            return $answer;
        }

        return match (config('services.ai.provider', 'gemini')) {
            'openai' => $this->answerWithOpenAi($question, $insights, $databaseContext, $conversation),
            'gemini' => $this->answerWithGemini($question, $insights, $databaseContext, $conversation),
            default => throw new RuntimeException('AI_PROVIDER hanya mendukung: gemini atau openai.'),
        };
    }

    private function answerWithOpenAi(string $question, array $insights, array $databaseContext, array $conversation): string
    {
        $apiKey = config('services.openai.api_key');

        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY belum diisi di .env.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->retry(2, 500)
            ->timeout(30)
            ->post(rtrim(config('services.openai.base_url'), '/') . '/chat/completions', $this->buildOpenAiChatPayload($question, $insights, $databaseContext, $conversation));

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'OpenAI tidak bisa merespons saat ini.';

            throw new RuntimeException($message);
        }

        return trim((string) (
            data_get($response->json(), 'choices.0.message.content')
            ?? 'Maaf, jawaban OpenAI kosong.'
        ));
    }

    private function answerWithGemini(string $question, array $insights, array $databaseContext, array $conversation): string
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY belum diisi di .env.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(rtrim(config('services.gemini.base_url'), '/') . '/chat/completions', $this->buildGeminiPayload($question, $insights, $databaseContext, $conversation));

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'Gemini tidak bisa merespons saat ini.';

            throw new RuntimeException($message);
        }

        return trim((string) (
            data_get($response->json(), 'choices.0.message.content')
            ?? 'Maaf, jawaban Gemini kosong.'
        ));
    }

    private function buildOpenAiChatPayload(string $question, array $insights, array $databaseContext, array $conversation): array
    {
        return [
            'model' => config('services.openai.model'),
            'messages' => array_merge([
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
            ], $this->chatConversation($conversation), [
                [
                    'role' => 'user',
                    'content' => "DATA BISNIS RINGKAS:\n" . json_encode($this->compactInsights($insights), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        . "\n\nKONTEKS DATABASE BISNIS:\n" . json_encode($databaseContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        . "\n\nPERTANYAAN USER:\n" . $question,
                ],
            ]),
            'temperature' => 0.45,
            'max_tokens' => 1800,
        ];
    }

    private function deterministicAnswer(string $question, array $insights, array $databaseContext): ?string
    {
        $text = $this->normalize($question);

        if (! Str::contains($text, ['untung', 'rugi', 'profit', 'laba', 'margin'])) {
            return null;
        }

        $filters = $insights['filters'] ?? [];
        $sales = $insights['sales'] ?? [];
        $costs = $insights['costs'] ?? [];
        $returns = $insights['returns'] ?? [];
        $profit = $insights['profit'] ?? [];

        $netProfit = (float) ($profit['net_profit'] ?? 0);
        $gunsasRevenue = (float) ($sales['gunsas_revenue'] ?? 0);
        $netSales = (float) ($sales['net_sales'] ?? 0);
        $records = (int) data_get($databaseContext, 'sales.summary.records', 0);

        if ($records === 0 && $netSales <= 0 && $gunsasRevenue <= 0) {
            return implode("\n\n", [
                '**Belum bisa disimpulkan untung/rugi dari angka sales, karena data sales pada filter ini kosong.**',
                'Konteks yang terbaca: ' . $this->filterLabel($filters) . '.',
                'Cek apakah filter periode/outlet sudah benar, atau apakah data sales untuk periode itu sudah diinput.',
            ]);
        }

        $status = match (true) {
            $netProfit > 0 => 'untung',
            $netProfit < 0 => 'rugi',
            default => 'impas',
        };

        $lines = [
            'Berdasarkan data yang terbaca, **' . $this->filterLabel($filters) . ' ' . $status . '**.',
            '',
            '- Sales net: **' . $this->money($netSales) . '**',
            '- Pendapatan Gunsas: **' . $this->money($gunsasRevenue) . '**',
            '- HPP sales: **' . $this->money($costs['hpp_sales'] ?? 0) . '**',
            '- Expense: **' . $this->money($costs['expenses'] ?? 0) . '**',
            '- Inventory terpakai: **' . $this->money($costs['inventory_usage'] ?? 0) . '**',
            '- Loss retur final: **' . $this->money($returns['loss_final'] ?? 0) . '**',
            '- Loss opname: **' . $this->money($costs['opname_loss'] ?? 0) . '**',
            '',
            'Profit bersih: **' . $this->money($netProfit) . '**',
            'Margin bersih: **' . $this->percent($profit['net_margin'] ?? 0) . '**',
        ];

        if (abs((float) ($insights['inventory']['amount'] ?? 0)) > 0) {
            $lines[] = '';
            $lines[] = 'Catatan: nilai stok tersisa/inventory valuation **' . $this->money($insights['inventory']['amount'] ?? 0) . '** tidak aku campur ke profit bersih. Kalau stok ikut dihitung sebagai posisi aset, angkanya jadi **' . $this->money($profit['net_asset_position'] ?? 0) . '**.';
        }

        return implode("\n", $lines);
    }

    private function filterLabel(array $filters): string
    {
        $outlet = (string) ($filters['outlet_name'] ?? 'Semua Outlet');
        $from = $filters['date_from'] ?? null;
        $until = $filters['date_until'] ?? null;

        if ($from && $until) {
            return $outlet . ' periode ' . $from . ' sampai ' . $until;
        }

        return $outlet;
    }

    private function money(mixed $value): string
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    private function percent(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.') . '%';
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s\/\-]/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();
    }

    private function buildGeminiPayload(string $question, array $insights, array $databaseContext, array $conversation): array
    {
        return [
            'model' => config('services.gemini.model'),
            'messages' => array_merge([
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
            ], $this->chatConversation($conversation), [
                [
                    'role' => 'user',
                    'content' => "DATA BISNIS RINGKAS:\n" . json_encode($this->compactInsights($insights), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        . "\n\nKONTEKS DATABASE BISNIS:\n" . json_encode($databaseContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        . "\n\nPERTANYAAN USER:\n" . $question,
                ],
            ]),
            'temperature' => 0.45,
            'max_tokens' => 1800,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah AI Gunsas, analis bisnis durian untuk dashboard internal Gunsas.
Peranmu seperti konsultan bisnis internal yang enak diajak ngobrol: bisa membaca angka, menilai operasional, memberi ide strategi, menyusun prioritas, membantu owner berpikir, dan menjelaskan keputusan bisnis dengan bahasa manusia.

Aturan:
- Kamu READ-ONLY. Jangan pernah mengubah data, database, file, code, konfigurasi, target, transaksi, atau melakukan aksi sistem.
- Kalau user meminta perubahan data/code, jelaskan bahwa kamu tidak bisa mengeksekusi perubahan, tapi boleh memberi saran, draft, checklist, atau langkah yang aman.
- Kamu boleh memakai DATA BISNIS yang diberikan untuk angka aktual.
- Kamu juga boleh memakai KONTEKS DATABASE BISNIS yang berisi data dari tabel bisnis operasional: sales, expenses, purchases, shipments, productions, product conversions, product returns, stock opnames, sales targets, outlets, durian varieties, dan inventory items.
- KONTEKS DATABASE BISNIS adalah sumber utama untuk pertanyaan yang membutuhkan data mentah/detail. DATA BISNIS RINGKAS adalah ringkasan kalkulasi untuk profit, margin, HPP, loss, inventory valuation, dan KPI.
- Kamu boleh memakai pengetahuan bisnis umum untuk memberi strategi, hipotesis, dan rekomendasi, tetapi bedakan jelas antara "berdasarkan data" dan "dugaan/saran bisnis".
- Jangan mengarang angka, outlet, periode, atau transaksi. Jika angka tidak ada di DATA BISNIS, bilang belum tersedia.
- Jika pertanyaan user tidak spesifik, jangan langsung menebak. Ajukan 1-3 pertanyaan klarifikasi yang paling penting, misalnya periode, outlet/grup, kategori produk, atau metrik yang dimaksud.
- Jika pertanyaan user cukup spesifik tetapi data tidak cukup, tetap bantu user berpikir: sebutkan kemungkinan penyebab, data yang perlu dicek, dan langkah investigasi.
- Kamu boleh berdiskusi santai/non-angka jika user mengajak ngobrol, selama tetap relevan dan membantu.
- Kamu boleh menganalisis website/aplikasi Gunsas berdasarkan APP CONTEXT yang diberikan.
- Jika user bertanya apakah kamu bisa membaca website ini, jawab dengan jelas:
  "Saya tidak melihat layar/DOM seperti manusia, tapi saya bisa membaca konteks database bisnis yang dikirim backend Gunsas ke chat ini. Jadi saya bisa menganalisis sales, profit, margin, target, expense, purchase, shipment, produksi, inventory, retur, stock opname, dan loss berdasarkan data yang tersedia."
- Jika user bertanya "apa yang kurang di website", jawab sebagai product/business analyst: fitur yang kurang, prioritas, dan alasan bisnisnya.
- Bedakan profit bersih dengan inventory valuation.
- Refund supplier sudah masuk dalam perhitungan loss retur final, jadi jangan ditambahkan dua kali.
- Susut proses kupas/durpas sudah masuk ke modal average, jadi jangan dikurangkan lagi dari profit.
- Untuk jawaban finansial, tampilkan rupiah, KG, persentase, dan insight singkat jika relevan.
- Jangan gunakan LaTeX, KaTeX, rumus dengan backslash seperti \frac, \text, \left, atau \times. Tulis formula dalam bahasa bisnis yang mudah dibaca.
- Contoh format formula yang benar: **Margin Bersih = Profit Bersih / Pendapatan Gunsas x 100%**.
- Jika menjelaskan rumus, beri 1 kalimat arti rumusnya, bukan blok formula teknis.
- Untuk pertanyaan strategi, berikan rekomendasi yang praktis: apa yang harus dicek, prioritas tindakan, risiko, dan keputusan yang bisa diambil owner.
- Format jawaban dengan Markdown yang rapi: gunakan **bold** untuk angka penting, bullet/penomoran untuk rincian, dan paragraf pendek.
- Jangan tulis semuanya dalam satu paragraf panjang.
- DATA BISNIS RINGKAS dan KONTEKS DATABASE BISNIS sudah difilter otomatis dari maksud pertanyaan user. Jika relevan, sebutkan periode dan outlet dari filters agar user tahu konteks jawaban.
- Jika periode pada filters kosong/null, artinya backend mengirim cakupan semua tanggal yang tersedia, bukan hanya bulan berjalan.
- Untuk pertanyaan harga jual rata-rata per produk, gunakan sales_by_product.avg_price_per_kg, kg, net_sales, dan gunsas_revenue.
- Untuk pertanyaan outlet paling bagus/buruk, gunakan profit_by_outlet, top_outlets, sales, costs, returns, dan loss_breakdown.
- Jangan bilang data tidak tersedia sebelum mengecek DATA BISNIS RINGKAS dan KONTEKS DATABASE BISNIS.
- Jangan membaca atau meminta data sensitif seperti password, token, API key, session, atau isi .env.
- Jangan terlalu kaku. Bicara natural seperti partner bisnis yang tegas, jujur, dan membantu.
PROMPT;
    }

    private function chatConversation(array $conversation): array
    {
        return collect($conversation)
            ->take(-8)
            ->map(fn (array $message) => [
                'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($message['text'] ?? ''),
            ])
            ->filter(fn (array $message) => trim($message['content']) !== '')
            ->values()
            ->all();
    }

    private function compactInsights(array $insights): array
    {
        return [
            'periode' => $insights['filters'] ?? [],
            'app_context' => $this->appContext(),
            'formula' => [
                'pendapatan_gunsas' => 'sales setelah diskon x persentase bagian Gunsas per outlet',
                'profit_bersih' => 'pendapatan Gunsas - HPP - expenses - loss retur final - loss opname',
                'profit_plus_inventory' => 'profit bersih + nilai stok tersisa',
            ],
            'sales' => $insights['sales'] ?? [],
            'costs' => $insights['costs'] ?? [],
            'returns' => $insights['returns'] ?? [],
            'loss_breakdown' => $insights['loss_breakdown'] ?? [],
            'profit' => $insights['profit'] ?? [],
            'inventory' => $insights['inventory'] ?? [],
            'production_efficiency' => $insights['production_efficiency'] ?? [],
            'sales_by_product' => $this->limitRows($insights['sales_by_product'] ?? [], 25),
            'profit_by_outlet' => $this->limitRows($insights['profit_by_outlet'] ?? [], 25),
            'top_outlets' => $insights['top_outlets'] ?? [],
            'expense_categories' => $insights['expense_categories'] ?? [],
        ];
    }

    private function limitRows(array $rows, int $limit): array
    {
        return array_slice($rows, 0, $limit);
    }

    private function appContext(): array
    {
        return [
            'nama_aplikasi' => 'Gunsas Tenant',
            'tujuan' => 'Mengelola operasional bisnis durian: stok buah utuh, produksi kupas fresh, durpas frozen, penjualan outlet, retur supplier, expenses, stock opname, laporan WhatsApp, dan analisa profit/loss.',
            'fitur_saat_ini' => [
                'Dashboard ringkasan stok dan penjualan',
                'Business Insights: profit bersih, margin, inventory valuation, loss KG, retur supplier, expenses, outlet top revenue',
                'Dashboard executive summary: target outlet, realisasi, margin, tren sales/expense/purchase',
                'Durian Varieties',
                'Outlets dan alias outlet untuk parsing WhatsApp',
                'Target sales bulanan per outlet',
                'Master Produk / Inventory Item untuk packaging, stiker, produk olahan, bahan baku',
                'Purchases',
                'Shipments dari pusat ke outlet',
                'Productions untuk buah utuh menjadi kupas fresh/olahan',
                'Product Conversions dari kupas fresh ke durpas frozen',
                'Sales',
                'Product Returns dan refund supplier',
                'Stock Opnames',
                'WA Draft Reports dari webhook Fonnte, approval lewat web dan WhatsApp',
                'AI Gunsas chat read-only',
            ],
            'kemampuan_ai_di_website' => [
                'Menjawab pertanyaan bisnis dari ringkasan data yang dikirim backend',
                'Menganalisis profit, margin, sales, target outlet, expense, purchase, inventory, retur, dan loss',
                'Memberi rekomendasi operasional dan prioritas pengembangan fitur',
                'Membantu investigasi penyebab rugi, margin turun, outlet belum target, atau biaya naik',
                'Menyusun checklist aksi dan pertanyaan lanjutan untuk owner/admin',
                'Tidak mengeksekusi perubahan data/code/database demi keamanan',
            ],
            'batasan_saat_ini' => [
                'AI tidak mengubah data, database, konfigurasi, atau source code',
                'AI tidak melihat layar/DOM browser secara langsung seperti manusia',
                'AI membaca ringkasan bisnis, metrik dashboard, dan konteks aplikasi yang dikirim backend ke chat',
                'AI bisa menganalisis data bisnis dan kebutuhan fitur berdasarkan konteks yang tersedia',
                'Belum ada role/permission detail per user',
                'Belum ada audit trail lengkap untuk semua perubahan data',
                'Belum ada notifikasi otomatis untuk anomali margin/loss',
                'Belum ada halaman khusus cashflow/piutang retur supplier',
                'Belum ada export laporan PDF/Excel khusus manajemen',
            ],
            'prioritas_pengembangan_yang_disarankan' => [
                'Audit trail dan approval log',
                'Alert loss KG atau margin negatif',
                'Laporan retur supplier: diajukan, diterima, refund, selisih loss',
                'Cashflow dan expense detail: gaji, packaging, bensin',
                'Chart tren profit, revenue, HPP, loss, inventory yang lebih lengkap',
                'Role admin/operator/owner',
                'Export laporan',
            ],
        ];
    }
}
