<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use App\Support\SalesAnalytics;
use Carbon\CarbonImmutable;

class SalesAnalyticsTest extends PosTestCase
{
    private function sale(string $at, array $items, int $discount = 0, ?string $customer = null, ?int $storeId = null, string $status = 'Paid'): Transaction
    {
        $this->travelTo(CarbonImmutable::parse($at));
        $total = collect($items)->sum(fn ($i) => $i[0]->price * $i[1]);
        $record = $customer ? (Customer::matching($customer)->first() ?? Customer::create(['name' => $customer])) : null;
        $trx = Transaction::create([
            'store_id' => $storeId ?? $this->pamulang->id, 'customer' => $customer, 'customer_id' => $record?->id, 'status' => $status,
            'total_item' => collect($items)->sum(fn ($i) => $i[1]), 'total_price' => $total, 'discount' => $discount ?: null,
        ]);
        foreach ($items as [$product, $qty]) {
            $trx->lines()->create(['product_id' => $product->id, 'amount' => $qty, 'price' => $product->price]);
        }
        $this->travelBack();

        return $trx;
    }

    public function test_kpis_products_pairs_and_customers(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 20:00'));
        $kopi = $this->product('Kopi Susu', 25000);
        $roti = $this->product('Roti Keto', 20000);
        $teh = $this->product('Teh Tarik', 15000); // never sold

        $this->sale('2026-11-20 19:10', [[$kopi, 2], [$roti, 1]], 5000, 'Budi');
        $this->sale('2026-11-21 19:30', [[$kopi, 1], [$roti, 1]], 0, ' budi ');
        $this->sale('2026-11-21 08:00', [[$kopi, 1]], 0, 'Sari');
        $this->sale('2026-10-01 08:00', [[$kopi, 1]], 0, 'Sari'); // Sari bought before: returning
        $this->sale('2026-11-21 09:00', [[$roti, 5]], 0, null, null, status: ''); // not paid: ignored
        $this->sale('2026-11-10 10:00', [[$kopi, 1]]); // previous 7-day period

        $this->travelTo(CarbonImmutable::parse('2026-11-21 20:00'));
        $data = (new SalesAnalytics([$this->pamulang->id], '7h'))->toArray();

        $kpis = collect($data['kpis'])->keyBy('key');
        $this->assertSame(65000 + 45000 + 25000, $kpis['net']['raw']);   // 70k−5k, 45k, 25k
        $this->assertSame(3, $kpis['count']['raw']);
        $this->assertSame(45000, $kpis['aov']['raw']);
        $this->assertSame(440, $kpis['net']['delta']);                     // vs 25.000 before

        $this->assertSame('Kopi Susu', $data['products']['top'][0]['name']);
        $this->assertSame(4, $data['products']['top'][0]['qty']);
        $this->assertSame([['a' => 'Kopi Susu', 'b' => 'Roti Keto', 'count' => 2]], $data['pairs']);

        $this->assertSame(1, $data['customers']['repeatCount']);          // "Budi" and " budi " are one customer
        $this->assertSame(1, $data['customers']['newCount']);             // Budi
        $this->assertSame(1, $data['customers']['returningCount']);       // Sari
        $this->assertSame(2, $data['customers']['top'][0]['visits']);
        $this->assertSame(['Teh Tarik'], collect($data['unsold']['items'])->pluck('name')->all());

        $this->assertSame(19, $data['heatmap']['peakHour']);
        $this->assertCount(7, $data['trend']['bars']);
        $this->assertNotEmpty($data['insights']);
    }

    public function test_page_renders_for_admin_and_respects_store_scope(): void
    {
        $kopi = $this->product('Kopi Susu', 25000);
        $this->sale(now()->subDay()->setTime(10, 0)->toDateTimeString(), [[$kopi, 1]], storeId: $this->senopati->id);
        $admin = $this->admin();

        foreach (array_keys(SalesAnalytics::PERIODS) as $period) {
            $this->actingAs($admin)->get(route('admin.analytics', ['periode' => $period]))->assertOk()->assertSee('Analisa Penjualan');
        }
        $this->get(route('admin.analytics'))->assertSee('Kopi Susu')->assertSee('Per toko');

        $this->post(route('store.select'), ['store' => $this->pamulang->id]);
        $this->get(route('admin.analytics'))->assertSee('Belum ada transaksi lunas');
    }

    public function test_staff_cannot_open_analytics(): void
    {
        $this->actingAs($this->staff($this->pamulang))->get(route('admin.analytics'))->assertForbidden();
    }
}
