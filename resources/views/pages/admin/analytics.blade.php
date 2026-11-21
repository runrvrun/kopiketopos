<?php

use App\Support\SalesAnalytics;
use App\Support\StoreContext;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Analisa Penjualan')] class extends Component
{
    #[Url(as: 'periode', except: '30h')]
    public string $period = '30h';

    public function with(): array
    {
        $context = StoreContext::for();

        return (new SalesAnalytics($context->storeIds(), $this->period))->toArray() + [
            'periods' => SalesAnalytics::PERIODS,
            'storeLabel' => $context->label(),
            // Sequential ramp for the busy-hours heatmap (validated: one hue, monotone, light end ≥ 2:1 on white).
            'ramp' => ['#cfa77a', '#b88752', '#946538', '#6b462c', '#3d2419'],
        ];
    }
};
?>

@php
    $heatColor = function (int $n) use ($heatmap, $ramp) {
        if ($n === 0 || $heatmap['max'] === 0) return '#f4f2ef';
        return $ramp[min(4, (int) floor(($n / $heatmap['max']) * 5 - 0.0001))];
    };
@endphp

<div class="space-y-6">
    {{-- Header + period filter (scopes everything below) --}}
    <div>
        <h1 class="page-title">Analisa Penjualan</h1>
        <p class="text-sm text-roast">{{ $storeLabel }} · {{ $rangeLabel }}</p>

        <div class="no-scrollbar -mx-4 mt-3 flex gap-2 overflow-x-auto px-4" role="radiogroup" aria-label="Periode">
            @foreach ($periods as $value => $label)
                <button type="button" wire:click="$set('period', '{{ $value }}')" role="radio" aria-checked="{{ $period === $value ? 'true' : 'false' }}"
                    @class([
                        'h-9 shrink-0 rounded-full px-4 text-sm font-medium transition',
                        'bg-espresso text-foam' => $period === $value,
                        'bg-white text-espresso-800 ring-1 ring-latte hover:bg-cream' => $period !== $value,
                    ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div wire:loading.class="opacity-60" class="space-y-6 transition-opacity">
    @unless ($hasData)
        <div class="card p-8 text-center">
            <p class="font-medium text-espresso-900">Belum ada transaksi lunas di periode ini.</p>
            <p class="mt-1 text-sm text-roast">Pilih periode yang lebih panjang atau toko lain.</p>
        </div>
    @else
        {{-- KPI row --}}
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Ringkasan">
            @foreach ($kpis as $kpi)
                <div @class(['card p-4', 'col-span-2 lg:col-span-1' => $loop->first])>
                    <p class="text-sm text-roast">{{ $kpi['label'] }}</p>
                    <p @class(['mt-1 font-semibold text-espresso-900', 'text-3xl' => $loop->first, 'text-xl' => ! $loop->first])>{{ $kpi['value'] }}</p>
                    @if ($kpi['delta'] !== null)
                        @php $good = ($kpi['delta'] >= 0) === $kpi['upIsGood']; @endphp
                        <p @class(['mt-1 flex items-center gap-1 text-xs font-medium', 'text-[#1f7a45]' => $good && $kpi['delta'] != 0, 'text-cherry' => ! $good && $kpi['delta'] != 0, 'text-roast' => $kpi['delta'] == 0])>
                            <x-icon name="chevron-down" @class(['size-3.5', 'rotate-180' => $kpi['delta'] > 0, 'hidden' => $kpi['delta'] == 0]) />
                            {{ $kpi['delta'] > 0 ? '+' : '' }}{{ $kpi['delta'] }}%
                            <span class="font-normal text-roast">vs sebelumnya</span>
                        </p>
                    @endif
                </div>
            @endforeach
        </section>
        <p class="-mt-3 text-xs text-roast">Dibandingkan dengan {{ $prevLabel }}.</p>

        {{-- Suggestions --}}
        @if ($insights)
            <section class="card overflow-hidden" aria-labelledby="saran">
                <h2 id="saran" class="flex items-center gap-2 border-b border-latte/70 px-4 py-3 font-semibold text-espresso-900">
                    <x-icon name="trend" class="size-5 text-caramel-600" /> Saran untuk meningkatkan penjualan
                </h2>
                <ul class="divide-y divide-latte/70">
                    @foreach ($insights as $tip)
                        <li class="flex gap-3 px-4 py-3">
                            <span @class([
                                'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg',
                                'bg-[#e8f4ec] text-[#1f7a45]' => $tip['tone'] === 'good',
                                'bg-[#fbeee9] text-cherry' => $tip['tone'] === 'warn',
                                'bg-caramel-100 text-caramel-700' => $tip['tone'] === 'info',
                            ])><x-icon :name="$tip['icon']" class="size-[18px]" /></span>
                            <p class="text-sm leading-relaxed text-espresso-900">{{ $tip['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Sales trend --}}
        <section class="card p-4" aria-labelledby="tren"
            x-data="{ active: null, bars: @js(collect($trend['bars'])->map(fn ($b) => ['label' => $b['label'], 'net' => rupiah($b['net']), 'count' => $b['count']])) }">
            <h2 id="tren" class="font-semibold text-espresso-900">Penjualan per {{ $trend['unit'] }}</h2>

            {{-- Readout: the tapped/hovered bar, or the summary --}}
            <div class="mt-1 h-10 text-sm" aria-live="polite">
                <template x-if="active !== null">
                    <p><span class="font-semibold text-espresso-900" x-text="bars[active].net"></span>
                       <span class="text-roast" x-text="' · ' + bars[active].count + ' transaksi'"></span><br>
                       <span class="text-xs text-roast" x-text="bars[active].label"></span></p>
                </template>
                <template x-if="active === null">
                    <p><span class="text-roast">Rata-rata</span> <span class="font-semibold text-espresso-900">{{ rupiah($trend['avg']) }}</span> <span class="text-roast">/ {{ $trend['unit'] }}</span><br>
                       <span class="text-xs text-roast">Tertinggi: {{ $trend['best']['label'] }} ({{ rupiah($trend['best']['net']) }})</span></p>
                </template>
            </div>

            <div class="relative mt-3">
                <span class="absolute -top-1 left-0 text-[11px] tabular-nums text-roast">{{ rupiah_short($trend['max']) }}</span>
                <div class="flex h-40 items-end gap-[2px] border-b border-latte pt-4" x-on:mouseleave="active = null">
                    @foreach ($trend['bars'] as $i => $bar)
                        <button type="button" class="group flex h-full flex-1 items-end justify-center focus:outline-none"
                            x-on:mouseenter="active = {{ $i }}" x-on:focus="active = {{ $i }}" x-on:click="active = active === {{ $i }} ? null : {{ $i }}"
                            aria-label="{{ $bar['label'] }}: {{ rupiah($bar['net']) }}, {{ $bar['count'] }} transaksi">
                            <span class="block w-full max-w-6 rounded-t-[4px] transition-colors"
                                :class="active === {{ $i }} ? 'bg-espresso' : 'bg-caramel-600 group-hover:bg-caramel-700'"
                                style="height: {{ max($bar['net'] > 0 ? 2 : 0, $bar['pct']) }}%"></span>
                        </button>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-[2px]" aria-hidden="true">
                    @foreach ($trend['bars'] as $bar)
                        <span class="flex-1 overflow-visible whitespace-nowrap text-center text-[10px] text-roast">{{ $bar['showTick'] ? $bar['short'] : '' }}</span>
                    @endforeach
                </div>
            </div>

            <details class="mt-3 text-sm">
                <summary class="cursor-pointer text-xs font-medium text-caramel-700">Lihat tabel</summary>
                <table class="mt-2 w-full text-xs">
                    <thead><tr class="text-left text-roast"><th class="py-1 font-medium">{{ ucfirst($trend['unit']) }}</th><th class="py-1 text-right font-medium">Penjualan</th><th class="py-1 text-right font-medium">Trx</th></tr></thead>
                    <tbody class="divide-y divide-latte/60 tabular-nums">
                        @foreach (array_reverse($trend['bars']) as $bar)
                            <tr><td class="py-1">{{ $bar['label'] }}</td><td class="py-1 text-right">{{ rupiah($bar['net']) }}</td><td class="py-1 text-right">{{ $bar['count'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Best sellers --}}
            <section class="card p-4" aria-labelledby="terlaris">
                <h2 id="terlaris" class="font-semibold text-espresso-900">Produk terlaris</h2>
                <p class="text-xs text-roast">Berdasarkan pendapatan · {{ $products['distinctSold'] }} produk terjual</p>
                <ol class="mt-3 space-y-3">
                    @foreach ($products['top'] as $p)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate text-espresso-900"><span class="mr-1.5 text-xs tabular-nums text-roast">{{ $loop->iteration }}</span>{{ $p['name'] }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-espresso-900">{{ rupiah_short($p['revenue']) }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="h-2.5 rounded-r-[4px] bg-caramel-600" style="width: {{ max(1, $p['pct']) }}%"></span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-roast">{{ number_format($p['qty'], 0, ',', '.') }} terjual · {{ number_format($p['share'], 1, ',', '.') }}% pendapatan</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Movers --}}
            <section class="card p-4" aria-labelledby="perubahan">
                <h2 id="perubahan" class="font-semibold text-espresso-900">Naik &amp; turun</h2>
                <p class="text-xs text-roast">Perubahan pendapatan produk vs periode sebelumnya</p>
                @foreach (['rising' => ['Naik', 'text-[#1f7a45]', true], 'falling' => ['Turun', 'text-cherry', false]] as $key => [$title, $color, $up])
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wider text-roast">{{ $title }}</h3>
                    <ul class="mt-1 divide-y divide-latte/60">
                        @forelse ($products[$key] as $m)
                            <li class="flex items-center gap-3 py-2 text-sm">
                                <x-icon name="chevron-down" @class(['size-4 shrink-0', $color, 'rotate-180' => $up]) />
                                <span class="min-w-0 flex-1 truncate text-espresso-900">{{ $m['name'] }}</span>
                                <span class="shrink-0 text-right">
                                    <span class="block font-semibold tabular-nums text-espresso-900">{{ $m['change'] > 0 ? '+' : '−' }}{{ rupiah_short(abs($m['change'])) }}</span>
                                    <span class="block text-[11px] text-roast">{{ $m['pctChange'] === null ? 'baru terjual' : ($m['pctChange'] > 0 ? '+' : '').$m['pctChange'].'%' }}</span>
                                </span>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-roast">Tidak ada perubahan berarti.</li>
                        @endforelse
                    </ul>
                @endforeach
            </section>
        </div>

        {{-- Busy hours heatmap --}}
        <section class="card p-4" aria-labelledby="jam"
            x-data="{ cell: null }">
            <h2 id="jam" class="font-semibold text-espresso-900">Waktu ramai</h2>
            <p class="text-xs text-roast">Jumlah transaksi per hari &amp; jam</p>

            <div class="mt-1 h-5 text-sm" aria-live="polite">
                <p x-show="cell" x-cloak class="text-espresso-900" x-text="cell"></p>
                <p x-show="!cell" class="text-roast">
                    @if ($heatmap['peakHour'] !== null) Tersibuk <span class="font-semibold text-espresso-900">{{ sprintf('%02d:00', $heatmap['peakHour']) }}</span>@endif
                    @if ($heatmap['busiestDay']) · hari <span class="font-semibold text-espresso-900">{{ $heatmap['days'][$heatmap['busiestDay']] }}</span>@endif
                </p>
            </div>

            <div class="mt-2 overflow-x-auto" x-on:mouseleave="cell = null">
                <table class="w-full border-separate" style="border-spacing: 2px">
                    <thead>
                        <tr>
                            <th class="w-7"></th>
                            @foreach ($heatmap['hours'] as $h)
                                <th class="text-[10px] font-normal text-roast">{{ $h % 3 === 0 ? $h : '' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($heatmap['days'] as $d => $dayName)
                            <tr>
                                <th class="pr-1 text-left text-[11px] font-normal text-roast">{{ mb_substr($dayName, 0, 3) }}</th>
                                @foreach ($heatmap['hours'] as $h)
                                    @php $n = $heatmap['grid'][$d][$h] ?? 0; $text = sprintf('%s, %02d:00–%02d:00 · %d transaksi', $dayName, $h, $h + 1, $n); @endphp
                                    <td class="p-0">
                                        <button type="button" class="block aspect-square w-full min-w-3 rounded-[3px] focus:outline-2 focus:outline-offset-1 focus:outline-espresso"
                                            style="background: {{ $heatColor($n) }}"
                                            x-on:mouseenter="cell = @js($text)" x-on:focus="cell = @js($text)" x-on:click="cell = @js($text)"
                                            aria-label="{{ $text }}"></button>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-2 flex items-center justify-end gap-1.5 text-[11px] text-roast" aria-hidden="true">
                Sepi
                <span class="size-3 rounded-[3px]" style="background:#f4f2ef"></span>
                @foreach ($ramp as $c)<span class="size-3 rounded-[3px]" style="background: {{ $c }}"></span>@endforeach
                Ramai
            </div>

            <details class="mt-2 text-sm">
                <summary class="cursor-pointer text-xs font-medium text-caramel-700">Lihat tabel</summary>
                <table class="mt-2 w-full text-xs">
                    <thead><tr class="text-left text-roast"><th class="py-1 font-medium">Hari</th><th class="py-1 text-right font-medium">Rata-rata transaksi / hari</th></tr></thead>
                    <tbody class="divide-y divide-latte/60 tabular-nums">
                        @foreach ($heatmap['days'] as $d => $dayName)
                            <tr><td class="py-1">{{ $dayName }}</td><td class="py-1 text-right">{{ number_format($heatmap['dayAvg'][$d] ?? 0, 1, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Categories --}}
            @if ($categories)
                <section class="card p-4" aria-labelledby="kategori">
                    <h2 id="kategori" class="font-semibold text-espresso-900">Pendapatan per kategori</h2>
                    <p class="text-xs text-roast">Produk di beberapa kategori dihitung di tiap kategorinya</p>
                    <ul class="mt-3 space-y-3">
                        @foreach ($categories as $c)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate text-espresso-900">{{ $c['name'] }}</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-espresso-900">{{ rupiah_short($c['revenue']) }}</span>
                                </div>
                                <span class="mt-1 block h-2.5 rounded-r-[4px] bg-caramel-600" style="width: {{ max(1, $c['pct']) }}%"></span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Bought together --}}
            <section class="card p-4" aria-labelledby="bersama">
                <h2 id="bersama" class="font-semibold text-espresso-900">Sering dibeli bersama</h2>
                <p class="text-xs text-roast">Ide paket bundling</p>
                <ul class="mt-2 divide-y divide-latte/60">
                    @forelse ($pairs as $pair)
                        <li class="flex items-center gap-3 py-2.5 text-sm">
                            <span class="min-w-0 flex-1 text-espresso-900">{{ $pair['a'] }} <span class="text-roast">+</span> {{ $pair['b'] }}</span>
                            <span class="shrink-0 text-xs tabular-nums text-roast">{{ $pair['count'] }}×</span>
                        </li>
                    @empty
                        <li class="py-2.5 text-sm text-roast">Belum ada pasangan produk yang sering dibeli bersama.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Customers --}}
            <section class="card p-4" aria-labelledby="pelanggan">
                <h2 id="pelanggan" class="font-semibold text-espresso-900">Pelanggan</h2>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-cream p-2.5"><dt class="text-[11px] text-roast">Ada nama</dt><dd class="font-semibold text-espresso-900">{{ $customers['namedShare'] }}%</dd></div>
                    <div class="rounded-xl bg-cream p-2.5"><dt class="text-[11px] text-roast">Baru</dt><dd class="font-semibold text-espresso-900">{{ number_format($customers['newCount'], 0, ',', '.') }}</dd></div>
                    <div class="rounded-xl bg-cream p-2.5"><dt class="text-[11px] text-roast">Lama</dt><dd class="font-semibold text-espresso-900">{{ number_format($customers['returningCount'], 0, ',', '.') }}</dd></div>
                </dl>
                @if ($customers['top'])
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wider text-roast">Pelanggan setia</h3>
                    <ul class="mt-1 divide-y divide-latte/60">
                        @foreach ($customers['top'] as $c)
                            <li class="flex items-center gap-3 py-2 text-sm">
                                <span class="min-w-0 flex-1 truncate text-espresso-900">{{ $c['name'] }}</span>
                                <span class="shrink-0 text-right">
                                    <span class="block font-semibold tabular-nums text-espresso-900">{{ rupiah_short($c['spend']) }}</span>
                                    <span class="block text-[11px] text-roast">{{ $c['visits'] }}× belanja</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-[11px] text-roast">Baru = pembelian pertama di periode ini. Lama = pernah belanja sebelumnya. Pelanggan setia = 2× atau lebih di periode ini.</p>
            </section>

            {{-- Unsold products --}}
            <section class="card p-4" aria-labelledby="tidaklaku">
                <h2 id="tidaklaku" class="font-semibold text-espresso-900">Tidak terjual</h2>
                <p class="text-xs text-roast">{{ $unsold['count'] }} dari {{ $unsold['activeTotal'] }} produk aktif tidak terjual di periode ini</p>
                @if ($unsold['count'])
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($unsold['items'] as $p)
                            <li><a href="{{ route('admin.products.edit', $p) }}" class="chip bg-cream py-1 text-espresso-800 ring-1 ring-latte">{{ $p->name }}</a></li>
                        @endforeach
                        @if ($unsold['count'] > count($unsold['items']))
                            <li class="chip py-1 text-roast">+{{ $unsold['count'] - count($unsold['items']) }} lainnya</li>
                        @endif
                    </ul>
                @else
                    <p class="mt-2 text-sm text-roast">Semua produk aktif terjual. Bagus!</p>
                @endif
            </section>
        </div>

        {{-- Stores --}}
        @if ($stores)
            <section class="card p-4" aria-labelledby="pertoko">
                <h2 id="pertoko" class="font-semibold text-espresso-900">Per toko</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($stores as $s)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate text-espresso-900">{{ $s['name'] }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-espresso-900">{{ rupiah_short($s['net']) }}</span>
                            </div>
                            <span class="mt-1 block h-2.5 rounded-r-[4px] bg-caramel-600" style="width: {{ $s['net'] ? max(1, $s['pct']) : 0 }}%"></span>
                            <p class="mt-0.5 text-[11px] text-roast">{{ number_format($s['count'], 0, ',', '.') }} transaksi · rata-rata {{ rupiah($s['aov']) }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="text-xs text-roast">Hanya transaksi lunas. Penjualan bersih = total − diskon; pendapatan produk = jumlah × harga (sebelum diskon).</p>
    @endunless
    </div>
</div>
