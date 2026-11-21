<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use Livewire\Livewire;

class CustomerTest extends PosTestCase
{
    public function test_checkout_creates_a_customer_with_address(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product();

        Livewire::test('pages::transactions.form')
            ->call('save', [$kopi->id => 1], '  Budi   Santoso ', '', '', $this->pamulang->id, 'Jl. Melati 5, Pamulang')
            ->assertHasNoErrors();

        $customer = Customer::sole();
        $this->assertSame('Budi Santoso', $customer->name);
        $this->assertSame('Jl. Melati 5, Pamulang', $customer->address);
        $trx = Transaction::sole();
        $this->assertSame($customer->id, $trx->customer_id);
        $this->assertSame('Budi Santoso', $trx->customer);

        $this->get(route('transactions.show', $trx))->assertSee('Pelanggan: Budi Santoso')->assertSee('Alamat: Jl. Melati 5, Pamulang');
    }

    public function test_same_name_reuses_the_customer_and_keeps_address_when_left_empty(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product();
        $existing = Customer::create(['name' => 'Budi', 'address' => 'Jl. Lama 1']);

        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], 'BUDI', '', '', $this->pamulang->id, '');
        $this->assertSame(1, Customer::count());
        $this->assertSame('Jl. Lama 1', $existing->fresh()->address);
        $this->assertSame($existing->id, Transaction::sole()->customer_id);

        // A new address updates the customer.
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], 'budi', '', '', $this->pamulang->id, 'Jl. Baru 2', $existing->id);
        $this->assertSame('Jl. Baru 2', $existing->fresh()->address);

        // No name: no customer.
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], '', '', '', $this->pamulang->id, 'Jl. Somewhere');
        $this->assertNull(Transaction::latest('id')->first()->customer_id);
        $this->assertSame(1, Customer::count());
    }

    public function test_same_name_different_address_is_a_different_customer(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product();
        $budiPamulang = Customer::create(['name' => 'Budi', 'address' => 'Pamulang']);
        $budiBintaro = Customer::create(['name' => 'Budi', 'address' => 'Bintaro']);

        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], 'Budi', '', '', $this->pamulang->id, 'pamulang');
        $this->assertSame($budiPamulang->id, Transaction::latest('id')->first()->customer_id);

        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], 'Budi', '', '', $this->pamulang->id, 'Depok');
        $this->assertSame(3, Customer::count()); // a third Budi, living in Depok
        $this->assertSame('Bintaro', $budiBintaro->fresh()->address);
    }

    public function test_phone_is_saved_shown_on_receipt_and_found_by_autocomplete(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product();

        Livewire::test('pages::transactions.form')
            ->call('save', [$kopi->id => 1], 'Sari', '', '', $this->pamulang->id, 'Jl. Mawar', null, '0812-3456-7890');

        $this->assertSame('081234567890', Customer::sole()->phone);
        $this->get(route('transactions.show', Transaction::sole()))->assertSee('HP: 081234567890');

        $found = Livewire::test('pages::transactions.form')->instance()->searchCustomers('4567');
        $this->assertSame('Sari', $found[0]['name']);
        $this->assertSame('081234567890', $found[0]['phone']);
    }

    public function test_legacy_customer_strings_split_into_name_and_address(): void
    {
        $this->assertSame(['Juita Nagel', 'Gd Serpong, Tangerang'], Customer::splitLegacy('Juita Nagel, Gd Serpong, Tangerang'));
        $this->assertSame(['Budi', null], Customer::splitLegacy(' Budi '));
        $this->assertSame(['Budi', null], Customer::splitLegacy('Budi,'));
        $this->assertSame(['Rani', 'Serpong'], Customer::splitLegacy('Rani ,  Serpong '));
        $this->assertSame(['Pamulang', null], Customer::splitLegacy(', Pamulang'));
    }

    public function test_renamed_pick_creates_a_new_customer(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product();
        $budi = Customer::create(['name' => 'Budi']);

        // The picked id no longer matches the typed name, so it's not used.
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], 'Budiman', '', '', $this->pamulang->id, null, $budi->id);

        $this->assertSame(2, Customer::count());
        $this->assertSame('Budiman', Transaction::sole()->customerRecord->name);
    }

    public function test_autocomplete_finds_customers_by_part_of_the_name(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        Customer::create(['name' => 'Budi Santoso', 'address' => 'Jl. Melati 5']);
        Customer::create(['name' => 'Ani Budiarti']);
        Customer::create(['name' => 'Sari']);

        $component = Livewire::test('pages::transactions.form');

        $names = collect($component->instance()->searchCustomers('budi'))->pluck('name')->all();
        $this->assertSame(['Budi Santoso', 'Ani Budiarti'], $names); // starts-with first
        $this->assertSame('Jl. Melati 5', $component->instance()->searchCustomers('BUDI S')[0]['address']);
        $this->assertSame([], $component->instance()->searchCustomers('b'));
        $this->assertSame([], $component->instance()->searchCustomers('%%'));
    }

    public function test_editing_an_order_prefills_customer_and_address(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product();
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], 'Budi', '', '', $this->pamulang->id, 'Jl. Melati 5');

        $this->get(route('transactions.edit', Transaction::sole()))->assertOk()->assertSee('Jl. Melati 5');
    }
}
