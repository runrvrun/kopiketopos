<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Transaction;
use App\Support\SalesAnalytics;
use Livewire\Livewire;

class SoftDeleteTest extends PosTestCase
{
    private function paidSale(Product $product, int $qty = 1, ?int $storeId = null): Transaction
    {
        $trx = Transaction::create([
            'store_id' => $storeId ?? $this->pamulang->id, 'status' => 'Paid',
            'total_item' => $qty, 'total_price' => $product->price * $qty, 'key' => (int) (now()->format('Ymd').'00001'),
        ]);
        $trx->lines()->create(['product_id' => $product->id, 'amount' => $qty, 'price' => $product->price]);

        return $trx;
    }

    public function test_deleted_product_leaves_the_menu_but_stays_on_receipts_and_reports(): void
    {
        $admin = $this->admin();
        $kopi = $this->product('Kopi Lama', 30000);
        $trx = $this->paidSale($kopi, 2);
        $kopi->delete();
        $this->actingAs($admin);

        $this->get(route('transactions.create'))->assertDontSee('Kopi Lama');
        $this->get(route('admin.products.index'))->assertDontSee('Kopi Lama');
        $this->get(route('transactions.show', $trx))->assertSee('Kopi Lama');

        $data = (new SalesAnalytics([$this->pamulang->id], '7h'))->toArray();
        $this->assertSame('Kopi Lama', $data['products']['top'][0]['name']);
        $this->assertSame(60000, collect($data['kpis'])->firstWhere('key', 'net')['raw']);
        $this->assertSame(0, $data['unsold']['count']);
    }

    public function test_deleted_product_cannot_be_added_but_can_stay_on_an_existing_order(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product('Kopi Lama', 30000);
        $roti = $this->product('Roti', 10000);
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], '', '', '', $this->pamulang->id);
        $trx = Transaction::sole();
        $kopi->delete();

        // Existing order: the deleted product stays and can be edited.
        $this->get(route('transactions.edit', $trx))->assertOk()->assertSee('Kopi Lama');
        Livewire::test('pages::transactions.form', ['transaction' => $trx])
            ->call('save', [$kopi->id => 3, $roti->id => 1], '', '', '', $this->pamulang->id)->assertHasNoErrors();
        $this->assertSame(100000, $trx->fresh()->total_price);

        // New order: it's ignored.
        Livewire::test('pages::transactions.form')
            ->call('save', [$kopi->id => 1], '', '', '', $this->pamulang->id)->assertHasErrors('items');
    }

    public function test_deleted_transactions_disappear_from_lists_and_reports_and_keep_their_number(): void
    {
        $admin = $this->admin();
        $kopi = $this->product('Kopi Susu', 25000);
        $this->actingAs($admin);
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], 'Hapus Saya', '', '', $this->pamulang->id);
        $trx = Transaction::sole();
        $trx->update(['status' => 'Paid']);

        Livewire::test('pages::transactions.form', ['transaction' => $trx])->call('delete');
        $this->assertSoftDeleted($trx);
        $this->assertDatabaseCount('product_transaction', 1);

        $this->get(route('transactions.index', ['tab' => 'lunas']))->assertDontSee('Hapus Saya');
        $this->get(route('transactions.show', $trx))->assertNotFound();
        $this->get('/')->assertSee('Rp0');
        $this->assertFalse((new SalesAnalytics([$this->pamulang->id], '7h'))->toArray()['hasData']);

        // The next order doesn't reuse the deleted transaction's invoice number.
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], '', '', '', $this->pamulang->id);
        $this->assertSame($trx->key + 1, Transaction::sole()->key);
    }

    public function test_deleted_store_leaves_the_selector_but_its_sales_stay_in_all_stores(): void
    {
        $admin = $this->admin();
        $staff = $this->staff($this->senopati);
        $this->paidSale($this->product('Kopi', 40000), 1, $this->senopati->id);
        $this->senopati->delete();

        $this->actingAs($admin)->get('/')->assertSee('Rp40.000')->assertDontSee('<option value="'.$this->senopati->id.'"', false);
        $this->assertSame([], $staff->fresh()->accessibleStores()->all());
        $this->actingAs($staff)->post(route('store.select'), ['store' => $this->senopati->id])->assertForbidden();
    }

    public function test_deleted_category_is_hidden_and_its_name_can_be_reused(): void
    {
        $this->actingAs($this->admin());
        $snacks = ProductCategory::create(['name' => 'Snacks']);
        $roti = $this->product('Roti Keto', 18000);
        $roti->categories()->attach($snacks);

        Livewire::test('pages::admin.categories.form', ['category' => $snacks])->call('delete');
        $this->assertSoftDeleted($snacks);
        $this->assertCount(0, $roti->fresh()->categories);
        $this->get(route('admin.categories.index'))->assertDontSee('Snacks');

        Livewire::test('pages::admin.categories.form')->set('name', 'Snacks')->call('save')->assertHasNoErrors();
        $this->assertSame(1, ProductCategory::where('name', 'Snacks')->count());
    }
}
