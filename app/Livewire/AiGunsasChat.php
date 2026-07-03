<?php

namespace App\Livewire;

use App\Models\Outlet;
use App\Services\BusinessInsightsCalculator;
use App\Services\GunsasAiAnalyst;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class AiGunsasChat extends Component
{
    public bool $isOpen = false;

    public string $question = '';

    public array $messages = [];

    public function mount(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'text' => 'Halo, saya AI Gunsas. Tanya aja soal profit, margin, stok, loss KG, retur, atau outlet. Kalau kamu sebut periode atau outlet, saya baca otomatis.',
            ],
        ];
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function ask(): void
    {
        $question = trim($this->question);

        if ($question === '') {
            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'text' => $question,
        ];
        $this->question = '';

        try {
            $answer = app(GunsasAiAnalyst::class)->answer(
                $question,
                app(BusinessInsightsCalculator::class)->calculate($this->filtersFromQuestion($question)),
            );
        } catch (Throwable $exception) {
            report($exception);

            $answer = 'Maaf, AI Gunsas belum bisa jawab sekarang. Cek AI_PROVIDER, API key, koneksi internet, atau limit/saldo API dulu ya.';
        }

        $this->messages[] = [
            'role' => 'assistant',
            'text' => $answer,
        ];
    }

    public function clearChat(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'text' => 'Chat dibersihkan. Mau cek bagian mana dulu?',
            ],
        ];
    }

    private function filtersFromQuestion(string $question): array
    {
        $period = $this->periodFromQuestion($question);

        return [
            'outlet_id' => $this->outletIdFromQuestion($question),
            'date_from' => $period['from'],
            'date_until' => $period['until'],
        ];
    }

    private function periodFromQuestion(string $question): array
    {
        $text = $this->normalize($question);
        $now = now();

        if (Str::contains($text, 'hari ini')) {
            return $this->period($now, $now);
        }

        if (Str::contains($text, 'kemarin')) {
            $date = $now->copy()->subDay();

            return $this->period($date, $date);
        }

        if (Str::contains($text, 'minggu lalu')) {
            return $this->period($now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek());
        }

        if (Str::contains($text, 'minggu ini')) {
            return $this->period($now->copy()->startOfWeek(), $now);
        }

        if (Str::contains($text, 'bulan lalu')) {
            return $this->period($now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth());
        }

        if (Str::contains($text, 'tahun ini')) {
            return $this->period($now->copy()->startOfYear(), $now);
        }

        if ($month = $this->monthFromQuestion($text)) {
            return $this->period($month->copy()->startOfMonth(), $month->copy()->endOfMonth());
        }

        if ($date = $this->dateFromQuestion($text)) {
            return $this->period($date, $date);
        }

        return $this->period($now->copy()->startOfMonth(), $now);
    }

    private function period(Carbon $from, Carbon $until): array
    {
        return [
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
        ];
    }

    private function monthFromQuestion(string $text): ?Carbon
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

        foreach ($months as $name => $month) {
            if (preg_match('/\b' . $name . '\b(?:\s+(\d{4}))?/u', $text, $matches)) {
                return Carbon::createFromDate((int) ($matches[1] ?? now()->year), $month, 1);
            }
        }

        return null;
    }

    private function dateFromQuestion(string $text): ?Carbon
    {
        if (! preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/u', $text, $matches)) {
            return null;
        }

        $year = isset($matches[3])
            ? (strlen($matches[3]) === 2 ? '20' . $matches[3] : $matches[3])
            : now()->year;

        return Carbon::createFromDate((int) $year, (int) $matches[2], (int) $matches[1]);
    }

    private function outletIdFromQuestion(string $question): ?int
    {
        $text = $this->normalize($question);

        if (Str::contains($text, ['semua outlet', 'semua toko', 'global'])) {
            return null;
        }

        foreach (Outlet::query()->orderByRaw('LENGTH(name) DESC')->get(['id', 'name', 'aliases']) as $outlet) {
            foreach ($this->outletTerms($outlet) as $term) {
                if ($term !== '' && str_contains($text, $term)) {
                    return $outlet->id;
                }
            }
        }

        return null;
    }

    private function outletTerms(Outlet $outlet): array
    {
        $aliases = preg_split('/[\r\n,;]+/u', (string) $outlet->aliases) ?: [];

        return collect([$outlet->name, ...$aliases])
            ->map(fn (string $term) => $this->normalize($term))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s\/\-]/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();
    }

    public function render()
    {
        return view('livewire.ai-gunsas-chat');
    }
}
