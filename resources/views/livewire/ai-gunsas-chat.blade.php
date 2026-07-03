<div style="position: fixed; right: 24px; bottom: 24px; z-index: 2147483647;">
    <style>
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
    </style>

    @if ($isOpen)
        <div
            class="mb-3 flex h-[min(680px,calc(100vh-7rem))] w-[min(420px,calc(100vw-2.5rem))] flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900"
            style="width: min(420px, calc(100vw - 40px)); height: min(680px, calc(100vh - 112px)); margin-bottom: 12px; display: flex; flex-direction: column; overflow: hidden; border-radius: 12px; border: 1px solid #e5e7eb; background: white; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">AI Gunsas</p>
                    <p class="text-xs text-gray-500">Analyst read-only</p>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="clearChat"
                        class="rounded-md px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    >
                        Clear
                    </button>
                    <button
                        type="button"
                        wire:click="toggle"
                        class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        aria-label="Tutup AI Gunsas"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto p-4">
                @foreach ($messages as $message)
                    <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="{{ $message['role'] === 'user' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' }} ai-gunsas-message max-w-[85%] rounded-lg px-3 py-2 text-sm leading-relaxed">
                            @if ($message['role'] === 'assistant')
                                {!! \Illuminate\Support\Str::markdown($message['text']) !!}
                            @else
                                <span class="whitespace-pre-line">{{ $message['text'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div wire:loading.flex wire:target="ask" class="justify-start">
                    <div class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-500 dark:bg-gray-800">
                        AI Gunsas sedang membaca data...
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="ask" class="border-t border-gray-200 p-3 dark:border-gray-800">
                <div class="flex gap-2">
                    <textarea
                        wire:model="question"
                        wire:keydown.enter.prevent="ask"
                        rows="2"
                        placeholder="Tanya profit, margin, loss KG, retur..."
                        class="min-h-11 flex-1 resize-none rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                    ></textarea>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="ask"
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-primary-600 text-white shadow-sm hover:bg-primary-500 disabled:opacity-60"
                        aria-label="Kirim pertanyaan"
                    >
                        <x-heroicon-o-paper-airplane class="h-5 w-5" />
                    </button>
                </div>
            </form>
        </div>
    @endif

    <button
        type="button"
        wire:click="toggle"
        class="ml-auto flex items-center gap-2 rounded-full bg-white text-gray-950 shadow-xl ring-2 ring-white transition hover:scale-[1.02] dark:bg-gray-900 dark:text-white dark:ring-gray-800"
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
