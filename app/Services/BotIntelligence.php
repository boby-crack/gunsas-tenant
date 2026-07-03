<?php

namespace App\Services;

class BotIntelligence
{
    protected array $validProducts = [
        'monthong',
        'musang king',
        'bawor',
        'duri hitam',
    ];

    public function parsePesan(string $pesan): array
    {
        $message = mb_strtolower($pesan, 'UTF-8');
        $durian = $this->detectDurian($message);

        preg_match('/kode\s*[:\s]*([a-z0-9-]+)/u', $message, $codeMatch);
        preg_match('/(\d+(?:[\.,]\d+)?)\s*kg/u', $message, $weightMatch);
        preg_match('/warna\s*[:\s]*([\p{L}\s]+?)(?:\s+keterangan|$)/u', $message, $colorMatch);
        preg_match('/keterangan\s*[:\s]*(.+)/u', $message, $noteMatch);

        $weight = isset($weightMatch[1])
            ? (float) str_replace(',', '.', $weightMatch[1])
            : 0.0;

        return [
            'durian' => $durian ?? 'Tidak terdeteksi',
            'kode_buah' => $codeMatch[1] ?? null,
            'berat' => $weight,
            'warna' => isset($colorMatch[1]) ? trim($colorMatch[1]) : null,
            'keterangan' => trim($noteMatch[1] ?? 'tidak ada'),
            'status' => ($durian && $weight > 0) ? 'VALID' : 'INVALID',
        ];
    }

    private function detectDurian(string $message): ?string
    {
        foreach ($this->validProducts as $product) {
            if (str_contains($message, $product)) {
                return $product;
            }
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $message, $matches);
        $words = $matches[0] ?? [];

        foreach ($this->validProducts as $product) {
            $productWords = explode(' ', $product);
            $windowSize = count($productWords);

            for ($index = 0; $index <= count($words) - $windowSize; $index++) {
                $candidate = implode(' ', array_slice($words, $index, $windowSize));

                if (levenshtein($candidate, $product) <= 2) {
                    return $product;
                }
            }
        }

        return null;
    }
}
