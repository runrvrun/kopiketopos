<?php

use App\Models\Product;
use App\Models\ProductCategory;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Produk')] class extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'aktif')]
    public string $status = 'aktif';

    #[Url(as: 'kategori', except: '')]
    public string $category = '';

    public function with(): array
    {
        $products = Product::query()
            ->when($this->status === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->with('categories:id,name')
            ->when($this->category === 'tanpa', fn ($q) => $q->doesntHave('categories'))
            ->when(ctype_digit($this->category), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey((int) $this->category)))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderBy('name')
            ->get();

        $categories = ProductCategory::ordered()->get(['id', 'name']);

        return compact('products', 'categories');
    }
};
?>

<div>
    <div class="flex items-center justify-between gap-3">
        <h1 class="page-title">Produk</h1>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah</a>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto_auto]">
        <label class="relative block">
            <span class="sr-only">Cari produk</span>
            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-roast" />
            <input type="search" wire:model.live.debounce.300ms="search" class="input pl-10" placeholder="Cari produk…">
        </label>
        <div class="grid grid-cols-2 gap-2 sm:contents">
            <select wire:model.live="category" class="input" aria-label="Kategori">
                <option value="">Semua kategori</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
                <option value="tanpa">Tanpa kategori</option>
            </select>
            <select wire:model.live="status" class="input" aria-label="Status">
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
                <option value="semua">Semua status</option>
            </select>
        </div>
    </div>

    <p class="mt-3 text-xs text-roast">{{ $products->count() }} produk</p>

    <ul class="card mt-2 divide-y divide-latte/70 overflow-hidden" wire:loading.class="opacity-60">
        @forelse ($products as $p)
            <li wire:key="p-{{ $p->id }}">
                <a href="{{ route('admin.products.edit', $p) }}" class="flex items-center gap-3 px-3 py-2.5 transition hover:bg-cream sm:px-4">
                    <img src="{{ $p->imageUrl() }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-xl bg-cream object-cover {{ $p->is_active ? '' : 'opacity-50 grayscale' }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-espresso-900">{{ $p->name }}</p>
                        <p class="truncate text-xs text-roast">{{ $p->categories->sortBy('sort_order')->pluck('name')->join(', ') ?: 'Tanpa kategori' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold tabular-nums text-espresso-900">{{ rupiah($p->price) }}</p>
                        @unless ($p->is_active)<p class="text-xs font-medium text-roast">Nonaktif</p>@endunless
                    </div>
                    <x-icon name="chevron-right" class="size-4 text-latte" />
                </a>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-roast">Tidak ada produk.</li>
        @endforelse
    </ul>
</div>
