<?php

namespace Tests\Feature;

use App\Models\PaymentAccount;
use Livewire\Livewire;

class PaymentAccountTest extends PosTestCase
{
    public function test_admin_manages_payment_accounts(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.accounts.index'))->assertOk()->assertSee('Belum ada rekening');

        Livewire::test('pages::admin.accounts.form')->call('save')->assertHasErrors(['bank', 'account_number', 'account_owner']);

        Livewire::test('pages::admin.accounts.form')
            ->set('bank', ' BCA ')->set('account_number', '1234567890')->set('account_owner', 'Kopi Keto')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('admin.accounts.index'));
        $account = PaymentAccount::sole();
        $this->assertSame('BCA', $account->bank);

        $this->get(route('admin.accounts.index'))->assertSee('1234567890')->assertDontSee(route('admin.accounts.create'));

        // Only one account: the create page sends you to the existing one, and saving a second fails.
        Livewire::test('pages::admin.accounts.form')->assertRedirect(route('admin.accounts.edit', $account));
        $this->assertSame(1, PaymentAccount::count());
        $this->get(route('admin.accounts.edit', $account))->assertOk();

        Livewire::test('pages::admin.accounts.form', ['account' => $account])->set('bank', 'Mandiri')->call('save')->assertHasNoErrors();
        $this->assertSame('Mandiri', $account->fresh()->bank);

        Livewire::test('pages::admin.accounts.form', ['account' => $account])->call('delete');
        $this->assertModelMissing($account);
    }

    public function test_staff_cannot_manage_payment_accounts(): void
    {
        $this->actingAs($this->staff($this->pamulang))->get(route('admin.accounts.index'))->assertForbidden();
    }
}
