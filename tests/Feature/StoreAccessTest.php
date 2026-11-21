<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Support\StoreContext;

class StoreAccessTest extends PosTestCase
{
    public function test_admin_defaults_to_all_stores(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $context = StoreContext::for($admin);
        $this->assertTrue($context->isAll());
        $this->assertEqualsCanonicalizing([$this->pamulang->id, $this->senopati->id], $context->storeIds());
    }

    public function test_staff_only_sees_assigned_stores(): void
    {
        $staff = $this->staff($this->senopati);
        $this->actingAs($staff);

        $context = StoreContext::for($staff);
        $this->assertFalse($staff->hasAllStores());
        $this->assertSame([$this->senopati->id], $context->storeIds());

        $this->post(route('store.select'), ['store' => 'all'])->assertForbidden();
        $this->post(route('store.select'), ['store' => $this->pamulang->id])->assertForbidden();
    }

    public function test_staff_with_every_store_can_choose_all(): void
    {
        $staff = $this->staff($this->pamulang, $this->senopati);

        $this->actingAs($staff)->post(route('store.select'), ['store' => 'all'])->assertRedirect();
        $this->assertTrue(StoreContext::for($staff)->isAll());
    }

    public function test_store_selection_filters_the_dashboard(): void
    {
        $admin = $this->admin();
        Transaction::create(['store_id' => $this->pamulang->id, 'total_item' => 1, 'total_price' => 30000, 'discount' => 5000, 'status' => 'Paid']);
        Transaction::create(['store_id' => $this->senopati->id, 'total_item' => 1, 'total_price' => 20000, 'status' => 'Paid']);
        Transaction::create(['store_id' => $this->senopati->id, 'total_item' => 1, 'total_price' => 99000]); // ongoing: not counted

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Rp45.000')->assertSee('Per toko');

        $this->post(route('store.select'), ['store' => $this->pamulang->id]);
        $this->get('/')->assertOk()->assertSee('Rp25.000')->assertDontSee('Rp45.000');
    }

    public function test_staff_cannot_open_another_stores_transaction(): void
    {
        $trx = Transaction::create(['store_id' => $this->pamulang->id, 'total_item' => 1, 'total_price' => 1000, 'status' => 'Paid']);

        $this->actingAs($this->staff($this->senopati))->get(route('transactions.show', $trx))->assertForbidden();
    }
}
