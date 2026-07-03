<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GunsasAiAnalyst
{
    public function answer(string $question, array $insights): string
    {
        return match (config('services.ai.provider', 'gemini')) {
            'openai' => $this->answerWithOpenAi($question, $insights),
            'gemini' => $this->answerWithGemini($question, $insights),
            default => throw new RuntimeException('AI_PROVIDER hanya mendukung: gemini atau openai.'),
        };
    }

    private function answerWithOpenAi(string $question, array $insights): string
    {
        $apiKey = config('services.openai.api_key');

        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY belum diisi di .env.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(rtrim(config('services.openai.base_url'), '/') . '/responses', $this->buildOpenAiPayload($question, $insights));

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'OpenAI tidak bisa merespons saat ini.';

            throw new RuntimeException($message);
        }

        return trim((string) (
            $response->json('output_text')
            ?? data_get($response->json(), 'output.0.content.0.text')
            ?? 'Maaf, jawabannya kosong.'
        ));
    }

    private function answerWithGemini(string $question, array $insights): string
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY belum diisi di .env.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(rtrim(config('services.gemini.base_url'), '/') . '/chat/completions', $this->buildGeminiPayload($question, $insights));

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'Gemini tidak bisa merespons saat ini.';

            throw new RuntimeException($message);
        }

        return trim((string) (
            data_get($response->json(), 'choices.0.message.content')
            ?? 'Maaf, jawaban Gemini kosong.'
        ));
    }

    private function buildOpenAiPayload(string $question, array $insights): array
    {
        return [
            'model' => config('services.openai.model'),
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->systemPrompt(),
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => "DATA BISNIS:\n" . json_encode($this->compactInsights($insights), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                . "\n\nPERTANYAAN USER:\n" . $question,
                        ],
                    ],
                ],
            ],
            'temperature' => 0.2,
            'max_output_tokens' => 700,
        ];
    }

    private function buildGeminiPayload(string $question, array $insights): array
    {
        return [
            'model' => config('services.gemini.model'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => "DATA BISNIS:\n" . json_encode($this->compactInsights($insights), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        . "\n\nPERTANYAAN USER:\n" . $question,
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => 700,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah AI Gunsas, analis bisnis durian untuk dashboard internal Gunsas.
Jawab dalam Bahasa Indonesia yang natural, ringkas, dan langsung ke angka penting.

Aturan:
- Kamu hanya boleh memakai DATA BISNIS yang diberikan.
- Jangan mengarang angka, outlet, periode, atau transaksi.
- Jika data tidak cukup, bilang data belum cukup dan sebutkan data apa yang perlu dilengkapi.
- Jangan memberi instruksi teknis coding.
- Jangan mengubah data. Kamu hanya analis read-only.
- Kamu boleh menganalisis website/aplikasi Gunsas berdasarkan APP CONTEXT yang diberikan.
- Jika user bertanya "apa yang kurang di website", jawab sebagai product/business analyst: fitur yang kurang, prioritas, dan alasan bisnisnya.
- Bedakan profit bersih dengan inventory valuation.
- Refund supplier sudah masuk dalam perhitungan loss retur final, jadi jangan ditambahkan dua kali.
- Susut proses kupas/durpas sudah masuk ke modal average, jadi jangan dikurangkan lagi dari profit.
- Untuk jawaban finansial, tampilkan rupiah, KG, dan insight singkat jika relevan.
- Format jawaban dengan Markdown yang rapi: gunakan **bold** untuk angka penting, bullet/penomoran untuk rincian, dan paragraf pendek.
- Jangan tulis semuanya dalam satu paragraf panjang.
- DATA BISNIS sudah difilter otomatis dari maksud pertanyaan user. Jika relevan, sebutkan periode dan outlet dari DATA BISNIS agar user tahu konteks jawaban.
PROMPT;
    }

    private function compactInsights(array $insights): array
    {
        return [
            'periode' => $insights['filters'] ?? [],
            'app_context' => $this->appContext(),
            'formula' => [
                'pendapatan_gunsas' => 'omset kasir x 85%',
                'profit_bersih' => 'pendapatan Gunsas - HPP - expenses - loss retur final - loss opname',
                'profit_plus_inventory' => 'profit bersih + nilai stok tersisa',
            ],
            'sales' => $insights['sales'] ?? [],
            'costs' => $insights['costs'] ?? [],
            'returns' => $insights['returns'] ?? [],
            'loss_breakdown' => $insights['loss_breakdown'] ?? [],
            'profit' => $insights['profit'] ?? [],
            'inventory' => $insights['inventory'] ?? [],
            'top_outlets' => $insights['top_outlets'] ?? [],
            'expense_categories' => $insights['expense_categories'] ?? [],
        ];
    }

    private function appContext(): array
    {
        return [
            'nama_aplikasi' => 'Gunsas Tenant',
            'tujuan' => 'Mengelola operasional bisnis durian: stok buah utuh, produksi kupas fresh, durpas frozen, penjualan outlet, retur supplier, expenses, stock opname, laporan WhatsApp, dan analisa profit/loss.',
            'fitur_saat_ini' => [
                'Dashboard ringkasan stok dan penjualan',
                'Business Insights: profit bersih, margin, inventory valuation, loss KG, retur supplier, expenses, outlet top revenue',
                'Durian Varieties',
                'Outlets dan alias outlet untuk parsing WhatsApp',
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
            'batasan_saat_ini' => [
                'AI tidak mengubah data dan tidak membaca source code secara langsung saat chat',
                'AI membaca ringkasan bisnis dan konteks aplikasi yang dikirim backend',
                'Belum ada role/permission detail per user',
                'Belum ada audit trail lengkap untuk semua perubahan data',
                'Belum ada notifikasi otomatis untuk anomali margin/loss',
                'Belum ada halaman khusus cashflow/piutang retur supplier',
                'Belum ada visual chart tren profit/loss per periode',
                'Belum ada export laporan PDF/Excel khusus manajemen',
            ],
            'prioritas_pengembangan_yang_disarankan' => [
                'Audit trail dan approval log',
                'Alert loss KG atau margin negatif',
                'Laporan retur supplier: diajukan, diterima, refund, selisih loss',
                'Cashflow dan expense detail: gaji, packaging, bensin',
                'Chart tren profit, revenue, HPP, loss, inventory',
                'Role admin/operator/owner',
                'Export laporan',
            ],
        ];
    }
}
