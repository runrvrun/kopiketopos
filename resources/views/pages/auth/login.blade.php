<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Masuk')] class extends Component
{
    #[Validate('required|string|email', message: [
        'required' => 'Email wajib diisi.',
        'email' => 'Format email tidak valid.',
    ])]
    public string $email = '';

    #[Validate('required|string', message: [
        'required' => 'Kata sandi wajib diisi.',
    ])]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);
            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah. Silakan coba lagi.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirectIntended(route('dashboard'));
    }
};
?>

<main class="min-h-dvh md:grid md:grid-cols-2">
    {{-- Brand panel: compact on phones so the form sits high on the screen --}}
    <section class="relative isolate overflow-hidden bg-espresso px-5 pb-16 pt-[max(1.25rem,env(safe-area-inset-top))] text-cream md:flex md:flex-col md:justify-between md:px-12 md:py-12">
        {{-- Coffee bean pattern --}}
        <svg class="absolute inset-0 -z-10 size-full text-caramel/[0.09]" aria-hidden="true">
            <defs>
                <pattern id="beans" width="76" height="76" patternUnits="userSpaceOnUse" patternTransform="rotate(-18)">
                    <g fill="currentColor">
                        <ellipse cx="18" cy="20" rx="8" ry="11.5" />
                        <ellipse cx="56" cy="58" rx="8" ry="11.5" transform="rotate(35 56 58)" />
                    </g>
                    <g fill="none" stroke="#56392c" stroke-width="1.6" stroke-linecap="round">
                        <path d="M18 9.5c-3.5 4-3.5 8.5 0 10.5s3.5 6.5 0 10.5" />
                        <path d="M56 47.5c-3.5 4-3.5 8.5 0 10.5s3.5 6.5 0 10.5" transform="rotate(35 56 58)" />
                    </g>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#beans)" />
        </svg>
        <div class="absolute -bottom-24 -right-16 -z-10 size-72 rounded-full bg-caramel/20 blur-3xl md:size-[28rem]" aria-hidden="true"></div>

        <div class="flex items-center gap-2.5">
            <img src="{{ asset('favicon.png') }}" alt="" class="size-8 rounded-lg bg-foam p-0.5 shadow-lg shadow-espresso-950/30 md:size-10 md:rounded-xl md:p-1">
            <span class="text-xs font-medium uppercase tracking-[0.2em] text-caramel-300 md:text-sm">Sistem Kasir</span>
        </div>

        <div class="mt-4 flex items-center justify-between gap-4 md:mt-0 md:block">
            <div>
                <h2 class="font-display text-[1.4rem] font-semibold leading-[1.15] text-foam md:text-5xl">
                    No sugar.<br>Very low carb.<br><span class="text-caramel">Keto friendly</span>
                </h2>
                <p class="mt-5 hidden max-w-sm text-base text-cream/70 md:block">
                    Catat pesanan, terima pembayaran, dan pantau penjualan harian di setiap outlet Kopi Keto.
                </p>
            </div>

            {{-- Steaming cup --}}
            <svg class="w-16 shrink-0 md:mt-12 md:w-40" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                <g stroke="#d9b88f" stroke-width="3" stroke-linecap="round">
                    <path class="steam" d="M46 34c-5-6 5-10 0-17" />
                    <path class="steam steam-2" d="M60 34c-5-6 5-10 0-17" />
                    <path class="steam steam-3" d="M74 34c-5-6 5-10 0-17" />
                </g>
                <path d="M28 46h64v20a28 28 0 0 1-28 28h-8a28 28 0 0 1-28-28V46Z" fill="#bf9060" />
                <path d="M28 46h64v6H28z" fill="#a4774a" />
                <path d="M92 54h4a10 10 0 0 1 0 20h-5" stroke="#bf9060" stroke-width="6" stroke-linecap="round" />
                <ellipse cx="60" cy="102" rx="42" ry="6" fill="#2e1e17" opacity=".5" />
                <path d="M47 64c3.5-4 8.5-4 13 0s9.5 4 13 0" stroke="#f1e3cf" stroke-width="3" stroke-linecap="round" opacity=".8" />
            </svg>
        </div>

        <p class="hidden text-sm text-cream/50 md:block">Salam Kopi Nusantara</p>
    </section>

    {{-- Sign-in card --}}
    <section class="relative -mt-10 px-4 pb-[max(2rem,env(safe-area-inset-bottom))] md:mt-0 md:flex md:items-center md:justify-center md:px-12">
        <div class="mx-auto w-full max-w-sm">
            <div class="rounded-3xl bg-foam p-5 shadow-xl shadow-espresso/10 ring-1 ring-latte sm:p-7 md:bg-transparent md:p-0 md:shadow-none md:ring-0">
                <img src="{{ asset('images/logo.png') }}" alt="Kopi Keto" class="h-6 w-auto md:h-9">

                <h1 class="mt-4 font-display text-xl font-semibold text-espresso-900 md:mt-6 md:text-2xl">Selamat datang</h1>
                <p class="mt-0.5 text-sm text-roast">Masuk untuk mulai melayani pelanggan.</p>

                <form wire:submit="login" class="mt-5 space-y-4" novalidate>
                    <div>
                        <label for="email" class="block text-sm font-medium text-espresso-800">Email</label>
                        <input
                            wire:model="email"
                            id="email"
                            type="email"
                            inputmode="email"
                            autocomplete="username"
                            autocapitalize="none"
                            autofocus
                            required
                            @class([
                                'mt-1.5 block h-12 w-full rounded-xl border bg-white px-4 text-base text-espresso-900 placeholder:text-roast/50 transition focus:outline-none focus:ring-4',
                                'border-latte focus:border-caramel focus:ring-caramel/20' => ! $errors->has('email'),
                                'border-cherry/60 focus:border-cherry focus:ring-cherry/15' => $errors->has('email'),
                            ])
                            placeholder="nama@kopiketo.com"
                            aria-describedby="email-error"
                        >
                        @error('email')
                            <p id="email-error" class="mt-1.5 flex items-start gap-1.5 text-sm text-cherry" role="alert">
                                <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div x-data="{ show: false }">
                        <label for="password" class="block text-sm font-medium text-espresso-800">Kata sandi</label>
                        <div class="relative mt-1.5">
                            <input
                                wire:model="password"
                                id="password"
                                x-bind:type="show ? 'text' : 'password'"
                                type="password"
                                autocomplete="current-password"
                                required
                                @class([
                                    'block h-12 w-full rounded-xl border bg-white pl-4 pr-12 text-base text-espresso-900 transition focus:outline-none focus:ring-4',
                                    'border-latte focus:border-caramel focus:ring-caramel/20' => ! $errors->has('password'),
                                    'border-cherry/60 focus:border-cherry focus:ring-cherry/15' => $errors->has('password'),
                                ])
                            >
                            <button
                                type="button"
                                x-on:click="show = !show"
                                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-roast transition hover:text-espresso focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-caramel"
                                x-bind:aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                aria-label="Tampilkan kata sandi"
                            >
                                <svg x-show="!show" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="show" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-sm text-cherry" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-espresso-800 select-none">
                        <input wire:model="remember" type="checkbox" class="size-5 rounded-md border-latte accent-espresso">
                        Tetap masuk di perangkat ini
                    </label>

                    <button
                        type="submit"
                        class="relative flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-espresso text-base font-semibold text-foam shadow-lg shadow-espresso/25 transition hover:bg-espresso-800 active:scale-[0.99] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-caramel disabled:cursor-wait disabled:opacity-80"
                        wire:loading.attr="disabled"
                        wire:target="login"
                    >
                        <span wire:loading.remove wire:target="login">Masuk</span>
                        <span wire:loading.flex wire:target="login" class="items-center gap-2">
                            <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                            Menyeduh&hellip;
                        </span>
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-roast">
                    Lupa kata sandi? Minta admin toko untuk mengatur ulang.
                </p>
            </div>

            <p class="mt-6 text-center text-xs text-roast/80 md:hidden">Salam Kopi Nusantara</p>
        </div>
    </section>
</main>
