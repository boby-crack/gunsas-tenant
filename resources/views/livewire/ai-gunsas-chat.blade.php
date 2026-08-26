<div
    x-data="{
        open: @entangle('isOpen').live,
        teaser: false,
        scrollToBottom() {
            const el = this.$refs.messages;

            if (! el) {
                return;
            }

            requestAnimationFrame(() => {
                el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            });
        },
    }"
    x-on:ai-gunsas-question-sent.window="$nextTick(() => { scrollToBottom(); $wire.answerPending(); })"
    x-on:ai-gunsas-answer-received.window="$nextTick(() => scrollToBottom())"
    x-effect="if (open) { teaser = false; $nextTick(() => scrollToBottom()); }"
    style="position: fixed; left: 8px; right: 8px; bottom: 8px; z-index: 2147483647; display: flex; justify-content: flex-end; pointer-events: none;"
>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .ai-gunsas-message p {
            margin: 0 0 0.55rem;
        }

        .ai-gunsas-message p:last-child {
            margin-bottom: 0;
        }

        .ai-gunsas-message ul,
        .ai-gunsas-message ol {
            margin: 0.45rem 0 0.65rem 1.15rem;
            padding: 0;
        }

        .ai-gunsas-message ul {
            list-style: disc;
        }

        .ai-gunsas-message ol {
            list-style: decimal;
        }

        .ai-gunsas-message li {
            margin: 0.2rem 0;
        }

        .ai-gunsas-message strong {
            font-weight: 700;
        }

        .ai-gunsas-tab {
            align-items: center;
            background: #f26a00;
            border: 0;
            border-radius: 9999px 0 0 9999px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.28);
            color: white;
            cursor: pointer;
            display: flex;
            height: 64px;
            justify-content: center;
            pointer-events: auto;
            width: 28px;
        }

        .ai-gunsas-pill {
            pointer-events: auto;
        }

        @media (max-width: 640px) {
            .ai-gunsas-tab {
                border-radius: 9999px;
                height: 44px;
                margin-right: 0;
                width: 44px;
            }

            .ai-gunsas-pill {
                margin-right: 0;
                max-width: calc(100vw - 16px);
            }
        }
    </style>

    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-3 scale-95"
        class="mb-3 flex origin-bottom-right overflow-hidden rounded-lg border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900"
        style="width: min(760px, calc(100vw - 16px)); height: min(680px, calc(100dvh - 80px)); margin-bottom: 12px; display: flex; overflow: hidden; border-radius: 12px; border: 1px solid rgba(148, 163, 184, .24); box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25); pointer-events: auto;"
    >
        <aside class="hidden w-56 shrink-0 flex-col border-r border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950 sm:flex">
            <div class="border-b border-gray-200 p-3 dark:border-gray-800">
                <button
                    type="button"
                    wire:click="newChat"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                >
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Chat Baru
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-2">
                <div class="flex items-center justify-between px-2 pb-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Riwayat</p>
                    @if ($sessions !== [])
                        <button
                            type="button"
                            wire:click="deleteAllHistory"
                            wire:confirm="Hapus semua riwayat chat AI Gunsas?"
                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-danger-600 dark:hover:bg-gray-900"
                            aria-label="Hapus semua riwayat"
                            title="Hapus semua riwayat"
                        >
                            <x-heroicon-o-trash class="h-4 w-4" />
                        </button>
                    @endif
                </div>

                <div class="space-y-1">
                    @forelse ($sessions as $session)
                        <div class="group flex items-center gap-1">
                            <button
                                type="button"
                                wire:click="selectSession({{ $session['id'] }})"
                                class="min-w-0 flex-1 rounded-md px-2 py-2 text-left text-sm transition {{ $activeSessionId === $session['id'] ? 'bg-white font-semibold text-gray-950 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-600 hover:bg-white hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-900 dark:hover:text-white' }}"
                            >
                                <span class="block truncate">{{ $session['title'] }}</span>
                                <span class="block truncate text-xs font-normal text-gray-400">{{ $session['time'] }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="deleteSession({{ $session['id'] }})"
                                wire:confirm="Hapus riwayat chat ini?"
                                class="rounded-md p-1.5 text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-danger-600 group-hover:opacity-100 dark:hover:bg-gray-900"
                                aria-label="Hapus riwayat"
                            >
                                <x-heroicon-o-trash class="h-4 w-4" />
                            </button>
                        </div>
                    @empty
                        <p class="px-2 text-sm text-gray-500">Belum ada riwayat.</p>
                    @endforelse
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 dark:border-gray-800 sm:px-4">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-950 dark:text-gray-950">AI Gunsas</p>
                    <p class="text-xs text-gray-500">Konsultan bisnis read-only</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        wire:click="newChat"
                        class="rounded-md px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200 sm:hidden"
                    >
                        Baru
                    </button>
                    <button
                        type="button"
                        wire:click="clearChat"
                        class="rounded-md px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    >
                        Clear
                    </button>
                    <button
                        type="button"
                        x-on:click="open = false; teaser = false; $wire.closeChat()"
                        class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        aria-label="Tutup AI Gunsas"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <div x-ref="messages" class="flex-1 space-y-3 overflow-y-auto p-3 sm:p-4">
                @foreach ($messages as $message)
                    <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="{{ $message['role'] === 'user' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' }} ai-gunsas-message max-w-[92%] rounded-lg px-3 py-2 text-sm leading-relaxed sm:max-w-[85%]">
                            @if ($message['role'] === 'assistant')
                                {!! \Illuminate\Support\Str::markdown($message['text'], [
                                    'html_input' => 'strip',
                                    'allow_unsafe_links' => false,
                                ]) !!}
                            @else
                                <span class="whitespace-pre-line">{{ $message['text'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div wire:loading.flex wire:target="answerPending" class="justify-start">
                    <div class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-500 dark:bg-gray-800">
                        AI Gunsas sedang membaca data...
                    </div>
                </div>
            </div>

            <form x-ref="form" wire:submit.prevent="ask" class="border-t border-gray-200 p-3 dark:border-gray-800">
                <div class="flex min-w-0 gap-2">
                    <textarea
                        wire:model="question"
                        x-on:keydown.enter.prevent="$refs.form.requestSubmit()"
                        wire:loading.attr="disabled"
                        wire:target="ask,answerPending"
                        rows="2"
                        placeholder="Tanya profit, margin, loss KG, retur..."
                        class="min-h-11 min-w-0 flex-1 resize-none rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                    ></textarea>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="ask,answerPending"
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-primary-600 text-white shadow-sm hover:bg-primary-500 disabled:opacity-60"
                        aria-label="Kirim pertanyaan"
                    >
                        <x-heroicon-o-paper-airplane class="h-5 w-5" />
                    </button>
                </div>
            </form>
        </div>
    </div>

    <button
        type="button"
        x-cloak
        x-show="! open && ! teaser"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-2"
        x-on:click="teaser = true"
        class="ai-gunsas-tab ml-auto transition hover:bg-primary-500"
        aria-label="Tampilkan AI Gunsas"
        title="AI Gunsas"
    >
        <x-heroicon-o-chevron-left class="h-5 w-5" />
    </button>

    <button
        type="button"
        x-cloak
        x-show="! open && teaser"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-4 scale-95"
        x-transition:enter-end="opacity-100 translate-x-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0 scale-100"
        x-transition:leave-end="opacity-0 translate-x-4 scale-95"
        x-on:click="open = true; teaser = false; $wire.openChat()"
        class="ai-gunsas-pill mr-3 flex items-center gap-2 rounded-full bg-white text-gray-950 shadow-xl ring-2 ring-white transition duration-200 hover:scale-[1.02] dark:bg-gray-900 dark:text-white dark:ring-gray-800"
        style="display: flex; height: 58px; align-items: center; gap: 8px; border: 0; border-radius: 9999px; background: white; color: #111827; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.35); cursor: pointer; font-family: Arial, sans-serif; padding: 8px 14px 8px 8px;"
        aria-label="Buka AI Gunsas"
    >
        <span style="display: flex; width: 42px; height: 42px; align-items: center; justify-content: center; overflow: hidden; border-radius: 9999px; background: #fff7ed;">
            <img src="{{ asset('assets/logogunsas.png') }}" alt="" style="width: 34px; height: 34px; object-fit: contain;" />
        </span>
        <span style="display: flex; flex-direction: column; align-items: flex-start; line-height: 1;">
            <span style="font-size: 11px; font-weight: 700; color: #f26a00; letter-spacing: .04em;">AI</span>
            <span style="font-size: 13px; font-weight: 800; color: #111827;">Gunsas</span>
        </span>
    </button>
</div>
