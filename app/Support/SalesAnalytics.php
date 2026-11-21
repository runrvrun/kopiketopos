<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\TransactionLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Sales insight for the Admin › Analisa Penjualan page.
 *
 * Only paid transactions count. "Net" = total − discount (same as the
 * dashboard); product revenue = quantity × unit price (before the order-level
 * discount). Aggregation happens in PHP over the selected period, which keeps
 * it database-agnostic and is fast at this shop's volume.
 */
class SalesAnalytics
{
    public const PERIODS = [
        '7h' => '7 hari',
        '30h' => '30 hari',
        '90h' => '90 hari',
        '12b' => '12 bulan',
    ];

    private const WEEKDAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    private CarbonImmutable $start;

    private CarbonImmutable $end;

    private CarbonImmutable $prevStart;

    private CarbonImmutable $prevEnd;

    /** @var Collection<int, Transaction> */
    private Collection $current;

    /** @var Collection<int, Transaction> */
    private Collection $previous;

    /** @var Collection<int, TransactionLine> */
    private Collection $lines;

    /** @var Collection<int, TransactionLine> */
    private Collection $prevLines;

    /** @param  array<int>  $storeIds */
    public function __construct(private array $storeIds, private string $period = '30h')
    {
        if (! isset(self::PERIODS[$period])) {
            $this->period = '30h';
        }

        $now = CarbonImmutable::now();
        $this->end = $now;

        if ($this->period === '12b') {
            $this->start = $now->startOfMonth()->subMonths(11);
            $this->prevStart = $this->start->subMonths(12);
        } else {
            $days = (int) $this->period;
            $this->start = $now->startOfDay()->subDays($days - 1);
            $this->prevStart = $this->start->subDays($days);
        }
        $this->prevEnd = $this->start->subSecond();

        $this->current = $this->transactions($this->start, $this->end);
        $this->previous = $this->transactions($this->prevStart, $this->prevEnd);
        $this->lines = $this->linesFor($this->current);
        $this->prevLines = $this->linesFor($this->previous);
    }

    public function toArray(): array
    {
        $kpis = $this->kpis();
        $products = $this->products();
        $heatmap = $this->heatmap();
        $pairs = $this->pairs();
        $unsold = $this->unsoldProducts();
        $customers = $this->customers();
        $stores = $this->stores();

        return [
            'period' => $this->period,
            'rangeLabel' => $this->rangeLabel($this->start, $this->end),
            'prevLabel' => $this->rangeLabel($this->prevStart, $this->prevEnd),
            'kpis' => $kpis,
            'trend' => $this->trend(),
            'products' => $products,
            'categories' => $this->categories(),
            'heatmap' => $heatmap,
            'pairs' => $pairs,
            'unsold' => $unsold,
            'customers' => $customers,
            'stores' => $stores,
            'insights' => $this->insights($kpis, $products, $heatmap, $pairs, $unsold, $customers, $stores),
            'hasData' => $this->current->isNotEmpty(),
        ];
    }

    // ---------------------------------------------------------------- data

    private function transactions(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Transaction::query()
            ->paid()
            ->forStores($this->storeIds)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'store_id', 'customer_id', 'total_item', 'total_price', 'discount', 'created_at']);
    }

    private function linesFor(Collection $transactions): Collection
    {
        if ($transactions->isEmpty()) {
            return collect();
        }

        return $transactions->pluck('id')->chunk(1000)->flatMap(
            fn ($ids) => TransactionLine::whereIn('transaction_id', $ids)->get(['transaction_id', 'product_id', 'amount', 'price'])
        );
    }

    private static function net(Transaction $t): int
    {
        return max(0, $t->total_price - (int) $t->discount);
    }

    // ---------------------------------------------------------------- sections

    private function kpis(): array
    {
        $summary = function (Collection $trx) {
            $count = $trx->count();
            $net = $trx->sum(fn ($t) => self::net($t));
            $gross = $trx->sum('total_price');

            return [
                'net' => $net,
                'count' => $count,
                'aov' => $count ? intdiv($net, $count) : 0,
                'items' => $count ? round($trx->sum('total_item') / $count, 1) : 0,
                'discountShare' => $gross ? round($trx->sum(fn ($t) => (int) $t->discount) / $gross * 100, 1) : 0,
            ];
        };

        $now = $summary($this->current);
        $before = $summary($this->previous);
        $delta = fn ($key) => $before[$key] ? (int) round(($now[$key] - $before[$key]) / $before[$key] * 100) : null;

        return [
            ['key' => 'net', 'label' => 'Penjualan bersih', 'value' => rupiah($now['net']), 'raw' => $now['net'], 'delta' => $delta('net'), 'upIsGood' => true],
            ['key' => 'count', 'label' => 'Transaksi', 'value' => number_format($now['count'], 0, ',', '.'), 'raw' => $now['count'], 'delta' => $delta('count'), 'upIsGood' => true],
            ['key' => 'aov', 'label' => 'Rata-rata / transaksi', 'value' => rupiah($now['aov']), 'raw' => $now['aov'], 'delta' => $delta('aov'), 'upIsGood' => true],
            ['key' => 'items', 'label' => 'Item / transaksi', 'value' => number_format($now['items'], 1, ',', '.'), 'raw' => $now['items'], 'delta' => $delta('items'), 'upIsGood' => true],
            ['key' => 'discount', 'label' => 'Diskon dari penjualan', 'value' => number_format($now['discountShare'], 1, ',', '.').'%', 'raw' => $now['discountShare'], 'delta' => null, 'upIsGood' => false],
        ];
    }

    /** Net sales per day (7/30 days), week (90 days) or month (12 months). */
    private function trend(): array
    {
        $buckets = [];
        $cursor = $this->start;

        while ($cursor->lte($this->end)) {
            [$next, $label, $short] = match ($this->period) {
                '12b' => [$cursor->addMonth(), $cursor->translatedFormat('F Y'), $cursor->translatedFormat('M')],
                '90h' => [$cursor->addWeek(), 'Minggu '.$cursor->translatedFormat('j M'), $cursor->translatedFormat('j/n')],
                default => [$cursor->addDay(), $cursor->translatedFormat('l, j M'), $this->period === '7h' ? $cursor->translatedFormat('D') : $cursor->format('j')],
            };
            $buckets[] = ['from' => $cursor, 'to' => $next, 'label' => $label, 'short' => $short, 'net' => 0, 'count' => 0];
            $cursor = $next;
        }

        foreach ($this->current as $t) {
            foreach ($buckets as $i => $b) {
                if ($t->created_at->gte($b['from']) && $t->created_at->lt($b['to'])) {
                    $buckets[$i]['net'] += self::net($t);
                    $buckets[$i]['count']++;
                    break;
                }
            }
        }

        $max = max(1, ...array_column($buckets, 'net'));
        $labelEvery = count($buckets) > 14 ? (int) ceil(count($buckets) / 7) : 1;

        return [
            'unit' => match ($this->period) { '12b' => 'bulan', '90h' => 'minggu', default => 'hari' },
            'max' => $max,
            'bars' => array_map(fn ($b, $i) => [
                'label' => $b['label'],
                'short' => $b['short'],
                'showTick' => $i % $labelEvery === 0 || $i === count($buckets) - 1,
                'net' => $b['net'],
                'count' => $b['count'],
                'pct' => round($b['net'] / $max * 100, 1),
            ], $buckets, array_keys($buckets)),
            'best' => collect($buckets)->sortByDesc('net')->first(),
            'avg' => count($buckets) ? intdiv((int) array_sum(array_column($buckets, 'net')), count($buckets)) : 0,
        ];
    }

    private function products(): array
    {
        $agg = fn (Collection $lines) => $lines->groupBy('product_id')->map(fn ($g) => [
            'qty' => (int) $g->sum('amount'),
            'revenue' => (int) $g->sum(fn ($l) => $l->amount * $l->price),
            'orders' => $g->pluck('transaction_id')->unique()->count(),
        ]);

        $now = $agg($this->lines);
        $before = $agg($this->prevLines);
        // Deleted products still appear here when they have sales in the period.
        $names = Product::withTrashed()->whereIn('id', $now->keys()->merge($before->keys())->unique())->pluck('name', 'id');
        $totalRevenue = max(1, $now->sum('revenue'));

        $top = $now->sortByDesc('revenue')->take(10)->map(fn ($p, $id) => $p + [
            'id' => $id,
            'name' => $names[$id] ?? 'Produk #'.$id,
            'share' => round($p['revenue'] / $totalRevenue * 100, 1),
        ])->values();

        $topMax = max(1, $top->max('revenue') ?? 1);
        $top = $top->map(fn ($p) => $p + ['pct' => round($p['revenue'] / $topMax * 100, 1)]);

        // Movers: biggest revenue change vs the previous period (ignore tiny sellers).
        $movers = $now->keys()->merge($before->keys())->unique()->map(function ($id) use ($now, $before, $names) {
            $a = $now[$id]['revenue'] ?? 0;
            $b = $before[$id]['revenue'] ?? 0;

            return ['id' => $id, 'name' => $names[$id] ?? 'Produk #'.$id, 'now' => $a, 'before' => $b, 'change' => $a - $b,
                'pctChange' => $b ? (int) round(($a - $b) / $b * 100) : null];
        })->filter(fn ($m) => max($m['now'], $m['before']) >= 100000);

        return [
            'top' => $top,
            'top3Share' => round($now->sortByDesc('revenue')->take(3)->sum('revenue') / $totalRevenue * 100),
            'rising' => $movers->where('change', '>', 0)->sortByDesc('change')->take(3)->values(),
            'falling' => $movers->where('change', '<', 0)->sortBy('change')->take(3)->values(),
            'distinctSold' => $now->count(),
        ];
    }

    /** Revenue per category; a product in several categories counts in each. */
    private function categories(): array
    {
        $revenueByProduct = $this->lines->groupBy('product_id')->map(fn ($g) => (int) $g->sum(fn ($l) => $l->amount * $l->price));

        $rows = ProductCategory::ordered()->with(['products' => fn ($q) => $q->withTrashed()->select('products.id')])->get()->map(fn ($c) => [
            'name' => $c->name,
            'revenue' => (int) $c->products->sum(fn ($p) => $revenueByProduct[$p->id] ?? 0),
        ]);

        $categorised = ProductCategory::query()->join('category_product', 'category_product.category_id', '=', 'product_categories.id')
            ->distinct()->pluck('category_product.product_id');
        $rows->push([
            'name' => 'Tanpa kategori',
            'revenue' => (int) $revenueByProduct->reject(fn ($v, $id) => $categorised->contains($id))->sum(),
        ]);

        $rows = $rows->where('revenue', '>', 0)->sortByDesc('revenue')->values();
        $max = max(1, $rows->max('revenue') ?? 1);

        return $rows->map(fn ($r) => $r + ['pct' => round($r['revenue'] / $max * 100, 1)])->all();
    }

    /** Transactions per weekday × hour: when the shop is busy. */
    private function heatmap(): array
    {
        $grid = [];
        $hours = $this->current->map(fn ($t) => (int) $t->created_at->format('G'));
        $from = (int) ($hours->min() ?? 8);
        $to = (int) ($hours->max() ?? 22);

        foreach ($this->current as $t) {
            $d = (int) $t->created_at->format('N');
            $h = (int) $t->created_at->format('G');
            $grid[$d][$h] = ($grid[$d][$h] ?? 0) + 1;
        }

        $max = 0;
        foreach ($grid as $row) {
            $max = max($max, ...array_values($row));
        }

        $byHour = [];
        $byDay = [];
        foreach (range($from, $to) as $h) {
            $byHour[$h] = array_sum(array_map(fn ($row) => $row[$h] ?? 0, $grid));
        }
        foreach (self::WEEKDAYS as $d => $name) {
            $byDay[$d] = array_sum($grid[$d] ?? []);
        }

        // Per-weekday average (a period may contain e.g. 5 Mondays and 4 Sundays).
        $dayCounts = [];
        for ($c = $this->start->startOfDay(); $c->lte($this->end); $c = $c->addDay()) {
            $dayCounts[(int) $c->format('N')] = ($dayCounts[(int) $c->format('N')] ?? 0) + 1;
        }
        $dayAvg = collect($byDay)->map(fn ($n, $d) => $dayCounts[$d] ?? 0 ? $n / $dayCounts[$d] : 0);

        $active = collect($byHour)->filter(fn ($n) => $n > 0);

        return [
            'hours' => range($from, $to),
            'days' => self::WEEKDAYS,
            'grid' => $grid,
            'max' => $max,
            'peakHour' => $active->isNotEmpty() ? $active->sortDesc()->keys()->first() : null,
            // Quietest regular hour: ignore stray hours with almost no sales (< 5% of the peak).
            'quietHour' => $active->count() > 3 ? $active->filter(fn ($n) => $n >= $active->max() * 0.05)->sort()->keys()->first() : null,
            'busiestDay' => $dayAvg->filter()->isNotEmpty() ? $dayAvg->sortDesc()->keys()->first() : null,
            'quietestDay' => $dayAvg->filter()->count() > 1 ? $dayAvg->filter()->sort()->keys()->first() : null,
            'dayAvg' => $dayAvg->all(),
        ];
    }

    /** Products most often bought together in the same transaction. */
    private function pairs(): array
    {
        $counts = [];
        foreach ($this->lines->groupBy('transaction_id') as $group) {
            $ids = $group->pluck('product_id')->unique()->sort()->values()->all();
            $n = count($ids);
            if ($n < 2 || $n > 12) {
                continue;
            }
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $key = $ids[$i].'-'.$ids[$j];
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
        }

        arsort($counts);
        $top = array_slice(array_filter($counts, fn ($c) => $c >= 2), 0, 5, true);
        $ids = collect(array_keys($top))->flatMap(fn ($k) => explode('-', $k))->unique();
        $names = Product::withTrashed()->whereIn('id', $ids)->pluck('name', 'id');

        return collect($top)->map(function ($count, $key) use ($names) {
            [$a, $b] = explode('-', $key);

            return ['a' => $names[$a] ?? '#'.$a, 'b' => $names[$b] ?? '#'.$b, 'count' => $count];
        })->values()->all();
    }

    /** Active products that sold nothing in the period. */
    private function unsoldProducts(): array
    {
        $sold = $this->lines->pluck('product_id')->unique();
        $unsold = Product::active()->whereNotIn('id', $sold)->orderBy('name')->get(['id', 'name']);

        return ['count' => $unsold->count(), 'items' => $unsold->take(8)->all(), 'activeTotal' => Product::active()->count()];
    }

    /** Customers in the period: new vs returning, and the most loyal ones. */
    private function customers(): array
    {
        $linked = $this->current->filter(fn ($t) => $t->customer_id);
        $groups = $linked->groupBy('customer_id');
        $repeat = $groups->filter(fn ($g) => $g->count() >= 2);

        // A customer is "new" when their first-ever paid purchase falls in this period.
        $firstPurchase = $groups->isEmpty() ? collect() : $groups->keys()->chunk(1000)->flatMap(
            fn ($ids) => Transaction::paid()->whereIn('customer_id', $ids)
                ->groupBy('customer_id')->selectRaw('customer_id, MIN(created_at) AS first_at')
                ->pluck('first_at', 'customer_id')
        );
        $newCount = $firstPurchase->filter(fn ($at) => CarbonImmutable::parse($at)->gte($this->start))->count();

        $topIds = $repeat->map(fn ($g) => $g->sum(fn ($t) => self::net($t)))->sortDesc()->take(5);
        $names = Customer::withTrashed()->whereIn('id', $topIds->keys())->pluck('name', 'id');

        return [
            'namedShare' => $this->current->count() ? (int) round($linked->count() / $this->current->count() * 100) : 0,
            'unique' => $groups->count(),
            'newCount' => $newCount,
            'returningCount' => $groups->count() - $newCount,
            'repeatCount' => $repeat->count(),
            'top' => $topIds->map(fn ($spend, $id) => [
                'name' => $names[$id] ?? 'Pelanggan #'.$id,
                'visits' => $groups[$id]->count(),
                'spend' => $spend,
            ])->values()->all(),
        ];
    }

    private function stores(): array
    {
        if (count($this->storeIds) < 2) {
            return [];
        }

        $byStore = $this->current->groupBy('store_id');
        $rows = Store::withTrashed()->whereIn('id', $this->storeIds)->orderBy('name')->get()
            ->reject(fn ($s) => $s->trashed() && ! $byStore->has($s->id))
            ->map(function ($s) use ($byStore) {
            $trx = $byStore[$s->id] ?? collect();
            $net = $trx->sum(fn ($t) => self::net($t));

            return ['name' => $s->name.($s->trashed() ? ' (dihapus)' : ''), 'net' => $net, 'count' => $trx->count(), 'aov' => $trx->count() ? intdiv($net, $trx->count()) : 0];
        })->sortByDesc('net')->values();

        $max = max(1, $rows->max('net') ?? 1);

        return $rows->map(fn ($r) => $r + ['pct' => round($r['net'] / $max * 100, 1)])->all();
    }

    // ---------------------------------------------------------------- insights

    /**
     * Plain-language suggestions derived from the numbers above.
     *
     * @return array<int, array{icon: string, tone: string, text: string}>
     */
    private function insights(array $kpis, array $products, array $heatmap, array $pairs, array $unsold, array $customers, array $stores): array
    {
        if ($this->current->isEmpty()) {
            return [];
        }

        $tips = [];
        $net = $kpis[0];

        if ($net['delta'] !== null && abs($net['delta']) >= 5) {
            $tips[] = $net['delta'] > 0
                ? ['icon' => 'trend', 'tone' => 'good', 'text' => "Penjualan naik {$net['delta']}% dibanding periode sebelumnya. Pertahankan produk dan promo yang sedang berjalan."]
                : ['icon' => 'trend', 'tone' => 'warn', 'text' => 'Penjualan turun '.abs($net['delta']).'% dibanding periode sebelumnya. Cek produk yang penjualannya turun di bawah dan pertimbangkan promo.'];
        }

        if ($heatmap['peakHour'] !== null) {
            $text = sprintf('Jam paling ramai %02d:00–%02d:00. Pastikan staf dan bahan siap sebelum jam ini.', $heatmap['peakHour'], $heatmap['peakHour'] + 1);
            if ($heatmap['quietHour'] !== null && $heatmap['quietHour'] !== $heatmap['peakHour']) {
                $text .= sprintf(' Jam sepi %02d:00–%02d:00 cocok untuk promo "happy hour".', $heatmap['quietHour'], $heatmap['quietHour'] + 1);
            }
            $tips[] = ['icon' => 'clock', 'tone' => 'info', 'text' => $text];
        }

        if ($heatmap['busiestDay'] && $heatmap['quietestDay'] && $heatmap['dayAvg'][$heatmap['quietestDay']] > 0) {
            $ratio = $heatmap['dayAvg'][$heatmap['busiestDay']] / $heatmap['dayAvg'][$heatmap['quietestDay']];
            if ($ratio >= 1.3) {
                $tips[] = ['icon' => 'calendar', 'tone' => 'info', 'text' => sprintf(
                    '%s rata-rata %s× lebih ramai dari %s. Coba promo khusus hari %s untuk meratakan penjualan.',
                    self::WEEKDAYS[$heatmap['busiestDay']], number_format($ratio, 1, ',', '.'), self::WEEKDAYS[$heatmap['quietestDay']], self::WEEKDAYS[$heatmap['quietestDay']],
                )];
            }
        }

        if ($pairs !== []) {
            $p = $pairs[0];
            $tips[] = ['icon' => 'bag', 'tone' => 'info', 'text' => "{$p['a']} sering dibeli bersama {$p['b']} ({$p['count']}×). Buat paket bundling dengan harga sedikit lebih hemat."];
        }

        if ($products['top3Share'] >= 50 && $products['top']->count() >= 3) {
            $tips[] = ['icon' => 'coffee', 'tone' => 'info', 'text' => "3 produk teratas menyumbang {$products['top3Share']}% pendapatan produk. Jaga stok dan kualitasnya, dan jadikan andalan di promosi."];
        }

        $items = $kpis[3]['raw'];
        if ($items > 0 && $items < 1.6) {
            $tips[] = ['icon' => 'plus', 'tone' => 'info', 'text' => 'Rata-rata hanya '.number_format($items, 1, ',', '.').' item per transaksi. Tawarkan tambahan (snack atau ukuran lebih besar) di kasir untuk menaikkan nilai belanja.'];
        }

        if (($products['falling'][0] ?? null) && $products['falling'][0]['pctChange'] !== null && $products['falling'][0]['pctChange'] <= -30) {
            $f = $products['falling'][0];
            $tips[] = ['icon' => 'trend', 'tone' => 'warn', 'text' => "Penjualan {$f['name']} turun ".abs($f['pctChange']).'%. Cek apakah stok, rasa, atau harganya berubah.'];
        }

        if ($unsold['count'] > 0) {
            $tips[] = ['icon' => 'eye-off', 'tone' => 'warn', 'text' => "{$unsold['count']} dari {$unsold['activeTotal']} produk aktif tidak terjual sama sekali di periode ini. Promosikan, atau nonaktifkan agar menu lebih ringkas."];
        }

        if ($kpis[4]['raw'] >= 5) {
            $tips[] = ['icon' => 'receipt', 'tone' => 'warn', 'text' => 'Diskon mencapai '.number_format($kpis[4]['raw'], 1, ',', '.').'% dari penjualan kotor. Pastikan diskon benar-benar menarik pelanggan baru atau pembelian ulang.'];
        }

        if ($customers['unique'] >= 5) {
            $returningShare = (int) round($customers['returningCount'] / $customers['unique'] * 100);
            $tips[] = $returningShare >= 50
                ? ['icon' => 'users', 'tone' => 'good', 'text' => "{$returningShare}% pelanggan di periode ini adalah pelanggan lama. Program loyalitas sederhana (mis. beli 10 gratis 1) bisa membuat mereka makin sering datang."]
                : ['icon' => 'users', 'tone' => 'info', 'text' => "{$customers['newCount']} pelanggan baru di periode ini, tapi baru {$returningShare}% pelanggan yang kembali. Minta nomor/alamat dan tawarkan promo untuk pembelian berikutnya."];
        } elseif ($customers['namedShare'] < 40) {
            $tips[] = ['icon' => 'users', 'tone' => 'info', 'text' => "Hanya {$customers['namedShare']}% transaksi yang mencatat nama pelanggan. Isi nama pelanggan agar pelanggan setia bisa dikenali."];
        }

        foreach ($stores as $s) {
            if ($s['count'] === 0) {
                $tips[] = ['icon' => 'store', 'tone' => 'warn', 'text' => "Toko {$s['name']} tidak punya penjualan di periode ini."];
            }
        }

        return $tips;
    }

    private function rangeLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->isSameYear($to)
            ? $from->translatedFormat('j M').' – '.$to->translatedFormat('j M Y')
            : $from->translatedFormat('j M Y').' – '.$to->translatedFormat('j M Y');
    }
}
