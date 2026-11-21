<?php

namespace Tests\Feature;

use App\Models\PaymentAccount;
use App\Models\Transaction;
use Livewire\Livewire;

class TransactionTest extends PosTestCase
{
    public function test_checkout_saves_a_pending_transaction_and_opens_the_receipt(): void
    {
        $staff = $this->staff($this->pamulang);
        $kopi = $this->product('Kopi Susu', 25000);
        $roti = $this->product('Roti Keto', 18000);
        $this->actingAs($staff);

        $component = Livewire::test('pages::transactions.form')
            ->call('save', [$kopi->id => 2, $roti->id => 1, 999 => 3], 'Budi', '3.000', '', $this->pamulang->id)
            ->assertHasNoErrors();

        $trx = Transaction::with('lines')->sole();
        $component->assertRedirect(route('transactions.show', $trx));

        $this->assertFalse($trx->isPaid());
        $this->assertSame(68000, $trx->total_price);
        $this->assertSame(3, $trx->total_item);
        $this->assertSame(3000, $trx->discount);
        $this->assertSame('Budi', $trx->customer);
        $this->assertSame($staff->id, $trx->user_id);
        $this->assertSame((int) (now()->format('Ymd').'00001'), $trx->key);
        $this->assertEquals([25000, 18000], $trx->lines->sortBy('product_id')->pluck('price')->values()->all());
    }

    public function test_invoice_keys_increment_per_day(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product();

        foreach ([1, 2] as $_) {
            Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], '', '', '', $this->pamulang->id);
        }

        $this->assertSame([(int) (now()->format('Ymd').'00001'), (int) (now()->format('Ymd').'00002')], Transaction::orderBy('id')->pluck('key')->all());
    }

    public function test_empty_order_and_foreign_store_are_rejected(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product();

        Livewire::test('pages::transactions.form')->call('save', [], '', '', '', $this->pamulang->id)->assertHasErrors('items');
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], '', '', '', $this->senopati->id)->assertHasErrors('storeId');

        $this->assertSame(0, Transaction::count());
    }

    public function test_pending_receipt_shows_payment_accounts_and_lunas_marks_it_paid(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        PaymentAccount::create(['bank' => 'BCA', 'account_number' => '1234567890', 'account_owner' => 'Kopi Keto']);
        $kopi = $this->product('Kopi Susu', 25000);
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 2], 'Sari', '', '10000', $this->pamulang->id);
        $trx = Transaction::sole();

        $this->get(route('transactions.show', $trx))->assertOk()
            ->assertSee('Belum lunas')->assertSee('TAGIHAN')->assertSee('40.000') // 50.000 − DP 10.000
            ->assertSee('Pembayaran via transfer')->assertSee('1234567890')->assertSee('a.n. Kopi Keto')
            ->assertSee('Kirim WA')->assertSee('Lunas');

        Livewire::test('pages::transactions.show', ['transaction' => $trx])
            ->call('markPaid')
            ->assertSet('justPaid', true)
            ->assertDontSee('Pembayaran via transfer')
            ->assertSee('DIBAYAR');

        $this->assertTrue($trx->fresh()->isPaid());
        $this->get(route('transactions.show', $trx))->assertOk()->assertSee('Kirim WA')->assertDontSee('1234567890');
    }

    public function test_editing_keeps_status_and_original_line_price(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product('Kopi Susu', 25000);
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], '', '', '', $this->pamulang->id);
        $trx = Transaction::sole();
        $trx->update(['status' => Transaction::STATUS_PAID]);
        $kopi->update(['price' => 30000]);

        Livewire::test('pages::transactions.form', ['transaction' => $trx])
            ->call('save', [$kopi->id => 2], '', '', '', $this->pamulang->id)
            ->assertRedirect(route('transactions.show', $trx));

        $trx->refresh();
        $this->assertSame(50000, $trx->total_price);
        $this->assertTrue($trx->isPaid());
    }

    public function test_pages_render_and_transaction_can_be_deleted(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $kopi = $this->product('Kopi Susu', 25000, ['image' => 'uploads/product/does-not-exist.jpg']);
        Livewire::test('pages::transactions.form')->call('save', [$kopi->id => 1], 'Sari', '', '', $this->pamulang->id);
        $trx = Transaction::sole();

        $this->get(route('transactions.index'))->assertOk()->assertSee('Sari')->assertSee('Belum lunas')->assertSee(route('transactions.show', $trx));
        $this->get(route('transactions.create'))->assertOk()->assertSee('Kopi Susu')->assertSee('default.png')->assertSee('Checkout');
        $this->get(route('transactions.edit', $trx))->assertOk()->assertSee('Ubah transaksi');
        $this->get(route('transactions.show', $trx))->assertOk()->assertSee('Jl. Alam Asri 1');

        Livewire::test('pages::transactions.form', ['transaction' => $trx])->call('delete')->assertRedirect(route('transactions.index'));
        $this->assertSame(0, Transaction::count());
        $this->assertSoftDeleted($trx);
        $this->assertDatabaseCount('product_transaction', 1); // lines kept with the soft-deleted transaction
    }
}
