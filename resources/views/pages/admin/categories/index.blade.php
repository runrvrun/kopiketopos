<?php

use App\Models\ProductCategory;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kategori')] class extends Component
{
    /** Swap a category with its neighbour (-1 = up, 1 = down). */
    public function move(int $id, int $direction): void
    {
        $ids = ProductCategory::ordered()->pluck('id')->all();
        $from = array_search($id, $ids, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || ! isset($ids[$to])) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
        foreach ($ids as $i => $categoryId) {
            ProductCategory::whereKey($categoryId)->update(['sort_order' => $i + 1]);
        }
    }

    public function with(): array
    {
        return [
            'categories' => ProductCategory::ordered()->withCount([
                'products',
                'products as active_products_count' => fn ($q) => $q->where('products.is_active', true),
            ])->get(),
            'uncategorised' => \App\Models\Product::doesntHave('categories')->count(),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-between gap-3">
        <h1 class="page-title">Kategori</h1>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah</a>
    </div>
    <p class="mt-1 text-sm text-roast">Urutan di sini menjadi urutan kategori di halaman transaksi.</p>

    <ul class="card mt-4 divide-y divide-latte/70 overflow-hidden">
        @forelse ($categories as $category)
            <li wire:key="cat-{{ $category->id }}" class="flex items-center gap-1 py-1.5 pl-1 pr-2">
                <div class="flex flex-col">
                    <button type="button" wire:click="move({{ $category->id }}, -1)" class="flex size-8 items-center justify-center rounded-lg text-roast transition hover:bg-cream hover:text-espresso disabled:opacity-25" @disabled($loop->first) aria-label="Naikkan {{ $category->name }}">
                        <x-icon name="chevron-down" class="size-4 rotate-180" />
                    </button>
                    <button type="button" wire:click="move({{ $category->id }}, 1)" class="flex size-8 items-center justify-center rounded-lg text-roast transition hover:bg-cream hover:text-espresso disabled:opacity-25" @disabled($loop->last) aria-label="Turunkan {{ $category->name }}">
                        <x-icon name="chevron-down" class="size-4" />
                    </button>
                </div>
                <a href="{{ route('admin.categories.edit', $category) }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-cream">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-espresso-900">{{ $category->name }}</p>
                        <p class="text-xs text-roast">{{ $category->active_products_count }} produk aktif
                            @if ($category->products_count > $category->active_products_count) · {{ $category->products_count - $category->active_products_count }} nonaktif @endif
                        </p>
                    </div>
                    <x-icon name="chevron-right" class="size-4 text-latte" />
                </a>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-roast">Belum ada kategori.</li>
        @endforelse
    </ul>

    @if ($uncategorised > 0)
        <a href="{{ route('admin.products.index', ['kategori' => 'tanpa']) }}" class="mt-3 flex items-center justify-between rounded-xl bg-cream px-4 py-3 text-sm text-espresso-800">
            <span>{{ $uncategorised }} produk belum punya kategori</span>
            <span class="flex items-center gap-1 font-medium text-caramel-700">Lihat <x-icon name="chevron-right" class="size-4" /></span>
        </a>
    @endif
</div>
