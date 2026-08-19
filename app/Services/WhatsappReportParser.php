<?php

namespace App\Services;

use App\Models\DurianVariety;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\ProductConversion;
use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Support\Str;

class WhatsappReportParser
{
    public function parse(string $message): array
    {
        $normalized = $this->normalize($message);
        $lineMessage = $this->normalizeLines($message);
        $type = $this->detectType($normalized);
        $outletText = $this->fieldValue(['nama otlet', 'nama outlet', 'otlet', 'outlet'], $lineMessage);
        $varietyText = $this->fieldValue(['varian', 'jenis durian', 'durian'], $lineMessage);
        $outlet = $this->matchModel($outletText ?: $normalized, Outlet::all(['id', 'name', 'aliases']), $outletText ? 60 : 25);
        $variety = $this->matchModel($varietyText ?: $normalized, DurianVariety::all(['id', 'name']), $varietyText ? 60 : 25);

        if ($type === 'rijek' && blank($varietyText)) {
            $record = DurianVariety::query()
                ->where('name', 'like', '%MONTHONG%')
                ->orWhere('name', 'like', '%MONTONG%')
                ->first(['id', 'name']);

            if ($record) {
                $variety = [
                    'id' => $record->id,
                    'name' => $record->name,
                    'score' => 50,
                ];
            }
        }

        if (! $variety && DurianVariety::count() === 1) {
            $record = DurianVariety::first(['id', 'name']);
            $variety = [
                'id' => $record->id,
                'name' => $record->name,
                'score' => 50,
            ];
        }
        $date = $this->extractDate($lineMessage);

        $payload = [
            'date' => $date->toDateString(),
            'outlet_id' => $outlet['id'] ?? null,
            'outlet_name' => $outlet['name'] ?? null,
            'durian_variety_id' => $variety['id'] ?? null,
            'durian_variety_name' => $variety['name'] ?? null,
            'notes' => trim($message),
        ];

        $payload = match ($type) {
            'retur' => array_merge($payload, $this->parseRetur($normalized, $lineMessage)),
            'rijek' => array_merge($payload, $this->parseRijek($normalized, $lineMessage)),
            'kupas' => array_merge($payload, $this->parseKupas($normalized, $lineMessage)),
            'frozen' => array_merge($payload, $this->parseFrozen($normalized, $lineMessage)),
            'fresh_loss' => array_merge($payload, $this->parseFreshLoss($normalized, $lineMessage)),
            'opname' => array_merge($payload, $this->parseOpname($lineMessage)),
            default => $payload,
        };

        $missingFields = $this->missingRequiredFields($type, $payload);
        $confidence = $this->confidence($type, $outlet, $variety, $payload, $missingFields);

        $payload['missing_fields'] = $missingFields;

        return [
            'report_type' => $type,
            'parsed_payload' => $payload,
            'confidence' => $confidence,
            'status' => $type && empty($missingFields) && $confidence >= 75
                ? 'pending_approval'
                : 'needs_review',
            'error_notes' => empty($missingFields)
                ? null
                : 'Perlu dilengkapi: ' . implode(', ', $missingFields),
        ];
    }

    private function parseRetur(string $message, string $lineMessage): array
    {
        $qtyKg = $this->numberFromField(['berat buah', 'berat'], $lineMessage)
            ?? $this->numberAfter(['berat buah', 'berat'], $message)
            ?? $this->firstKg($message);
        $description = $this->fieldValue(['keterangan', 'alasan', 'ket'], $lineMessage)
            ?? $this->textAfter(['keterangan', 'alasan', 'ket'], $message)
            ?? 'Input dari WhatsApp';
        $supplierCode = $this->fieldValue(['supplier', 'suplier', 'kode buah', 'kode'], $lineMessage)
            ?? $this->textAfter(['supplier', 'spl', 'suplier'], $message);
        $paintColor = $this->fieldValue(['warna cat', 'warna', 'cat', 'pilox'], $lineMessage)
            ?? $this->textAfter(['warna cat', 'warna', 'cat', 'pilox'], $message);

        return [
            'qty_kg' => $qtyKg,
            'qty_butir' => $this->numberAfter(['butir', 'btr'], $message, false) ?? 1,
            'supplier_code' => $supplierCode,
            'paint_color' => $paintColor,
            'return_reason_type' => str_contains($message, 'bangkalan') ? 'Buah Bangkalan' : 'Buah Rusak / Asam',
            'detailed_reason' => $description,
            'status' => 'pending',
            'refund_amount' => 0,
        ];
    }

    private function parseRijek(string $message, string $lineMessage): array
    {
        $buahKg = $this->numberFromField(['berat buah', 'berat'], $lineMessage)
            ?? $this->numberAfter(['berat buah', 'berat'], $message)
            ?? $this->firstKg($message);
        $freshKg = $this->gramLikeNumberFromField(['berat kupas fresh', 'kupas fresh kg', 'fresh kg'], $lineMessage)
            ?? $this->gramLikeNumberAfter(['berat kupas fresh', 'kupas fresh', 'fresh'], $message)
            ?? 0;
        $olahanKg = $this->gramLikeNumberFromField(['berat olahan', 'olahan kg', 'reject kg'], $lineMessage)
            ?? $this->gramLikeNumberAfter(['berat olahan', 'olahan', 'reject'], $message)
            ?? 0;
        $freshPack = $this->numberFromField(['jumlah pack', 'pack fresh', 'fresh pack'], $lineMessage, false)
            ?? $this->packCount($message)
            ?? 0;
        $totalMeat = ($freshKg ?? 0) + ($olahanKg ?? 0);
        $supplierCode = $this->fieldValue(['supplier', 'suplier', 'kode buah', 'kode'], $lineMessage)
            ?? $this->textAfter(['supplier', 'spl', 'suplier', 'kode'], $message);
        $description = $this->fieldValue(['keterangan', 'alasan', 'ket.', 'ket'], $lineMessage)
            ?? $this->textAfter(['keterangan', 'alasan', 'ket'], $message)
            ?? 'Input rijek dari WhatsApp';

        return [
            'qty_kg' => $buahKg,
            'qty_butir' => $this->numberAfter(['butir', 'btr'], $message, false) ?? 1,
            'supplier_code' => $supplierCode,
            'paint_color' => null,
            'return_reason_type' => str_contains($message, 'bangkalan') ? 'Buah Bangkalan' : 'Buah Rusak / Asam',
            'detailed_reason' => $description,
            'status' => 'pending',
            'refund_amount' => 0,
            'source_type' => Production::SOURCE_RETURN,
            'qty_buah_kg' => $buahKg,
            'qty_buah_butir' => $this->numberAfter(['butir', 'btr'], $message, false) ?? 1,
            'qty_kupas_kg' => $freshKg,
            'qty_kupas_pack' => $freshPack,
            'qty_olahan_kg' => $olahanKg,
            'qty_olahan_pack' => 0,
            'total_usable_meat_kg' => $totalMeat,
            'shrinkage_percentage' => $buahKg && $buahKg > 0 ? round((($buahKg - $totalMeat) / $buahKg) * 100, 2) : 0,
            'multiplier_factor' => $totalMeat > 0 && $buahKg ? round($buahKg / $totalMeat, 2) : 0,
        ];
    }

    private function parseKupas(string $message, string $lineMessage): array
    {
        $buahKg = $this->numberFromField(['berat buah'], $lineMessage)
            ?? $this->numberAfter(['buah kg', 'buah', 'bahan', 'utuh'], $message)
            ?? $this->firstKg($message);
        $freshKg = $this->numberFromField(['jumlah pack', 'hasil pack', 'hasil kupas'], $lineMessage)
            ?? $this->numberAfter(['fresh', 'kupas', 'daging'], $message);
        $olahanKg = $this->numberAfter(['olahan', 'reject', 'rusak'], $message) ?? 0;
        $totalMeat = ($freshKg ?? 0) + $olahanKg;

        return [
            'source_type' => $this->productionSourceType($message),
            'qty_buah_kg' => $buahKg,
            'qty_buah_butir' => $this->numberAfter(['butir', 'btr'], $message, false) ?? 0,
            'qty_kupas_kg' => $freshKg,
            'qty_kupas_pack' => $this->packCount($message) ?? 0,
            'qty_olahan_kg' => $olahanKg,
            'qty_olahan_pack' => 0,
            'total_usable_meat_kg' => $totalMeat,
            'shrinkage_percentage' => $buahKg && $buahKg > 0 ? round((($buahKg - $totalMeat) / $buahKg) * 100, 2) : 0,
            'multiplier_factor' => $totalMeat > 0 && $buahKg ? round($buahKg / $totalMeat, 2) : 0,
        ];
    }

    private function parseFrozen(string $message, string $lineMessage): array
    {
        $packResult = $this->packAndKgFromField(['kupas jadi', 'jadi', 'hasil'], $lineMessage)
            ?? $this->packAndKgFromText(['kupas jadi', 'jadi', 'hasil'], $message);

        $fromKg = $this->numberFromField(['berat awal', 'awal', 'berat'], $lineMessage)
            ?? $this->numberAfter(['berat awal', 'fresh', 'asal', 'dari'], $message)
            ?? $this->firstKg($message);
        $toKg = $this->numberFromField(['berat akhir', 'akhir'], $lineMessage)
            ?? ($packResult['kg'] ?? null)
            ?? $this->numberAfter(['berat akhir', 'durpas', 'frozen kg'], $message);

        return [
            'conversion_type' => ProductConversion::TYPE_FRESH_TO_FROZEN,
            'from_qty_kg' => $fromKg,
            'from_qty_pack' => $this->numberAfter(['pack fresh', 'fresh pack'], $message, false) ?? 0,
            'to_qty_kg' => $toKg,
            'to_qty_pack' => $packResult['pack'] ?? $this->packCount($message) ?? 0,
        ];
    }

    private function parseFreshLoss(string $message, string $lineMessage): array
    {
        $fromKg = $this->gramLikeNumberFromField(['berat fresh', 'berat kupas fresh', 'kupas fresh kg', 'fresh kg', 'berat awal'], $lineMessage)
            ?? $this->gramLikeNumberAfter(['berat fresh', 'berat kupas fresh', 'fresh busuk', 'fresh rusak', 'fresh olahan', 'fresh'], $message)
            ?? $this->firstKg($message);
        $notes = $this->fieldValue(['keterangan', 'alasan', 'ket.', 'ket', 'note'], $lineMessage)
            ?? $this->textAfter(['keterangan', 'alasan', 'ket', 'note'], $message)
            ?? 'Fresh busuk / olahan dari WhatsApp';

        return [
            'conversion_type' => ProductConversion::TYPE_FRESH_LOSS,
            'from_qty_kg' => $fromKg,
            'from_qty_pack' => $this->numberFromField(['pack fresh', 'fresh pack', 'kupas fresh pack'], $lineMessage, false)
                ?? $this->packCount($message)
                ?? 0,
            'to_qty_kg' => 0,
            'to_qty_pack' => 0,
            'notes' => $notes,
        ];
    }

    private function parseOpname(string $lineMessage): array
    {
        $durianItems = $this->durianOpnameItemsFromText(
            $this->messageWithoutAdditionalDurianBlocks($lineMessage),
        );

        foreach ($this->additionalDurianOpnameBlocks($lineMessage) as $block) {
            $durianItems = array_merge(
                $durianItems,
                $this->durianOpnameItemsFromText($block['text'], $block['variety']),
            );
        }

        $inventoryItems = [];

        foreach ([
            'thinwall' => 'Thinwall',
            'stiker batang' => 'Stiker Batang',
            'stiker durpas' => 'Stiker Durpas',
            'sendok tester' => 'Sendok Tester',
            'tusuk gigi' => 'Tusuk Gigi',
            'sarung tangan plastik' => 'Sarung Tangan Plastik',
            'tissue' => 'tissue',
            'tisu' => 'tissue',
            'karet' => 'karet',
            'sticker batang' => 'Stiker Batang',
            'sticker durpas' => 'Stiker Durpas',
            'soaker pad' => 'soaker pad',
        ] as $label => $name) {
            $value = $this->fieldValue([$label], $lineMessage)
                ?? $this->opnameTextValue($label, $lineMessage);

            if (! $value) {
                continue;
            }

            $qty = $this->quantityFromInventoryValue($value);

            if ($qty === null) {
                continue;
            }

            $item = $this->matchInventoryItem($name);

            $inventoryItems[] = [
                'inventory_item_id' => $item['id'] ?? null,
                'inventory_item_name' => $item['name'] ?? $name,
                'physical_qty' => $qty,
                'unit' => $this->unitFromInventoryValue($value),
                'raw_value' => $value,
            ];
        }

        return [
            'opname_items' => $durianItems,
            'inventory_items' => $inventoryItems,
        ];
    }

    private function durianOpnameItemsFromText(string $message, ?array $variety = null): array
    {
        $items = [];
        $varietyData = $variety ? [
            'durian_variety_id' => $variety['id'] ?? null,
            'durian_variety_name' => $variety['name'] ?? null,
        ] : [];

        if (($kg = $this->numberFromOpnameField(['buah utuh kg'], $message)) !== null) {
            $items[] = [
                ...$varietyData,
                'product_type' => 'Buah Utuh',
                'physical_qty_kg' => $kg,
                'physical_qty_butir' => $this->numberFromOpnameField(['buah utuh butir'], $message, false),
            ];
        }

        if (($kg = $this->numberFromOpnameField(['kupas fresh kg'], $message)) !== null) {
            $items[] = [
                ...$varietyData,
                'product_type' => 'Daging Fresh',
                'physical_qty_kg' => $kg,
                'physical_qty_pack' => $this->numberFromOpnameField(['kupas fresh pack'], $message, false),
            ];
        }

        if (($kg = $this->numberFromOpnameField(['durpas frozen kg'], $message)) !== null) {
            $items[] = [
                ...$varietyData,
                'product_type' => 'Daging Frozen',
                'physical_qty_kg' => $kg,
                'physical_qty_pack' => $this->numberFromOpnameField(['durpas frozen pack'], $message, false),
            ];
        }

        return $items;
    }

    private function messageWithoutAdditionalDurianBlocks(string $message): string
    {
        return trim(preg_replace('/^durian\s+[^\n:]+\s*:\s*\n.*?(?=^durian\s+[^\n:]+\s*:|\z)/msu', '', $message) ?? $message);
    }

    /**
     * @return array<int, array{variety: array{id: int, name: string, score: int}|null, text: string}>
     */
    private function additionalDurianOpnameBlocks(string $message): array
    {
        if (! preg_match_all('/^durian\s+([^\n:]+)\s*:\s*\n(.*?)(?=^durian\s+[^\n:]+\s*:|\z)/msu', $message, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $varieties = DurianVariety::all(['id', 'name']);

        return collect($matches)
            ->map(function (array $match) use ($varieties): array {
                $varietyText = $this->normalize($match[1]);

                if (Str::contains($varietyText, ['blacktron', 'black thron', 'blackthorn'])) {
                    $varietyText = 'blackthorn lokal';
                }

                $variety = $this->matchModel($varietyText, $varieties, 20);

                return [
                    'variety' => $variety,
                    'text' => trim($match[2]),
                ];
            })
            ->filter(fn (array $block): bool => $block['text'] !== '')
            ->values()
            ->all();
    }

    private function detectType(string $message): ?string
    {
        if (
            Str::contains($message, ['data rijek', 'data reject', 'rijek', 'reject'])
            && Str::contains($message, ['berat kupas fresh', 'berat olahan', 'jumlah pack'])
        ) {
            return 'rijek';
        }

        if (Str::contains($message, ['stok opname', 'stock opname', 'opname mingguan', 'opname'])) {
            return 'opname';
        }

        if (
            Str::contains($message, ['dari retur', 'dari return', 'buah retur', 'buah return', 'no retur', 'no return'])
            && Str::contains($message, ['kupas', 'fresh', 'daging', 'produksi'])
        ) {
            return 'kupas';
        }

        if (Str::contains($message, ['retur', 'return', 'rusak', 'asam', 'bangkalan'])) {
            return 'retur';
        }

        if (
            Str::contains($message, ['fresh', 'kupas'])
            && Str::contains($message, ['olahan', 'busuk', 'loss', 'reject', 'rijek', 'rusak'])
        ) {
            return 'fresh_loss';
        }

        if (Str::contains($message, ['frozen', 'durpas', 'beku', 'freezer'])) {
            return 'frozen';
        }

        if (Str::contains($message, ['berat buah']) && Str::contains($message, ['jumlah pack', 'pack'])) {
            return 'kupas';
        }

        if (Str::contains($message, ['kupas', 'fresh', 'daging'])) {
            return 'kupas';
        }

        return null;
    }

    private function productionSourceType(string $message): string
    {
        return Str::contains($message, ['dari retur', 'dari return', 'buah retur', 'buah return', 'no retur', 'no return'])
            ? Production::SOURCE_RETURN
            : Production::SOURCE_NORMAL;
    }

    private function matchModel(string $message, $records, int $minimumScore = 25): ?array
    {
        $best = null;
        $bestScore = 0;
        $messageWords = preg_split('/\s+/', $message) ?: [];

        foreach ($records as $record) {
            $name = $this->normalize($record->name);
            $searchTerms = array_merge([$name], $this->aliasesFor($record));
            $score = 0;

            foreach ($searchTerms as $term) {
                if ($term !== '' && str_contains($message, $term)) {
                    $score = max($score, $term === $name ? 100 : 95);
                }
            }

            $words = collect($searchTerms)
                ->flatMap(fn (string $term) => explode(' ', $term))
                ->filter(fn (string $word) => strlen($word) > 2)
                ->unique()
                ->values()
                ->all();

            foreach ($words as $word) {
                if ($message === $word) {
                    $score += 75;

                    continue;
                }

                if (str_contains($message, $word)) {
                    $score += 35;

                    continue;
                }

                foreach ($messageWords as $messageWord) {
                    if (strlen($messageWord) > 2 && levenshtein($messageWord, $word) <= 2) {
                        $score += 25;
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'id' => $record->id,
                    'name' => $record->name,
                    'score' => min($score, 100),
                ];
            }
        }

        return $bestScore >= $minimumScore ? $best : null;
    }

    private function aliasesFor(object $record): array
    {
        if (! $record instanceof Outlet || blank($record->aliases)) {
            return [];
        }

        return collect(preg_split('/[\r\n,;]+/u', (string) $record->aliases) ?: [])
            ->map(fn (string $alias) => $this->normalize($alias))
            ->filter()
            ->values()
            ->all();
    }

    private function confidence(?string $type, ?array $outlet, ?array $variety, array $payload, array $missingFields): int
    {
        $score = 0;
        $score += $type ? 25 : 0;
        $score += $outlet ? 20 : 0;
        $score += $variety ? 20 : 0;
        $score += max(0, 35 - (count($missingFields) * 12));

        return min(100, $score);
    }

    private function missingRequiredFields(?string $type, array $payload): array
    {
        $required = match ($type) {
            'retur' => ['outlet_id', 'qty_kg'],
            'rijek' => ['outlet_id', 'durian_variety_id', 'qty_kg', 'qty_buah_kg'],
            'kupas' => ['outlet_id', 'durian_variety_id', 'qty_buah_kg', 'qty_kupas_kg'],
            'frozen' => ['outlet_id', 'durian_variety_id', 'from_qty_kg', 'to_qty_kg'],
            'fresh_loss' => ['outlet_id', 'durian_variety_id', 'from_qty_kg'],
            'opname' => ['outlet_id'],
            default => ['report_type'],
        };

        $missing = array_values(array_filter($required, fn ($field) => blank($payload[$field] ?? null)));

        if ($type === 'opname') {
            $hasDurianItems = ! empty($payload['opname_items'] ?? []);
            $hasInventoryItems = ! empty($payload['inventory_items'] ?? []);

            $hasDurianVariety = filled($payload['durian_variety_id'] ?? null)
                || collect($payload['opname_items'] ?? [])->every(fn (array $item): bool => filled($item['durian_variety_id'] ?? null));

            if ($hasDurianItems && ! $hasDurianVariety) {
                $missing[] = 'durian_variety_id';
            }

            if (! $hasDurianItems && ! $hasInventoryItems) {
                $missing[] = 'opname_items';
            }
        }

        return array_values(array_unique($missing));
    }

    private function extractDate(string $message): Carbon
    {
        $months = [
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
        ];

        $dateField = $this->fieldValue(['tgl kedatangan', 'tanggal kedatangan', 'tanggal', 'tgl'], $message);

        if ($dateField && preg_match('/^(\d{1,2})$/', $dateField, $matches)) {
            return Carbon::createFromDate(now()->year, now()->month, (int) $matches[1]);
        }

        if (preg_match('/(\d{1,2})\s+(' . implode('|', array_keys($months)) . ')(?:\s+(\d{4}))?/u', $message, $matches)) {
            return Carbon::createFromDate(
                (int) ($matches[3] ?? now()->year),
                $months[$matches[2]],
                (int) $matches[1],
            );
        }

        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?/', $message, $matches)) {
            $year = isset($matches[3])
                ? (strlen($matches[3]) === 2 ? '20' . $matches[3] : $matches[3])
                : now()->year;

            return Carbon::createFromDate((int) $year, (int) $matches[2], (int) $matches[1]);
        }

        return now();
    }

    private function numberAfter(array $labels, string $message, bool $allowDecimal = true): ?float
    {
        $numberPattern = $allowDecimal ? '(\d+(?:[\.,]\s*\d+)?)' : '(\d+)';

        foreach ($labels as $label) {
            $label = preg_quote($label, '/');

            if (preg_match('/' . $label . '\s*[:=\-]?\s*' . $numberPattern . '/u', $message, $matches)) {
                return $this->normalizeNumber($matches[1]);
            }

            if (preg_match('/' . $numberPattern . '\s*' . $label . '/u', $message, $matches)) {
                return $this->normalizeNumber($matches[1]);
            }
        }

        return null;
    }

    private function gramLikeNumberAfter(array $labels, string $message): ?float
    {
        foreach ($labels as $label) {
            $label = preg_quote($label, '/');

            if (preg_match('/' . $label . '\s*[:=\-]?\s*(\d+(?:[\.,]\s*\d+)?)/u', $message, $matches)) {
                return $this->normalizeGramLikeNumber($matches[1]);
            }
        }

        return null;
    }

    private function matchInventoryItem(string $name): ?array
    {
        return $this->matchModel($this->normalize($name), InventoryItem::all(['id', 'name']), 45);
    }

    private function quantityFromInventoryValue(string $value): ?float
    {
        if (! preg_match('/(\d+(?:[\.,]\s*\d+)?)/u', $value, $matches)) {
            return null;
        }

        $qty = $this->normalizeNumber($matches[1]);
        $text = $this->normalize($value);

        if (Str::contains($text, ['setengah', 'separuh', '1/2'])) {
            $qty += 0.5;
        }

        return $qty;
    }

    private function unitFromInventoryValue(string $value): ?string
    {
        $text = $this->normalize($value);

        return match (true) {
            Str::contains($text, ['pack']) => 'pack',
            Str::contains($text, ['pcs', 'pc']) => 'pcs',
            Str::contains($text, ['set']) => 'set',
            Str::contains($text, ['thinwal', 'thinwall']) => 'pcs',
            default => null,
        };
    }

    private function numberFromField(array $labels, string $message, bool $allowDecimal = true): ?float
    {
        $value = $this->fieldValue($labels, $message);

        if (! $value) {
            return null;
        }

        $numberPattern = $allowDecimal ? '/(\d+(?:[\.,]\s*\d+)?)/' : '/(\d+)/';

        if (preg_match($numberPattern, $value, $matches)) {
            return $this->normalizeNumber($matches[1]);
        }

        return null;
    }

    private function gramLikeNumberFromField(array $labels, string $message): ?float
    {
        $value = $this->fieldValue($labels, $message);

        if (! $value) {
            return null;
        }

        if (preg_match('/(\d+(?:[\.,]\s*\d+)?)/', $value, $matches)) {
            return $this->normalizeGramLikeNumber($matches[1]);
        }

        return null;
    }

    private function numberFromOpnameField(array $labels, string $message, bool $allowDecimal = true): ?float
    {
        return $this->numberFromField($labels, $message, $allowDecimal)
            ?? $this->numberAfter($labels, $message, $allowDecimal);
    }

    private function opnameTextValue(string $label, string $message): ?string
    {
        $labels = [
            'thinwall',
            'stiker batang',
            'stiker durpas',
            'sendok tester',
            'tusuk gigi',
            'sarung tangan plastik',
            'tissue',
            'tisu',
            'karet',
            'soaker pad',


        ];

        $nextLabels = collect($labels)
            ->reject(fn (string $item) => $item === $label)
            ->map(fn (string $item) => preg_quote($item, '/'))
            ->implode('|');

        if (preg_match('/' . preg_quote($label, '/') . '\s*[:=\-]?\s*(.+?)(?=\s+(?:' . $nextLabels . ')\s*[:=\-]|$)/u', $message, $matches)) {
            $value = trim($matches[1]);

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function packAndKgFromField(array $labels, string $message): ?array
    {
        $value = $this->fieldValue($labels, $message);

        if (! $value) {
            return null;
        }

        return $this->packAndKgFromValue($value);
    }

    private function packAndKgFromText(array $labels, string $message): ?array
    {
        foreach ($labels as $label) {
            $label = preg_quote($label, '/');

            if (preg_match('/' . $label . '\s*[:=\-]?\s*(\d+)\s*(?:\(|\s+)(\d+(?:[\.,]\d+)?)/u', $message, $matches)) {
                return [
                    'pack' => (int) $matches[1],
                    'kg' => $this->normalizeGramLikeNumber($matches[2]),
                ];
            }
        }

        return null;
    }

    private function packAndKgFromValue(string $value): ?array
    {
        if (preg_match('/^(\d+)\s*(?:\(|\s+)(\d+(?:[\.,]\d+)?)/u', $value, $matches)) {
            return [
                'pack' => (int) $matches[1],
                'kg' => $this->normalizeGramLikeNumber($matches[2]),
            ];
        }

        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:\)|$)/u', $value, $matches)) {
            return [
                'pack' => 0,
                'kg' => $this->normalizeGramLikeNumber($matches[1]),
            ];
        }

        return null;
    }

    private function firstKg(string $message): ?float
    {
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:kg|kilo)/u', $message, $matches)) {
            return $this->normalizeNumber($matches[1]);
        }

        return null;
    }

    private function packCount(string $message): ?int
    {
        preg_match_all('/(\d+)\s*pack/u', $message, $matches);

        if (empty($matches[1])) {
            return null;
        }

        return (int) end($matches[1]);
    }

    private function textAfter(array $labels, string $message): ?string
    {
        $stopWords = 'nama|otlet|outlet|varian|tgl|tanggal|kedatangan|supplier|suplier|spl|kode|warna|cat|pilox|alasan|ket|keterangan|berat|kg|kilo|butir|btr|buah|fresh|frozen|durpas|olahan|reject|rusak|pack';

        foreach ($labels as $label) {
            $label = preg_quote($label, '/');

            if (preg_match('/' . $label . '\s*[:=\-]?\s*([a-z0-9\.\-\s]+?)(?=\s+(?:' . $stopWords . ')\b|$)/u', $message, $matches)) {
                $value = trim($matches[1]);

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    private function fieldValue(array $labels, string $message): ?string
    {
        $lines = preg_split('/\R/u', $message) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            foreach ($labels as $label) {
                $label = preg_quote($label, '/');

                if (preg_match('/^' . $label . '\s*[:=\-]?\s*(.*)$/u', $line, $matches)) {
                    $value = trim($matches[1]);

                    return $value === '' ? null : $value;
                }
            }
        }

        return null;
    }

    private function normalizeNumber(string $value): float
    {
        $value = preg_replace('/(?<=\d)[\.,]\s+(?=\d)/', '.', $value) ?? $value;
        $value = str_replace(',', '.', $value);

        if (! str_contains($value, '.') && strlen($value) > 3) {
            return (float) ($value / 1000);
        }

        return (float) $value;
    }

    private function normalizeGramLikeNumber(string $value): float
    {
        $value = preg_replace('/(?<=\d)[\.,]\s+(?=\d)/', '.', $value) ?? $value;
        $normalized = str_replace(',', '.', $value);

        if (! str_contains($normalized, '.')) {
            return (float) ($normalized / 1000);
        }

        return (float) $normalized;
    }

    private function normalize(string $value): string
    {
        $value = Str::of($value)
            ->lower()
            ->replace([','], ['.'])
            ->replaceMatches('/[^\pL\pN\s\.\/\-]/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        return (string) $value;
    }

    private function normalizeLines(string $value): string
    {
        $lines = preg_split('/\R/u', $value) ?: [];
        $lines = array_map(function (string $line): string {
            return (string) Str::of($line)
                ->lower()
                ->replace([','], ['.'])
                ->replaceMatches('/[^\pL\pN\s\.\/\-:=]/u', ' ')
                ->replaceMatches('/\s+/', ' ')
                ->trim();
        }, $lines);

        return implode("\n", $lines);
    }
}
