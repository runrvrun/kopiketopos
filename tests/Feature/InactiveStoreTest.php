<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Support\StoreContext;
use Livewire\Livewire;

class InactiveStoreTest extends PosTestCase
{
    public function test_admin_can_deactivate_and_reactivate_a_store(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.stores.form', ['store' => $this->senopati])->set('isActive', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($this->senopati->fresh()->is_active);
        $this->assertNotSoftDeleted($this->senopati);
        $this->get(route('admin.stores.index'))->assertSee('Nonaktif');

        Livewire::test('pages::admin.stores.form', ['store' => $this->senopati])->set('isActive', true)->call('save');
        $this->assertTrue($this->senopati->fresh()->is_active);
    }

    public function test_inactive_store_takes_no_new_orders_but_keeps_its_history(): void
    {
        $admin = $this->admin();
        $kopi = $this->product('Kopi', 40000);
        $old = Transaction::create(['store_id' => $this->senopati->id, 'status' => 'Paid', 'total_item' => 1, 'total_price' => 40000]);
        $old->lines()->create(['product_id' => $kopi->id, 'amount' => 1, 'price' => 40000]);
        $this->senopati->update(['is_active' => false]);
        $this->actingAs($admin);

        // Order screen offers only active stores; saving to the inactive one fails.
        Livewire::test('pages::transactions.form')
            ->assertViewHas('stores', fn ($stores) => $stores->pluck('name')->all() === ['Pamulang']);
        Livewire::test('pages::transactions.form')
            ->call('save', [$kopi->id => 1], '', '', '', $this->senopati->id)->assertHasErrors('storeId');

        // Its old order can still be opened and edited, keeping its store.
        Livewire::test('pages::transactions.form', ['transaction' => $old])
            ->call('save', [$kopi->id => 2], '', '', '', $this->senopati->id)->assertHasNoErrors();
        $this->assertSame(80000, $old->fresh()->total_price);

        // History stays in reports and the store is still selectable for viewing.
        $this->get('/')->assertSee('Rp80.000')->assertSee('Senopati (nonaktif)');
        $this->post(route('store.select'), ['store' => $this->senopati->id])->assertRedirect();
        $this->assertSame($this->senopati->id, StoreContext::for($admin)->currentId());
    }

    public function test_staff_with_only_an_inactive_store_cannot_create_orders(): void
    {
        $staff = $this->staff($this->senopati);
        $this->senopati->update(['is_active' => false]);
        $kopi = $this->product();
        $this->actingAs($staff);

        $this->get(route('transactions.create'))->assertOk()->assertSee('Tidak ada toko aktif');
        Livewire::test('pages::transactions.form')
            ->call('save', [$kopi->id => 1], '', '', '', $this->senopati->id)->assertHasErrors('storeId');
        $this->assertSame(0, Transaction::count());
    }

    public function test_inactive_store_without_sales_gets_no_warning_in_analysis(): void
    {
        $kopi = $this->product();
        Transaction::create(['store_id' => $this->pamulang->id, 'status' => 'Paid', 'total_item' => 1, 'total_price' => 25000])
            ->lines()->create(['product_id' => $kopi->id, 'amount' => 1, 'price' => 25000]);
        $this->senopati->update(['is_active' => false]);

        $data = (new \App\Support\SalesAnalytics([$this->pamulang->id, $this->senopati->id], '7h'))->toArray();
        $this->assertStringNotContainsString('Senopati', collect($data['insights'])->pluck('text')->join(' '));
        $this->assertSame(['Pamulang'], collect($data['stores'])->pluck('name')->all());
    }
}
