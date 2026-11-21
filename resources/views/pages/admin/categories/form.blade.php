<?php

use App\Models\ProductCategory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kategori')] class extends Component
{
    #[Locked]
    public ?int $categoryId = null;

    public string $name = '';

    public function mount(?ProductCategory $category = null): void
    {
        if ($category?->exists) {
            $this->categoryId = $category->id;
            $this->name = $category->name;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('product_categories')->withoutTrashed()->ignore($this->categoryId)],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Kategori dengan nama ini sudah ada.',
        ]);

        if ($this->categoryId) {
            ProductCategory::findOrFail($this->categoryId)->update(['name' => $data['name']]);
        } else {
            ProductCategory::create([
                'name' => $data['name'],
                'sort_order' => (int) ProductCategory::max('sort_order') + 1, // new ones go last
            ]);
        }

        session()->flash('status', $this->categoryId ? 'Kategori diperbarui' : 'Kategori ditambahkan');
        $this->redirectRoute('admin.categories.index');
    }

    public function delete(): void
    {
        // Soft delete: the category disappears everywhere; products keep their other categories.
        ProductCategory::findOrFail($this->categoryId)->delete();

        session()->flash('status', 'Kategori dihapus');
        $this->redirectRoute('admin.categories.index');
    }

    public function with(): array
    {
        return [
            'productCount' => $this->categoryId ? ProductCategory::findOrFail($this->categoryId)->products()->count() : 0,
        ];
    }
};
?>

<div class="mx-auto max-w-xl">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.categories.index') }}" class="btn-icon -ml-2" aria-label="Kembali"><x-icon name="arrow-left" /></a>
        <h1 class="page-title">{{ $categoryId ? 'Ubah kategori' : 'Tambah kategori' }}</h1>
    </div>

    <form wire:submit="save" class="card mt-4 space-y-4 p-4 sm:p-6" novalidate>
        <div>
            <label for="name" class="label">Nama kategori</label>
            <input id="name" type="text" wire:model="name" class="input" placeholder="mis. Coffee-Based Drinks" autofocus>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        @if ($categoryId)
            <a href="{{ route('admin.products.index', ['kategori' => $categoryId, 'status' => 'semua']) }}" class="flex items-center justify-between rounded-xl bg-cream px-3 py-2.5 text-sm text-espresso-800">
                <span>{{ $productCount }} produk di kategori ini</span>
                <x-icon name="chevron-right" class="size-4 text-roast" />
            </a>
        @endif

        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-between">
            @if ($categoryId)
                <button type="button" wire:click="delete"
                    wire:confirm="Hapus kategori ini?{{ $productCount ? ' '.$productCount.' produk akan dilepas dari kategori ini (produknya tidak dihapus).' : '' }}"
                    class="btn btn-danger"><x-icon name="trash" class="size-4" /> Hapus</button>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary h-12 sm:h-11" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Simpan</button>
        </div>
    </form>
</div>
