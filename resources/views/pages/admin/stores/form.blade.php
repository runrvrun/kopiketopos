<?php

use App\Models\Store;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Toko')] class extends Component
{
    #[Locked]
    public ?int $storeId = null;

    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public bool $isActive = true;

    public function mount(?Store $store = null): void
    {
        if ($store?->exists) {
            $this->storeId = $store->id;
            $this->name = $store->name;
            $this->address = (string) $store->address;
            $this->phone = (string) $store->phone;
            $this->isActive = $store->is_active;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'isActive' => ['boolean'],
        ], [
            'name.required' => 'Nama toko wajib diisi.',
        ]);

        $store = $this->storeId ? Store::findOrFail($this->storeId) : new Store;
        $store->fill([
            'name' => $data['name'],
            'address' => $data['address'] ?: null,
            'phone' => $data['phone'] ?: null,
            'is_active' => $data['isActive'],
        ])->save();

        session()->flash('status', $this->storeId ? 'Toko diperbarui' : 'Toko ditambahkan');
        $this->redirectRoute('admin.stores.index');
    }

    public function delete(): void
    {
        // Soft delete: gone from the store selector and staff access; its past
        // transactions stay in "Semua toko" reports.
        Store::findOrFail($this->storeId)->delete();
        session()->flash('status', 'Toko dihapus');
        $this->redirectRoute('admin.stores.index');
    }
};
?>

<div class="mx-auto max-w-xl">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.stores.index') }}" class="btn-icon -ml-2" aria-label="Kembali"><x-icon name="arrow-left" /></a>
        <h1 class="page-title">{{ $storeId ? 'Ubah toko' : 'Tambah toko' }}</h1>
    </div>

    <form wire:submit="save" class="card mt-4 space-y-4 p-4 sm:p-6" novalidate>
        <div>
            <label for="name" class="label">Nama toko</label>
            <input id="name" type="text" wire:model="name" class="input" placeholder="mis. Pamulang">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="address" class="label">Alamat <span class="font-normal text-roast">(dicetak di struk)</span></label>
            <textarea id="address" wire:model="address" rows="2" class="input h-auto py-2.5"></textarea>
            @error('address') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="label">Telepon <span class="font-normal text-roast">(dicetak di struk)</span></label>
            <input id="phone" type="tel" inputmode="tel" wire:model="phone" class="input">
            @error('phone') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-xl bg-cream px-3 py-2">
            <span>
                <span class="block text-sm font-medium text-espresso-900">Aktif</span>
                <span class="block text-xs text-roast">Toko nonaktif tidak bisa dipakai untuk transaksi baru. Riwayat dan laporannya tetap ada.</span>
            </span>
            <input type="checkbox" wire:model="isActive" class="size-5 rounded-md accent-espresso">
        </label>

        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-between">
            @if ($storeId)
                <button type="button" wire:click="delete" wire:confirm="Hapus toko ini? Toko hilang dari pilihan toko dan akses staf, tapi riwayat penjualannya tetap tercatat." class="btn btn-danger"><x-icon name="trash" class="size-4" /> Hapus</button>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary h-12 sm:h-11" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Simpan</button>
        </div>
        @error('delete') <p class="rounded-xl bg-cherry/10 px-3 py-2 text-sm text-cherry">{{ $message }}</p> @enderror
    </form>
</div>
