<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use Livewire\Livewire;

class CustomerAdminTest extends PosTestCase
{
    private function order(Customer $customer, int $total, string $status = 'Paid'): Transaction
    {
        return Transaction::create([
            'store_id' => $this->pamulang->id, 'customer_id' => $customer->id, 'customer' => $customer->name,
            'total_item' => 1, 'total_price' => $total, 'status' => $status ?: null,
        ]);
    }

    public function test_list_shows_stats_search_and_sorting(): void
    {
        $this->actingAs($this->admin());
        $budi = Customer::create(['name' => 'Budi', 'address' => 'Jl. Melati 5']);
        $sari = Customer::create(['name' => 'Sari']);
        $this->order($budi, 50000);
        $this->order($budi, 30000);
        $this->order($budi, 99000, ''); // pending: not counted in stats
        $this->order($sari, 200000);

        $this->get(route('admin.customers.index'))->assertOk()->assertSee('Budi')->assertSee('2× belanja')->assertSee('Rp80 rb');

        Livewire::test('pages::admin.customers.index')->set('search', 'melati')->assertSee('Budi')->assertDontSee('Sari');
        Livewire::test('pages::admin.customers.index')->set('sort', 'belanja')
            ->assertSeeInOrder(['Sari', 'Budi']);
        Livewire::test('pages::admin.customers.index')->set('sort', 'kunjungan')
            ->assertSeeInOrder(['Budi', 'Sari']);
    }

    public function test_edit_split_address_and_create(): void
    {
        $this->actingAs($this->admin());
        $customer = Customer::create(['name' => 'Juita Nagel, Gd Serpong, Tangerang']);
        $trx = $this->order($customer, 10000);

        Livewire::test('pages::admin.customers.form', ['customer' => $customer])
            ->assertSee('Pisahkan alamat')
            ->call('splitAddress')
            ->assertSet('name', 'Juita Nagel')
            ->assertSet('address', 'Gd Serpong, Tangerang')
            ->call('save')->assertHasNoErrors();

        $customer->refresh();
        $this->assertSame('Juita Nagel', $customer->name);
        $this->assertSame('juita nagel', $customer->name_key);
        $this->assertSame('Juita Nagel, Gd Serpong, Tangerang', $trx->fresh()->customer); // receipt keeps the typed name

        Livewire::test('pages::admin.customers.form')->set('name', 'Pelanggan Baru')->set('address', 'Jl. Baru')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'Pelanggan Baru', 'address' => 'Jl. Baru']);
    }

    public function test_merge_moves_transactions_and_removes_the_duplicate(): void
    {
        $this->actingAs($this->admin());
        $keep = Customer::create(['name' => 'Budi Santoso']);
        $dupe = Customer::create(['name' => 'Budi S', 'address' => 'Jl. Melati 5']);
        $this->order($keep, 10000);
        $this->order($dupe, 20000);
        $deleted = $this->order($dupe, 30000);
        $deleted->delete();

        Livewire::test('pages::admin.customers.form', ['customer' => $keep])
            ->assertSee('Budi S') // suggested look-alike (same first word)
            ->call('merge', $dupe->id)
            ->assertRedirect(route('admin.customers.edit', $keep));

        $this->assertSoftDeleted($dupe);
        $this->assertSame(3, Transaction::withTrashed()->where('customer_id', $keep->id)->count());
        $this->assertSame('Jl. Melati 5', $keep->fresh()->address); // missing address taken from the duplicate
    }

    public function test_soft_delete_hides_the_customer_but_keeps_history(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $budi = Customer::create(['name' => 'Budi', 'address' => 'Jl. Melati 5', 'phone' => '0812']);
        $trx = $this->order($budi, 10000);

        Livewire::test('pages::admin.customers.form', ['customer' => $budi])->call('delete')->assertRedirect(route('admin.customers.index'));

        $this->assertSoftDeleted($budi);
        $this->assertSame($budi->id, $trx->fresh()->customer_id);
        $this->get(route('admin.customers.index'))->assertDontSee('Jl. Melati 5');
        $this->assertSame([], Livewire::test('pages::transactions.form')->instance()->searchCustomers('budi'));
        $this->get(route('transactions.show', $trx))->assertSee('Alamat: Jl. Melati 5'); // receipt still complete
    }

    public function test_phone_is_saved_and_searchable(): void
    {
        $this->actingAs($this->admin());
        Livewire::test('pages::admin.customers.form')->set('name', 'Sari')->set('phone', '0812-3456 789')->call('save')->assertHasNoErrors();
        $this->assertSame('08123456789', Customer::sole()->phone);

        Livewire::test('pages::admin.customers.form')->set('name', 'X')->set('phone', 'abc')->call('save')->assertHasErrors('phone');
        Livewire::test('pages::admin.customers.index')->set('search', '3456')->assertSee('Sari');
    }

    public function test_staff_cannot_manage_customers(): void
    {
        $this->actingAs($this->staff($this->pamulang))->get(route('admin.customers.index'))->assertForbidden();
    }
}
