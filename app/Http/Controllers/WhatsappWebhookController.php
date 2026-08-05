<?php

namespace App\Http\Controllers;

use App\Models\WhatsappReport;
use App\Services\FonnteMessageSender;
use App\Services\WhatsappReportApprover;
use App\Services\WhatsappReportParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappWebhookController extends Controller
{
    public function store(
        Request $request,
        WhatsappReportParser $parser,
        FonnteMessageSender $senderClient,
        WhatsappReportApprover $approver
    ): JsonResponse
    {
        $message = $this->firstFilled($request, [
            'caption',
            'description',
            'data.caption',
            'data.description',
            'message',
            'body',
            'text',
            'pesan',
            'content',
            'data.message',
            'data.body',
            'data.text',
        ]);

        $sender = $this->firstFilled($request, [
            'sender',
            'from',
            'number',
            'phone',
            'wa_number',
            'device',
            'data.sender',
            'data.from',
            'data.number',
            'data.phone',
        ]);
        $member = $this->firstFilled($request, [
            'member',
            'memberid',
            'data.member',
            'data.memberid',
        ]);
        $isGroup = $request->boolean('isgroup');

        if (blank($message)) {
            return response()->json([
                'ok' => false,
                'error' => 'Field pesan tidak ditemukan.',
                'received_keys' => array_keys($request->all()),
            ], 422);
        }

        if ($approveId = $this->approveCommandId($message)) {
            $report = WhatsappReport::find($approveId);

            if (! $report) {
                $reply = 'Aku belum menemukan draft #' . $approveId . '. Coba cek lagi nomornya ya.';
                $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

                return response()->json([
                    'ok' => false,
                    'command' => 'approve',
                    'message' => $reply,
                ], 404);
            }

            $result = $approver->approve($report);
            $reply = $result['ok']
                ? $result['message'] . "\nMasuk ke: " . $result['target_label']
                : $result['message'];

            $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

            return response()->json([
                'ok' => $result['ok'],
                'command' => 'approve',
                'id' => $report->id,
                'message' => $result['message'],
            ], $result['ok'] ? 200 : 422);
        }

        if ($cancelId = $this->cancelCommandId($message)) {
            $report = WhatsappReport::find($cancelId);

            if (! $report) {
                $reply = 'Draft #' . $cancelId . ' tidak ditemukan.';
                $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

                return response()->json([
                    'ok' => false,
                    'command' => 'cancel',
                    'message' => $reply,
                ], 404);
            }

            if ($report->status === 'approved') {
                $reply = 'Draft #' . $report->id . ' sudah approved, jadi aku tidak hapus dari WhatsApp.';
                $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

                return response()->json([
                    'ok' => false,
                    'command' => 'cancel',
                    'id' => $report->id,
                    'message' => $reply,
                ], 422);
            }

            $report->delete();
            $reply = 'Siap, draft #' . $cancelId . ' sudah aku batalkan dan hapus.';
            $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

            return response()->json([
                'ok' => true,
                'command' => 'cancel',
                'id' => $cancelId,
                'message' => $reply,
            ]);
        }

        if ($this->isPendingListCommand($message)) {
            $reply = $this->pendingListReply();
            $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

            return response()->json([
                'ok' => true,
                'command' => 'list_pending',
            ]);
        }

        if ($this->isReviewListCommand($message)) {
            $reply = $this->reviewListReply();
            $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

            return response()->json([
                'ok' => true,
                'command' => 'list_review',
            ]);
        }

        if ($detailId = $this->detailCommandId($message)) {
            $report = WhatsappReport::find($detailId);
            $reply = $report
                ? $this->detailReply($report)
                : 'Aku belum menemukan draft #' . $detailId . '. Coba cek lagi nomornya ya.';

            $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

            return response()->json([
                'ok' => (bool) $report,
                'command' => 'detail',
                'id' => $detailId,
            ], $report ? 200 : 404);
        }

        if ($duplicate = $this->recentDuplicateReport($sender, $message)) {
            $reply = implode("\n", [
                'Laporan ini sudah tercatat sebelumnya.',
                'Draft #' . $duplicate->id . ' statusnya: ' . $duplicate->status . '.',
                'Ketik detail #' . $duplicate->id . ' kalau mau cek ulang.',
            ]);

            $this->sendReply($senderClient, $sender, $member, $isGroup, $reply);

            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'id' => $duplicate->id,
                'status' => $duplicate->status,
                'report_type' => $duplicate->report_type,
            ]);
        }

        $parsed = $parser->parse($message);

        $report = WhatsappReport::create([
            'sender' => $sender,
            'raw_message' => $message,
            'report_type' => $parsed['report_type'],
            'parsed_payload' => $parsed['parsed_payload'],
            'confidence' => $parsed['confidence'],
            'status' => $parsed['status'],
            'error_notes' => $parsed['error_notes'],
        ]);

        $this->sendReply($senderClient, $sender, $member, $isGroup, $this->replyFor($report));

        return response()->json([
            'ok' => true,
            'id' => $report->id,
            'status' => $report->status,
            'report_type' => $report->report_type,
            'confidence' => $report->confidence,
            'parsed_payload' => $report->parsed_payload,
        ]);
    }

    private function recentDuplicateReport(?string $sender, string $message): ?WhatsappReport
    {
        return WhatsappReport::query()
            ->where('sender', $sender)
            ->where('raw_message', $message)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest()
            ->first();
    }

    private function replyFor(WhatsappReport $report): string
    {
        if ($report->status === 'pending_approval') {
            return implode("\n", [
                'Siap, laporan sudah masuk.',
                'Aku baca sebagai ' . $this->reportTypeLabel($report->report_type) . '.',
                'Draft #' . $report->id . ' tinggal di-approve.',
                'Ketik detail #' . $report->id . ' kalau mau cek dulu.',
            ]);
        }

        return implode("\n", [
            'Laporannya sudah masuk, tapi aku belum yakin datanya lengkap.',
            $report->error_notes ?: 'Formatnya belum kebaca penuh.',
            'Draft #' . $report->id . ' perlu dicek dulu di web.',
        ]);
    }

    private function sendReply(FonnteMessageSender $senderClient, ?string $sender, ?string $member, bool $isGroup, string $message): void
    {
        $sent = $senderClient->send($sender, $message);

        if (! $sent && $isGroup && filled($member)) {
            $senderClient->send($member, $message);
        }
    }

    private function reportTypeLabel(?string $type): string
    {
        return match ($type) {
            'retur' => 'Retur',
            'rijek' => 'Data Rijek',
            'kupas' => 'Buah ke Kupas Fresh',
            'frozen' => 'Kupas Fresh ke Durpas Frozen',
            'fresh_loss' => 'Kupas Fresh Loss / Olahan',
            'opname' => 'Stock Opname',
            default => 'Belum dikenali',
        };
    }

    private function approveCommandId(string $message): ?int
    {
        if (preg_match('/^\s*(?:approve|acc|setujui)\s*#?(\d+)\s*$/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function cancelCommandId(string $message): ?int
    {
        if (preg_match('/^\s*(?:batal|cancel|hapus|delete)\s*#?(\d+)\s*$/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function isPendingListCommand(string $message): bool
    {
        return (bool) preg_match('/^\s*(?:list|daftar|pending|belum approve|belum acc)(?:\s+(?:pending|draft|approve|acc))?\s*$/i', $message);
    }

    private function isReviewListCommand(string $message): bool
    {
        return (bool) preg_match('/^\s*(?:list|daftar)?\s*(?:review|needs review|need review|perlu review|perlu cek|cek manual)\s*$/i', $message);
    }

    private function detailCommandId(string $message): ?int
    {
        if (preg_match('/^\s*(?:detail|cek|lihat)\s*#?(\d+)\s*$/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function pendingListReply(): string
    {
        $reports = WhatsappReport::query()
            ->where('status', 'pending_approval')
            ->latest()
            ->limit(5)
            ->get();

        if ($reports->isEmpty()) {
            return 'Aman, tidak ada draft yang menunggu approval.';
        }

        $lines = ['Ada ' . $reports->count() . ' draft yang siap di-approve:'];

        foreach ($reports as $report) {
            $payload = $report->parsed_payload ?? [];
            $lines[] = '#' . $report->id
                . ' | ' . $this->reportTypeLabel($report->report_type)
                . ' | ' . ($payload['outlet_name'] ?? '-')
                . ' | ' . ($payload['date'] ?? '-');
        }

        $lines[] = '';
        $lines[] = 'Ketik detail #ID untuk cek.';
        $lines[] = 'Ketik approve #ID kalau sudah oke.';

        return implode("\n", $lines);
    }

    private function reviewListReply(): string
    {
        $reports = WhatsappReport::query()
            ->where('status', 'needs_review')
            ->latest()
            ->limit(5)
            ->get();

        if ($reports->isEmpty()) {
            return 'Aman, tidak ada draft yang perlu review.';
        }

        $lines = ['Ada ' . $reports->count() . ' draft yang perlu dicek dulu:'];

        foreach ($reports as $report) {
            $payload = $report->parsed_payload ?? [];
            $missing = $payload['missing_fields'] ?? [];
            $lines[] = '#' . $report->id
                . ' | ' . $this->reportTypeLabel($report->report_type)
                . ' | ' . ($payload['outlet_name'] ?? '-')
                . ' | ' . $report->confidence . '%'
                . ' | kurang: ' . (empty($missing) ? '-' : implode(', ', $missing));
        }

        $lines[] = '';
        $lines[] = 'Ketik detail #ID untuk lihat ringkasannya.';
        $lines[] = 'Yang ini perlu diperbaiki di web sebelum approve.';

        return implode("\n", $lines);
    }

    private function detailReply(WhatsappReport $report): string
    {
        $payload = $report->parsed_payload ?? [];
        $lines = [
            'Ini ringkasan draft #' . $report->id . ':',
            'Jenis: ' . $this->reportTypeLabel($report->report_type),
            'Outlet: ' . ($payload['outlet_name'] ?? '-'),
            'Tanggal: ' . ($payload['date'] ?? '-'),
        ];

        foreach ($this->quantityLines($report->report_type, $payload) as $line) {
            $lines[] = $line;
        }

        if (! empty($payload['missing_fields'])) {
            $lines[] = 'Yang masih kurang: ' . implode(', ', $payload['missing_fields']);
        }

        $lines[] = 'Keyakinan baca: ' . $report->confidence . '%';

        if ($report->status === 'pending_approval') {
            $lines[] = '';
            $lines[] = 'Kalau sudah cocok, ketik approve #' . $report->id . '.';
        } elseif ($report->status === 'needs_review') {
            $lines[] = '';
            $lines[] = 'Draft ini perlu dirapikan di web dulu.';
        }

        return implode("\n", $lines);
    }

    private function quantityLines(?string $type, array $payload): array
    {
        return match ($type) {
            'retur' => [
                'Berat: ' . $this->formatKg($payload['qty_kg'] ?? null),
                'Butir: ' . ($payload['qty_butir'] ?? '-'),
                'Kode: ' . ($payload['supplier_code'] ?? '-'),
                'Cat: ' . ($payload['paint_color'] ?? '-'),
                'Alasan: ' . ($payload['detailed_reason'] ?? '-'),
            ],
            'rijek' => [
                'Buah return: ' . $this->formatKg($payload['qty_kg'] ?? null),
                'Fresh jadi: ' . $this->formatKg($payload['qty_kupas_kg'] ?? null),
                'Pack fresh: ' . ($payload['qty_kupas_pack'] ?? '-'),
                'Olahan/reject: ' . $this->formatKg($payload['qty_olahan_kg'] ?? null),
                'Kode: ' . ($payload['supplier_code'] ?? '-'),
                'Catatan: ' . ($payload['detailed_reason'] ?? '-'),
            ],
            'kupas' => [
                'Sumber: ' . (($payload['source_type'] ?? 'normal') === 'return' ? 'Buah return' : 'Stok normal'),
                'Buah awal: ' . $this->formatKg($payload['qty_buah_kg'] ?? null),
                'Fresh jadi: ' . $this->formatKg($payload['qty_kupas_kg'] ?? null),
                'Pack: ' . ($payload['qty_kupas_pack'] ?? '-'),
            ],
            'frozen' => [
                'Fresh awal: ' . $this->formatKg($payload['from_qty_kg'] ?? null),
                'Frozen jadi: ' . $this->formatKg($payload['to_qty_kg'] ?? null),
                'Pack: ' . ($payload['to_qty_pack'] ?? '-'),
            ],
            'fresh_loss' => [
                'Fresh keluar: ' . $this->formatKg($payload['from_qty_kg'] ?? null),
                'Pack: ' . ($payload['from_qty_pack'] ?? '-'),
                'Catatan: ' . ($payload['notes'] ?? '-'),
            ],
            'opname' => $this->opnameQuantityLines($payload),
            default => [],
        };
    }

    private function opnameQuantityLines(array $payload): array
    {
        $lines = [];

        foreach ($payload['opname_items'] ?? [] as $item) {
            $label = match ($item['product_type'] ?? null) {
                'Buah Utuh' => 'Buah utuh',
                'Daging Fresh' => 'Kupas fresh',
                'Daging Frozen' => 'Durpas frozen',
                default => $item['product_type'] ?? 'Produk durian',
            };

            $lines[] = $label . ': ' . $this->formatKg($item['physical_qty_kg'] ?? null);
        }

        $inventoryItems = $payload['inventory_items'] ?? [];

        if (! empty($inventoryItems)) {
            $lines[] = 'Inventory: ' . count($inventoryItems) . ' item';
        }

        return $lines;
    }

    private function formatKg(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 3, ',', '.') . ' Kg';
    }

    private function firstFilled(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->input($key);

            if (filled($value) && is_scalar($value) && $this->isMeaningfulText((string) $value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function isMeaningfulText(string $value): bool
    {
        return ! in_array(strtolower(trim($value)), [
            'non-text message',
            'non-button message',
        ], true);
    }
}
