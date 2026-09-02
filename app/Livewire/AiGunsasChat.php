<?php

namespace App\Livewire;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\Outlet;
use App\Services\BusinessInsightsCalculator;
use App\Services\GunsasBusinessDataContext;
use App\Services\GunsasAiAnalyst;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class AiGunsasChat extends Component
{
    private const GREETING = 'Halo, saya AI Gunsas. Tanya aja soal profit, margin, stok, loss KG, retur, atau outlet. Kalau kamu sebut periode atau outlet, saya baca otomatis.';

    public bool $isOpen = false;

    public string $question = '';

    public array $messages = [];

    public array $sessions = [];

    public ?int $activeSessionId = null;

    public ?string $pendingAnswerQuestion = null;

    public function mount(): void
    {
        $this->activeSessionId = $this->sessionQuery()->value('id');
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;

        if ($this->isOpen) {
            $this->openCurrentChat();
        }
    }

    public function openChat(): void
    {
        $this->isOpen = true;
        $this->openCurrentChat();
    }

    public function closeChat(): void
    {
        $this->isOpen = false;
    }

    public function ask(): void
    {
        if ($this->pendingAnswerQuestion !== null) {
            return;
        }

        $question = trim($this->question);

        if ($question === '') {
            return;
        }

        $this->ensureActiveSession();

        if ($this->messages === []) {
            $this->loadMessages();
        }

        $this->storeMessage('user', $question);

        $this->messages[] = [
            'role' => 'user',
            'text' => $question,
        ];
        $this->question = '';
        $this->pendingAnswerQuestion = $question;

        $this->dispatch('ai-gunsas-question-sent');
    }

    public function answerPending(): void
    {
        $question = $this->pendingAnswerQuestion;

        if (! $question) {
            return;
        }

        try {
            $filters = $this->filtersFromQuestion($question);

            $answer = app(GunsasAiAnalyst::class)->answer(
                $question,
                app(BusinessInsightsCalculator::class)->calculate($filters),
                app(GunsasBusinessDataContext::class)->build($question, $filters),
                $this->messages,
            );
        } catch (Throwable $exception) {
            report($exception);

            $answer = 'Maaf, AI Gunsas belum bisa jawab sekarang. Cek AI_PROVIDER, API key, koneksi internet, atau limit/saldo API dulu ya.';
        }

        $this->messages[] = [
            'role' => 'assistant',
            'text' => $answer,
        ];
        $this->storeMessage('assistant', $answer);

        $this->pendingAnswerQuestion = null;
        $this->dispatch('ai-gunsas-answer-received');
    }

    public function clearChat(): void
    {
        $this->ensureActiveSession();

        AiChatMessage::query()
            ->where('ai_chat_session_id', $this->activeSessionId)
            ->delete();

        $this->messages = [];
        $this->storeMessage('assistant', 'Chat dibersihkan. Mau cek bagian mana dulu?');
        $this->loadMessages();
        $this->pendingAnswerQuestion = null;
        $this->dispatch('ai-gunsas-answer-received');
    }

    public function newChat(): void
    {
        $session = AiChatSession::create([
            'user_id' => auth()->id(),
            'title' => 'Obrolan baru',
            'last_message_at' => now(),
        ]);

        $this->activeSessionId = $session->id;
        $this->messages = [];
        $this->pendingAnswerQuestion = null;
        $this->storeMessage('assistant', self::GREETING);
        $this->loadSessions();
        $this->loadMessages();
        $this->dispatch('ai-gunsas-answer-received');
    }

    public function openCurrentChat(): void
    {
        $this->loadSessions();

        if (! $this->activeSessionId) {
            $latestSession = $this->sessionQuery()->first();

            if ($latestSession) {
                $this->activeSessionId = $latestSession->id;
            } else {
                $this->newChat();

                return;
            }
        }

        if ($this->messages === []) {
            $this->loadMessages();
        }

        $this->dispatch('ai-gunsas-answer-received');
    }

    public function selectSession(int $sessionId): void
    {
        $session = $this->sessionQuery()
            ->whereKey($sessionId)
            ->first();

        if (! $session) {
            return;
        }

        $this->activeSessionId = $session->id;
        $this->pendingAnswerQuestion = null;
        $this->question = '';
        $this->loadMessages();
        $this->dispatch('ai-gunsas-answer-received');
    }

    public function deleteSession(int $sessionId): void
    {
        $session = $this->sessionQuery()
            ->whereKey($sessionId)
            ->first();

        if (! $session) {
            return;
        }

        $session->delete();
        $this->loadSessions();

        if ($this->activeSessionId === $sessionId) {
            $latestSession = $this->sessionQuery()->first();

            if ($latestSession) {
                $this->selectSession($latestSession->id);
            } else {
                $this->newChat();
            }
        }
    }

    public function deleteAllHistory(): void
    {
        $this->sessionQuery()->delete();

        $this->activeSessionId = null;
        $this->sessions = [];
        $this->messages = [];
        $this->question = '';
        $this->pendingAnswerQuestion = null;

        $this->newChat();
    }

    private function ensureActiveSession(): void
    {
        if ($this->activeSessionId && AiChatSession::whereKey($this->activeSessionId)->exists()) {
            return;
        }

        $session = AiChatSession::create([
            'user_id' => auth()->id(),
            'title' => 'Obrolan baru',
            'last_message_at' => now(),
        ]);

        $this->activeSessionId = $session->id;
        $this->storeMessage('assistant', self::GREETING);
        $this->loadSessions();
        $this->loadMessages();
    }

    private function storeMessage(string $role, string $text): void
    {
        if (! $this->activeSessionId) {
            return;
        }

        AiChatMessage::create([
            'ai_chat_session_id' => $this->activeSessionId,
            'role' => $role,
            'text' => $text,
        ]);

        $session = AiChatSession::find($this->activeSessionId);

        if (! $session) {
            return;
        }

        $session->last_message_at = now();

        if ($role === 'user' && ($session->title === 'Obrolan baru' || trim($session->title) === '')) {
            $session->title = Str::limit($text, 42, '');
        }

        $session->save();
        $this->loadSessions();
    }

    private function loadSessions(): void
    {
        $this->sessions = $this->sessionQuery()
            ->limit(20)
            ->get()
            ->map(fn (AiChatSession $session) => [
                'id' => $session->id,
                'title' => $session->title,
                'time' => optional($session->last_message_at ?? $session->updated_at)->diffForHumans(),
            ])
            ->all();
    }

    private function loadMessages(): void
    {
        if (! $this->activeSessionId) {
            $this->messages = [];

            return;
        }

        $this->messages = AiChatMessage::query()
            ->where('ai_chat_session_id', $this->activeSessionId)
            ->oldest()
            ->get(['role', 'text'])
            ->map(fn (AiChatMessage $message) => [
                'role' => $message->role,
                'text' => $message->text,
            ])
            ->all();
    }

    private function sessionQuery()
    {
        return AiChatSession::query()
            ->where('user_id', auth()->id())
            ->latest('last_message_at')
            ->latest('id');
    }

    private function filtersFromQuestion(string $question): array
    {
        $period = $this->periodFromQuestion($question);
        $outletId = $this->outletIdFromQuestion($question);

        return [
            'outlet_group' => $outletId ? null : $this->outletGroupFromQuestion($question),
            'outlet_id' => $outletId,
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

        return [
            'from' => null,
            'until' => null,
        ];
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

        if (Str::contains($text, ['grup ', 'group ', 'kelompok '])) {
            return null;
        }

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

    private function outletGroupFromQuestion(string $question): ?string
    {
        $text = $this->normalize($question);

        if (Str::contains($text, ['semua outlet', 'semua toko', 'global'])) {
            return null;
        }

        foreach (Outlet::GROUPS as $key => $label) {
            $terms = [
                $key,
                $label,
                str_replace('_', ' ', $key),
                'grup ' . $label,
                'group ' . $label,
                'kelompok ' . $label,
            ];

            foreach ($terms as $term) {
                if (str_contains($text, $this->normalize($term))) {
                    return $key;
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
