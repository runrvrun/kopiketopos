@php
    $user = auth()->user();
    $stores = $user->accessibleStores();
    $isAdmin = $user->isAdmin();

    $menu = [
        ['label' => 'Home', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home'],
        ['label' => 'Transaksi', 'route' => 'transactions.index', 'active' => 'transactions.*', 'icon' => 'receipt'],
    ];

    $adminMenu = [
        ['label' => 'Analisa Penjualan', 'route' => 'admin.analytics', 'active' => 'admin.analytics', 'icon' => 'chart'],
        ['label' => 'Pelanggan', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*', 'icon' => 'user'],
        ['label' => 'Pengguna', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'users'],
        ['label' => 'Toko', 'route' => 'admin.stores.index', 'active' => 'admin.stores.*', 'icon' => 'store'],
        ['label' => 'Produk', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'icon' => 'coffee'],
        ['label' => 'Kategori', 'route' => 'admin.categories.index', 'active' => 'admin.categories.*', 'icon' => 'tag'],
        ['label' => 'Rekening', 'route' => 'admin.accounts.index', 'active' => 'admin.accounts.*', 'icon' => 'bank'],
        ['label' => 'Unduh Riwayat', 'route' => 'admin.history', 'active' => 'admin.history', 'icon' => 'download'],
    ];

    $showFab = ! request()->routeIs('transactions.create', 'transactions.edit');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh" x-data="{ drawer: false }" x-on:keydown.escape.window="drawer = false">
    {{-- Header --}}
    <header class="sticky top-0 z-30 border-b border-latte/80 bg-foam/95 pt-[env(safe-area-inset-top)] backdrop-blur" data-print-hide>
        <div class="mx-auto flex h-14 max-w-6xl items-center gap-1 px-2 sm:px-4">
            <button type="button" class="btn-icon md:hidden" x-on:click="drawer = true" aria-label="Buka menu">
                <x-icon name="menu" class="size-6" />
            </button>

            <a href="{{ route('dashboard') }}" class="shrink-0 px-1">
                <img src="{{ asset('images/logo-tnav.png') }}" alt="Kopi Keto" class="h-5 w-auto sm:h-6">
            </a>

            {{-- Desktop menu --}}
            <nav class="ml-5 hidden items-center gap-1 md:flex" aria-label="Menu utama">
                @foreach ($menu as $item)
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'rounded-full px-3.5 py-2 text-sm font-medium transition',
                           'bg-espresso text-foam' => request()->routeIs($item['active']),
                           'text-roast hover:bg-latte/60 hover:text-espresso' => ! request()->routeIs($item['active']),
                       ])>{{ $item['label'] }}</a>
                @endforeach

                @can('admin')
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button type="button" x-on:click="open = !open"
                            @class([
                                'inline-flex items-center gap-1 rounded-full px-3.5 py-2 text-sm font-medium transition',
                                'bg-espresso text-foam' => request()->routeIs('admin.*'),
                                'text-roast hover:bg-latte/60 hover:text-espresso' => ! request()->routeIs('admin.*'),
                            ])
                            x-bind:aria-expanded="open">
                            Admin <x-icon name="chevron-down" class="size-4" />
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.left
                             class="absolute left-0 top-full mt-2 w-56 overflow-hidden rounded-2xl bg-foam p-1.5 shadow-xl shadow-espresso/10 ring-1 ring-latte">
                            @foreach ($adminMenu as $item)
                                <a href="{{ route($item['route']) }}"
                                   @class([
                                       'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition',
                                       'bg-caramel-100 font-medium text-espresso' => request()->routeIs($item['active']),
                                       'text-espresso-800 hover:bg-cream' => ! request()->routeIs($item['active']),
                                   ])>
                                    <x-icon :name="$item['icon']" class="size-[18px] text-caramel-600" />
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endcan
            </nav>

            <div class="ml-auto flex min-w-0 items-center gap-1.5">
                {{-- Store selector --}}
                @if ($stores->count() > 1 || ($storeContext->isAll() && $stores->isNotEmpty()))
                    <form method="POST" action="{{ route('store.select') }}" class="min-w-0">
                        @csrf
                        <label class="relative flex min-w-0 items-center">
                            <span class="sr-only">Pilih toko</span>
                            <x-icon name="store" class="pointer-events-none absolute left-2.5 size-4 text-caramel-600" />
                            <select name="store" onchange="this.form.submit()"
                                class="h-9 min-w-0 max-w-[9.5rem] appearance-none truncate rounded-full bg-cream py-0 pl-8 pr-7 text-sm font-medium text-espresso-900 ring-1 ring-latte transition focus:outline-none focus:ring-2 focus:ring-caramel sm:max-w-[13rem]">
                                @if ($user->hasAllStores())
                                    <option value="all" @selected($storeContext->isAll())>Semua toko</option>
                                @endif
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}" @selected($storeContext->currentId() === $store->id)>{{ $store->name }}</option>
                                @endforeach
                            </select>
                            <x-icon name="chevron-down" class="pointer-events-none absolute right-2 size-4 text-roast" />
                        </label>
                    </form>
                @else
                    <span class="inline-flex h-9 min-w-0 items-center gap-1.5 rounded-full bg-cream px-3 text-sm font-medium text-espresso-900 ring-1 ring-latte">
                        <x-icon name="store" class="size-4 text-caramel-600" />
                        <span class="truncate">{{ $storeContext->label() }}</span>
                    </span>
                @endif

                {{-- Signed-in user (compact) --}}
                <div class="ml-1 hidden items-center gap-2 sm:flex" title="{{ $user->email }}">
                    <span class="flex size-8 items-center justify-center rounded-full bg-caramel-100 text-sm font-semibold text-caramel-700">{{ $user->initials() }}</span>
                    <span class="hidden flex-col leading-tight lg:flex">
                        <span class="max-w-[8rem] truncate text-sm font-medium text-espresso-900">{{ $user->name }}</span>
                        <span class="text-[11px] text-roast">{{ $isAdmin ? 'Admin' : 'Staf' }}</span>
                    </span>
                </div>

                {{-- Phones sign out from the drawer menu --}}
                <form method="POST" action="{{ route('logout') }}" class="hidden md:block">
                    @csrf
                    <button type="submit" class="btn-icon" aria-label="Keluar" title="Keluar">
                        <x-icon name="logout" />
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Mobile drawer --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="Menu">
        <div x-show="drawer" x-transition.opacity class="absolute inset-0 bg-espresso-950/40" x-on:click="drawer = false"></div>
        <aside x-show="drawer"
               x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
               class="absolute inset-y-0 left-0 flex w-[82%] max-w-xs flex-col bg-foam pb-[env(safe-area-inset-bottom)] pt-[env(safe-area-inset-top)] shadow-2xl">
            <div class="flex items-center justify-between px-4 py-3">
                <img src="{{ asset('images/logo-tnav.png') }}" alt="Kopi Keto" class="h-5 w-auto">
                <button type="button" class="btn-icon" x-on:click="drawer = false" aria-label="Tutup menu"><x-icon name="x" /></button>
            </div>

            <div class="mx-4 flex items-center gap-3 rounded-2xl bg-cream p-3">
                <span class="flex size-10 items-center justify-center rounded-full bg-caramel-100 font-semibold text-caramel-700">{{ $user->initials() }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-espresso-900">{{ $user->name }}</p>
                    <p class="truncate text-xs text-roast">{{ $isAdmin ? 'Admin' : 'Staf' }} · {{ $storeContext->label() }}</p>
                </div>
            </div>

            <nav class="mt-4 flex-1 overflow-y-auto px-3" aria-label="Menu utama">
                @foreach ($menu as $item)
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium transition',
                           'bg-espresso text-foam' => request()->routeIs($item['active']),
                           'text-espresso-900 hover:bg-cream' => ! request()->routeIs($item['active']),
                       ])>
                        <x-icon :name="$item['icon']" />
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @can('admin')
                    <p class="mb-1 mt-5 px-3 text-xs font-semibold uppercase tracking-wider text-roast">Admin</p>
                    @foreach ($adminMenu as $item)
                        <a href="{{ route($item['route']) }}"
                           @class([
                               'flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] transition',
                               'bg-caramel-100 font-medium text-espresso' => request()->routeIs($item['active']),
                               'text-espresso-900 hover:bg-cream' => ! request()->routeIs($item['active']),
                           ])>
                            <x-icon :name="$item['icon']" class="text-caramel-600" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @endcan
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="border-t border-latte p-3">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-[15px] text-roast transition hover:bg-cream hover:text-espresso">
                    <x-icon name="logout" /> Keluar
                </button>
            </form>
        </aside>
    </div>

    {{-- Flash message --}}
    @if (session('status'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.opacity
             class="fixed inset-x-0 top-[calc(4rem+env(safe-area-inset-top))] z-40 flex justify-center px-4" data-print-hide>
            <div class="flex items-center gap-2 rounded-full bg-espresso-900 px-4 py-2.5 text-sm font-medium text-foam shadow-lg" role="status">
                <x-icon name="check" class="size-4 text-caramel-300" /> {{ session('status') }}
            </div>
        </div>
    @endif

    <main @class(['mx-auto max-w-6xl px-4 pt-5 sm:px-6', 'pb-28' => $showFab, 'pb-8' => ! $showFab])>
        {{ $slot }}
    </main>

    {{-- New transaction button --}}
    @if ($showFab)
        <a href="{{ route('transactions.create') }}"
           class="fixed bottom-[calc(1.25rem+env(safe-area-inset-bottom))] right-4 z-30 inline-flex h-14 items-center gap-2 rounded-full bg-caramel pl-4 pr-5 text-[15px] font-semibold text-espresso-950 shadow-xl shadow-espresso/25 ring-4 ring-cream transition hover:bg-caramel-600 hover:text-foam active:scale-95 sm:right-6"
           aria-label="Transaksi baru" data-print-hide>
            <x-icon name="plus" class="size-6" />
            <span>Transaksi</span>
        </a>
    @endif
</body>
</html>
