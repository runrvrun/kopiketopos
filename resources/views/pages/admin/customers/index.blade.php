<?php

use App\Models\Customer;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pelanggan')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'urut', except: 'terbaru')]
    public string $sort = 'terbaru';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $term = trim($this->search);

        $customers = Customer::query()
            ->withStats()
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name_key', 'like', '%'.Customer::keyFor($term).'%')
                ->orWhere('address', 'like', '%'.$term.'%')
                ->when(strlen(preg_replace('/\D/', '', $term)) >= 4, fn ($q) => $q->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $term).'%'))))
            ->when($this->sort === 'belanja', fn ($q) => $q->orderByDesc('spend'))
            ->when($this->sort === 'kunjungan', fn ($q) => $q->orderByDesc('visits'))
            ->when($this->sort === 'nama', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'terbaru', fn ($q) => $q->orderByDesc('last_at'))
            ->orderBy('id', 'desc')
            ->paginate(30);

        return ['customers' => $customers, 'total' => Customer::count()];
    }
};
?>

<div>
    <div class="flex items-center justify-between gap-3">
        <h1 class="page-title">Pelanggan</h1>
        <a href="{{ route('admin.customers.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah</a>
    </div>
    <p class="mt-1 text-sm text-roast">{{ number_format($total, 0, ',', '.') }} pelanggan. Pelanggan baru otomatis tersimpan saat checkout.</p>

    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_12rem]">
        <label class="relative block">
            <span class="sr-only">Cari pelanggan</span>
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-roast" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-10" placeholder="Cari nama, alamat, atau no. HP">
        </label>
        <select wire:model.live="sort" class="input" aria-label="Urutkan">
            <option value="terbaru">Belanja terakhir</option>
            <option value="belanja">Total belanja terbesar</option>
            <option value="kunjungan">Paling sering belanja</option>
            <option value="nama">Nama (A–Z)</option>
        </select>
    </div>

    <ul class="card mt-4 divide-y divide-latte/70 overflow-hidden" wire:loading.class="opacity-60">
        @forelse ($customers as $c)
            <li wire:key="c-{{ $c->id }}">
                <a href="{{ route('admin.customers.edit', $c) }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-cream">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-espresso-900">{{ $c->name }}</p>
                        <p class="truncate text-xs text-roast">{{ $c->address ?: 'Alamat belum diisi' }}@if ($c->phone) · {{ $c->phone }}@endif</p>
                        <p class="mt-0.5 text-[11px] text-roast">
                            {{ $c->visits }}× belanja
                            @if ($c->last_at) · terakhir {{ \Carbon\Carbon::parse($c->last_at)->translatedFormat('j M Y') }} @endif
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-semibold tabular-nums text-espresso-900">{{ rupiah_short($c->spend) }}</p>
                    </div>
                    <x-icon name="chevron-right" class="size-4 text-latte" />
                </a>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-roast">{{ $search !== '' ? 'Tidak ada pelanggan yang cocok.' : 'Belum ada pelanggan.' }}</li>
        @endforelse
    </ul>

    @if ($customers->hasPages())
        <nav class="mt-5 flex items-center justify-between gap-3" aria-label="Halaman">
            <button type="button" wire:click="previousPage" class="btn btn-secondary" @disabled($customers->onFirstPage())><x-icon name="arrow-left" class="size-4" /> Sebelumnya</button>
            <span class="text-sm text-roast tabular-nums">{{ $customers->currentPage() }} / {{ $customers->lastPage() }}</span>
            <button type="button" wire:click="nextPage" class="btn btn-secondary" @disabled(! $customers->hasMorePages())>Berikutnya <x-icon name="chevron-right" class="size-4" /></button>
        </nav>
    @endif
</div>
