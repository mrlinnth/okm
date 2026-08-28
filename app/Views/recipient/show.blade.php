<!DOCTYPE html>
<html lang="my" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @php($cssVersion = (defined('FCPATH') && is_file(FCPATH . 'css/output.css')) ? filemtime(FCPATH . 'css/output.css') : '1')
    <link href="/css/output.css?v={{ $cssVersion }}" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.16.2/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-base-200 text-base-content">
    <header class="border-b border-base-300 bg-base-100 px-5 py-4">
        <p class="mx-auto max-w-md text-sm font-bold tracking-[0.18em]">OKM</p>
    </header>

    <main class="mx-auto flex min-h-[calc(100vh-57px)] w-full max-w-md items-center px-5 py-10">
        <section lang="my" class="w-full rounded-box border border-base-300 bg-base-100 p-6 text-center shadow-xl shadow-base-300/30 sm:p-8">
            @if ($state === 'active' && $subscription !== null)
                <div x-data="recipientKey({!! json_encode($subscription['accessUrl'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!})">
                    <p class="text-sm text-base-content/50">မင်္ဂလာပါ</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $subscription['recipientName'] }}</h1>
                    <p class="mt-5 text-sm text-base-content/55">{{ $subscription['expiryDate'] }} အထိ သက်တမ်းရှိသည်</p>

                    <div class="mt-4 rounded-box border border-base-300 bg-base-200 px-4 py-3 text-left">
                        <p class="break-all font-mono text-xs leading-6 text-base-content/75">{{ $subscription['accessUrl'] }}</p>
                    </div>
                    <button @click="copy()" class="btn btn-neutral mt-4 w-full" x-text="copied ? 'ကူးယူပြီးပါပြီ' : 'ကီးကုဒ် ကူးယူရန်'"></button>
                </div>
            @else
                <div class="py-1">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-base-200 text-base-content/45">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    @if ($state === 'disabled')
                        <h1 class="mt-5 text-lg font-bold">ဤစာရင်းသွင်းမှုကို လောလောဆယ် ပိတ်ထားပါသည်။</h1>
                    @elseif ($state === 'expired')
                        <h1 class="mt-5 text-lg font-bold">ဤစာရင်းသွင်းမှု သက်တမ်းကုန်သွားပါပြီ။</h1>
                    @else
                        <h1 class="mt-5 text-lg font-bold">ဤလင့်ခ်ကို ယခုအသုံးမပြုနိုင်သေးပါ။</h1>
                    @endif
                    <p class="mt-2 text-sm leading-6 text-base-content/55">အမှားဖြစ်သည်ဟု ထင်ပါက သင့်အက်ဒမင်ကို ဆက်သွယ်ပါ။</p>
                </div>
            @endif

            <footer class="mt-7 border-t border-base-300 pt-5">
                <p class="text-xs text-base-content/50">အကူအညီလိုပါသလား? သင့်အက်ဒမင်ကို မက်ဆေ့ချ်ပို့ပါ</p>
                <div class="mt-3 flex justify-center gap-2">
                    <a href="https://{{ $recipient->telegramHandle }}" class="btn btn-outline btn-sm">Telegram</a>
                    <a href="viber://chat?number={{ urlencode($recipient->viberNumber) }}" class="btn btn-outline btn-sm">Viber</a>
                </div>
            </footer>
        </section>
    </main>

    <script>
        function recipientKey(accessUrl) {
            return {
                copied: false,
                async copy() {
                    await navigator.clipboard.writeText(accessUrl);
                    this.copied = true;
                    window.setTimeout(() => { this.copied = false; }, 1500);
                },
            };
        }
    </script>
</body>
</html>
