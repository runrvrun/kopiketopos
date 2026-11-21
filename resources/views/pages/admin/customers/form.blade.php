<?php

use App\Actions\MergeCustomers;
use App\Models\Customer;
use App\Models\Transaction;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pelanggan')] class extends Component
{
    #[Locked]
    public ?int $customerId = null;

    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public string $mergeSearch = '';

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->customerId = $customer->id;
            $this->name = $customer->name;
            $this->address = (string) $customer->address;
            $this->phone = (string) $customer->phone;
        }
    }

    /** v1 names often carry the address after a comma: move it to the address field. */
    public function splitAddress(): void
    {
        if (! str_contains($this->name, ',') || filled($this->address)) {
            return;
        }

        [$name, $rest] = array_map('trim', explode(',', $this->name, 2));
        if ($name !== '' && $rest !== '') {
            $this->name = $name;
            $this->address = $rest;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s.-]*$/'],
        ], [
            'phone.regex' => 'No. HP hanya boleh berisi angka.',
            'name.required' => 'Nama pelanggan wajib diisi.',
        ]);

        $customer = $this->customerId ? Customer::findOrFail($this->customerId) : new Customer;
        $customer->fill([
            'name' => $data['name'],
            'address' => filled($data['address']) ? trim($data['address']) : null,
            'phone' => filled($data['phone']) ? preg_replace('/[^0-9+]/', '', $data['phone']) : null,
        ])->save();

        session()->flash('status', $this->customerId ? 'Pelanggan diperbarui' : 'Pelanggan ditambahkan');
        $this->redirectRoute('admin.customers.index');
    }

    /** Move the other customer's transactions here and remove the duplicate. */
    public function merge(int $duplicateId): void
    {
        $keep = Customer::findOrFail($this->customerId);
        $duplicate = Customer::findOrFail($duplicateId);

        $moved = app(MergeCustomers::class)->handle($keep, $duplicate);
        $this->address = (string) $keep->fresh()->address;
        $this->mergeSearch = '';

        session()->flash('status', "{$duplicate->name} digabungkan ({$moved} transaksi dipindah)");
        $this->redirectRoute('admin.customers.edit', $keep);
    }

    public function delete(): void
    {
        // Soft delete: hidden from lists and autocomplete; past orders and reports keep it.
        Customer::findOrFail($this->customerId)->delete();
        session()->flash('status', 'Pelanggan dihapus');
        $this->redirectRoute('admin.customers.index');
    }

    public function with(): array
    {
        if (! $this->customerId) {
            return ['customer' => null, 'recent' => collect(), 'candidates' => collect(), 'searching' => false];
        }

        $customer = Customer::withStats()->findOrFail($this->customerId);

        $recent = Transaction::with('store:id,name')->where('customer_id', $customer->id)
            ->latest()->latest('id')->limit(10)->get();

        // Merge candidates: a search, or else look-alike names (same first word).
        $term = Customer::keyFor($this->mergeSearch);
        $searching = mb_strlen($term) >= 2;
        $firstWord = explode(' ', explode(',', $customer->name_key)[0])[0];

        $candidates = Customer::query()->withStats()
            ->whereKeyNot($customer->id)
            ->when($searching,
                fn ($q) => $q->where(fn ($q) => $q->where('name_key', 'like', '%'.$term.'%')->orWhere('address', 'like', '%'.trim($this->mergeSearch).'%')),
                fn ($q) => mb_strlen($firstWord) >= 3 ? $q->where('name_key', 'like', $firstWord.'%') : $q->whereRaw('1 = 0'))
            ->orderByDesc('visits')
            ->limit(6)
            ->get();

        return compact('customer', 'recent', 'candidates', 'searching');
    }
};
?>

<div class="mx-auto max-w-xl space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.customers.index') }}" class="btn-icon -ml-2" aria-label="Kembali"><x-icon name="arrow-left" /></a>
        <h1 class="page-title">{{ $customerId ? 'Ubah pelanggan' : 'Tambah pelanggan' }}</h1>
    </div>

    @if ($customer)
        <dl class="grid grid-cols-3 gap-2 text-center">
            <div class="card p-3"><dt class="text-[11px] text-roast">Belanja</dt><dd class="font-semibold text-espresso-900">{{ $customer->visits }}×</dd></div>
            <div class="card p-3"><dt class="text-[11px] text-roast">Total</dt><dd class="font-semibold text-espresso-900">{{ rupiah_short($customer->spend) }}</dd></div>
            <div class="card p-3"><dt class="text-[11px] text-roast">Terakhir</dt><dd class="text-sm font-semibold text-espresso-900">{{ $customer->last_at ? \Carbon\Carbon::parse($customer->last_at)->translatedFormat('j M y') : '–' }}</dd></div>
        </dl>
    @endif

    <form wire:submit="save" class="card space-y-4 p-4 sm:p-6" novalidate>
        <div>
            <label for="name" class="label">Nama</label>
            <input id="name" type="text" wire:model.live.debounce.400ms="name" maxlength="100" class="input" autocomplete="off">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
            @if (str_contains($name, ',') && blank($address))
                <button type="button" wire:click="splitAddress" class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-caramel-100 px-3 py-1.5 text-xs font-medium text-caramel-700">
                    <x-icon name="merge" class="size-4" /> Pisahkan alamat: pindahkan teks setelah koma ke Alamat
                </button>
            @endif
        </div>
        <div>
            <label for="address" class="label">Alamat</label>
            <textarea id="address" wire:model="address" rows="3" maxlength="255" class="input h-auto py-2.5"></textarea>
            @error('address') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="label">No. HP</label>
            <input id="phone" type="tel" inputmode="tel" wire:model="phone" maxlength="30" class="input" placeholder="08xx" autocomplete="off">
            @error('phone') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        @if ($customer)
            <p class="text-xs text-roast">Mengubah nama tidak mengubah struk lama; struk tetap memakai nama yang diketik saat transaksi.</p>
        @endif

        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-between">
            @if ($customerId)
                <button type="button" wire:click="delete" wire:confirm="Hapus pelanggan ini? Pelanggan hilang dari daftar dan saran nama, tapi riwayat transaksinya tetap tercatat. Jika ini duplikat, lebih baik gabungkan." class="btn btn-danger"><x-icon name="trash" class="size-4" /> Hapus</button>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary h-12 sm:h-11" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Simpan</button>
        </div>
        @error('delete') <p class="rounded-xl bg-cherry/10 px-3 py-2 text-sm text-cherry">{{ $message }}</p> @enderror
    </form>

    @if ($customer)
        {{-- Merge duplicates --}}
        <section class="card p-4" aria-labelledby="gabung">
            <h2 id="gabung" class="flex items-center gap-2 font-semibold text-espresso-900"><x-icon name="merge" class="size-5 text-caramel-600" /> Gabungkan duplikat</h2>
            <p class="mt-0.5 text-xs text-roast">Transaksi pelanggan yang dipilih dipindah ke <strong class="font-semibold text-espresso-800">{{ $customer->name }}</strong>, lalu pelanggan tersebut dihapus.</p>

            <label class="relative mt-3 block">
                <span class="sr-only">Cari pelanggan duplikat</span>
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-roast" />
                <input type="search" wire:model.live.debounce.300ms="mergeSearch" class="input pl-10" placeholder="Cari pelanggan lain">
            </label>

            <p class="mt-3 text-xs font-semibold uppercase tracking-wider text-roast">{{ $searching ? 'Hasil pencarian' : 'Nama mirip' }}</p>
            <ul class="mt-1 divide-y divide-latte/60">
                @forelse ($candidates as $c)
                    <li wire:key="m-{{ $c->id }}" class="flex items-center gap-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-espresso-900">{{ $c->name }}</p>
                            <p class="truncate text-xs text-roast">{{ $c->visits }}× belanja · {{ $c->address ?: 'tanpa alamat' }}@if ($c->phone) · {{ $c->phone }}@endif</p>
                        </div>
                        <button type="button" wire:click="merge({{ $c->id }})"
                            wire:confirm="Gabungkan &quot;{{ $c->name }}&quot; ke &quot;{{ $customer->name }}&quot;? {{ $c->visits }} transaksi akan dipindah dan &quot;{{ $c->name }}&quot; dihapus."
                            class="btn btn-secondary h-9 shrink-0 px-3 text-xs">Gabungkan</button>
                    </li>
                @empty
                    <li class="py-2.5 text-sm text-roast">{{ $searching ? 'Tidak ada pelanggan yang cocok.' : 'Tidak ada nama yang mirip. Cari pelanggan lain di atas.' }}</li>
                @endforelse
            </ul>
        </section>

        {{-- Recent transactions --}}
        <section class="card overflow-hidden" aria-labelledby="riwayat">
            <h2 id="riwayat" class="border-b border-latte/70 px-4 py-3 font-semibold text-espresso-900">Transaksi terakhir</h2>
            <ul class="divide-y divide-latte/70">
                @forelse ($recent as $trx)
                    <li>
                        <a href="{{ route('transactions.show', $trx) }}" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-cream">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-espresso-900">{{ $trx->created_at->translatedFormat('j M Y, H:i') }}</p>
                                <p class="truncate text-xs text-roast">{{ $trx->invoiceNumber() }} · {{ $trx->total_item }} item @if ($trx->store) · {{ $trx->store->name }} @endif</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold tabular-nums text-espresso-900">{{ rupiah($trx->netTotal()) }}</p>
                                @unless ($trx->isPaid())<p class="text-[11px] font-medium text-caramel-700">Belum lunas</p>@endunless
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-3 text-sm text-roast">Belum ada transaksi.</li>
                @endforelse
            </ul>
        </section>
    @endif
</div>
