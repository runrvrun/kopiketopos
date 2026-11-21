<?php

use App\Models\Store;
use App\Models\Transaction;
use App\Support\StoreContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Home')] class extends Component
{
    public function with(): array
    {
        $context = StoreContext::for();
        $storeIds = $context->storeIds();

        $today = now()->startOfDay();
        $month = now()->startOfMonth();
        $quarter = now()->subMonthsNoOverflow(2)->startOfMonth();

        // Net sales = total − discount of paid transactions (same as v1).
        $net = 'total_price - COALESCE(discount, 0)';
        $select = "
            store_id,
            SUM(CASE WHEN created_at >= ? THEN {$net} ELSE 0 END) AS today_sales,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS today_count,
            SUM(CASE WHEN created_at >= ? THEN {$net} ELSE 0 END) AS month_sales,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS month_count,
            SUM({$net}) AS quarter_sales,
            COUNT(*) AS quarter_count";

        $perStore = Transaction::query()
            ->selectRaw($select, [$today, $today, $month, $month])
            ->paid()
            ->forStores($storeIds)
            ->where('created_at', '>=', $quarter)
            ->groupBy('store_id')
            ->get()
            ->keyBy('store_id');

        $sum = fn (string $field) => (int) $perStore->sum($field);

        return [
            'periods' => [
                ['label' => 'Hari ini', 'hint' => now()->translatedFormat('l, j F'), 'sales' => $sum('today_sales'), 'count' => $sum('today_count'), 'icon' => 'clock'],
                ['label' => 'Bulan ini', 'hint' => now()->translatedFormat('F Y'), 'sales' => $sum('month_sales'), 'count' => $sum('month_count'), 'icon' => 'calendar'],
                ['label' => '3 bulan terakhir', 'hint' => $quarter->translatedFormat('M').' – '.now()->translatedFormat('M Y'), 'sales' => $sum('quarter_sales'), 'count' => $sum('quarter_count'), 'icon' => 'trend'],
            ],
            'breakdown' => count($storeIds) > 1
                ? Store::withTrashed()->whereIn('id', $storeIds)->orderBy('name')->get()
                    ->reject(fn ($s) => ($s->trashed() || ! $s->is_active) && ! isset($perStore[$s->id]))
                    ->map(fn ($s) => [
                    'name' => $s->name.($s->trashed() ? ' (dihapus)' : (! $s->is_active ? ' (nonaktif)' : '')),
                    'today' => (int) ($perStore[$s->id]->today_sales ?? 0),
                    'month' => (int) ($perStore[$s->id]->month_sales ?? 0),
                    'quarter' => (int) ($perStore[$s->id]->quarter_sales ?? 0),
                ])
                : collect(),
            'ongoing' => Transaction::ongoing()->forStores($storeIds)->count(),
            'storeLabel' => $context->label(),
            'hasStore' => $storeIds !== [],
        ];
    }
};
?>

<div>
    <div class="flex items-end justify-between gap-3">
        <div>
            <p class="text-sm text-roast">{{ $storeLabel }}</p>
            <h1 class="page-title">Penjualan</h1>
        </div>
        @if ($ongoing > 0)
            <a href="{{ route('transactions.index') }}" class="chip bg-caramel-100 py-1.5 text-caramel-700 ring-1 ring-caramel-300/60">
                <span class="size-1.5 rounded-full bg-caramel-600"></span>
                {{ $ongoing }} belum lunas
            </a>
        @endif
    </div>

    @unless ($hasStore)
        <div class="card mt-5 p-6 text-center">
            <p class="font-medium text-espresso-900">Akun ini belum punya akses ke toko mana pun.</p>
            <p class="mt-1 text-sm text-roast">Minta admin untuk menambahkan akses toko.</p>
        </div>
    @else
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach ($periods as $period)
                <div @class([
                    'relative overflow-hidden rounded-2xl p-4 sm:p-5',
                    'bg-espresso text-foam shadow-lg shadow-espresso/20' => $loop->first,
                    'bg-foam ring-1 ring-latte/80' => ! $loop->first,
                ])>
                    <div class="flex items-center justify-between">
                        <p @class(['text-sm font-medium', 'text-caramel-300' => $loop->first, 'text-roast' => ! $loop->first])>{{ $period['label'] }}</p>
                        <x-icon :name="$period['icon']" @class(['size-5', 'text-caramel' => $loop->first, 'text-caramel-600' => ! $loop->first]) />
                    </div>
                    <p @class(['mt-2 font-display text-[1.75rem] font-semibold leading-none tracking-tight tabular-nums', 'text-espresso-900' => ! $loop->first])>
                        {{ rupiah($period['sales']) }}
                    </p>
                    <p @class(['mt-2 text-xs', 'text-cream/70' => $loop->first, 'text-roast' => ! $loop->first])>
                        {{ number_format($period['count'], 0, ',', '.') }} transaksi · {{ $period['hint'] }}
                    </p>
                </div>
            @endforeach
        </div>

        @if ($breakdown->isNotEmpty())
            <section class="mt-6">
                <h2 class="text-sm font-semibold text-espresso-900">Per toko</h2>
                <div class="card mt-2 divide-y divide-latte/70">
                    @foreach ($breakdown as $row)
                        <div class="p-4">
                            <p class="flex items-center gap-2 font-medium text-espresso-900">
                                <x-icon name="store" class="size-4 text-caramel-600" /> {{ $row['name'] }}
                            </p>
                            <dl class="mt-2 grid grid-cols-3 gap-2 text-sm">
                                <div><dt class="text-xs text-roast">Hari ini</dt><dd class="font-semibold tabular-nums">{{ rupiah($row['today']) }}</dd></div>
                                <div><dt class="text-xs text-roast">Bulan ini</dt><dd class="font-semibold tabular-nums">{{ rupiah($row['month']) }}</dd></div>
                                <div><dt class="text-xs text-roast">3 bulan</dt><dd class="font-semibold tabular-nums">{{ rupiah($row['quarter']) }}</dd></div>
                            </dl>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <p class="mt-4 text-xs text-roast">Penjualan bersih (total − diskon) dari transaksi yang sudah dibayar.</p>
    @endunless
</div>
