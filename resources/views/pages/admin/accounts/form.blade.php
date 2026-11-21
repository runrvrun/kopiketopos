<?php

use App\Models\PaymentAccount;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rekening')] class extends Component
{
    #[Locked]
    public ?int $accountId = null;

    public string $bank = '';
    public string $account_number = '';
    public string $account_owner = '';

    public function mount(?PaymentAccount $account = null): void
    {
        if ($account?->exists) {
            $this->accountId = $account->id;
            $this->fill($account->only('bank', 'account_number', 'account_owner'));

            return;
        }

        // Only one payment account: "add" edits the existing one instead.
        if ($existing = PaymentAccount::first()) {
            $this->redirectRoute('admin.accounts.edit', $existing);
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'bank' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_owner' => ['required', 'string', 'max:100'],
        ], [
            'bank.required' => 'Nama bank wajib diisi.',
            'account_number.required' => 'Nomor rekening wajib diisi.',
            'account_owner.required' => 'Nama pemilik rekening wajib diisi.',
        ]);

        $account = $this->accountId ? PaymentAccount::findOrFail($this->accountId) : new PaymentAccount;
        if (! $account->exists && PaymentAccount::exists()) {
            $this->addError('bank', 'Hanya boleh ada satu rekening. Ubah rekening yang sudah ada.');

            return;
        }
        $account->fill(array_map('trim', $data))->save();

        session()->flash('status', $this->accountId ? 'Rekening diperbarui' : 'Rekening ditambahkan');
        $this->redirectRoute('admin.accounts.index');
    }

    public function delete(): void
    {
        PaymentAccount::findOrFail($this->accountId)->delete();

        session()->flash('status', 'Rekening dihapus');
        $this->redirectRoute('admin.accounts.index');
    }
};
?>

<div class="mx-auto max-w-xl">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.accounts.index') }}" class="btn-icon -ml-2" aria-label="Kembali"><x-icon name="arrow-left" /></a>
        <h1 class="page-title">{{ $accountId ? 'Ubah rekening' : 'Tambah rekening' }}</h1>
    </div>

    <form wire:submit="save" class="card mt-4 space-y-4 p-4 sm:p-6" novalidate>
        <div>
            <label for="bank" class="label">Bank</label>
            <input id="bank" type="text" wire:model="bank" class="input" placeholder="mis. BCA" autocomplete="off">
            @error('bank') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="account_number" class="label">No Rek</label>
            <input id="account_number" type="text" inputmode="numeric" wire:model="account_number" class="input font-mono tracking-wide" placeholder="mis. 1234567890" autocomplete="off">
            @error('account_number') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="account_owner" class="label">Nama</label>
            <input id="account_owner" type="text" wire:model="account_owner" class="input" placeholder="Nama pemilik rekening" autocomplete="off">
            @error('account_owner') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-between">
            @if ($accountId)
                <button type="button" wire:click="delete" wire:confirm="Hapus rekening ini?" class="btn btn-danger"><x-icon name="trash" class="size-4" /> Hapus</button>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary h-12 sm:h-11" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Simpan</button>
        </div>
    </form>
</div>
