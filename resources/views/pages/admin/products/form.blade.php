<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ProductImage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Produk')] class extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $productId = null;

    public string $name = '';
    public string $price = '';
    /** @var array<int> */
    public array $categoryIds = [];
    public bool $isActive = true;
    public $photo = null;

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->productId = $product->id;
            $this->name = $product->name;
            $this->price = (string) $product->price;
            $this->categoryIds = $product->categories()->pluck('product_categories.id')->all();
            $this->isActive = $product->is_active;
        }
    }

    public function updatedPrice(): void
    {
        $this->price = preg_replace('/\D/', '', $this->price);
    }

    public function save(): void
    {
        $this->price = preg_replace('/\D/', '', $this->price);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'categoryIds' => ['array'],
            'categoryIds.*' => ['integer', 'exists:product_categories,id'],
            'isActive' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'name.required' => 'Nama produk wajib diisi.',
            'price.required' => 'Harga wajib diisi.',
            'price.integer' => 'Harga harus berupa angka.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 8 MB.',
        ]);

        $product = $this->productId ? Product::findOrFail($this->productId) : new Product;
        $product->fill([
            'name' => $data['name'],
            'price' => (int) $data['price'],
            'is_active' => $data['isActive'],
        ]);

        // Old photos are kept on disk: past data may still reference them.
        if ($this->photo) {
            $product->image = ProductImage::store($this->photo);
        }

        $product->save();
        $product->categories()->sync($data['categoryIds']);

        session()->flash('status', $this->productId ? 'Produk diperbarui' : 'Produk ditambahkan');
        $this->redirectRoute('admin.products.index');
    }

    public function delete(): void
    {
        // Soft delete: hidden from the menu and admin lists, but past orders,
        // receipts and reports still show it.
        Product::findOrFail($this->productId)->delete();
        session()->flash('status', 'Produk dihapus');
        $this->redirectRoute('admin.products.index');
    }

    public function with(): array
    {
        $product = $this->productId ? Product::find($this->productId) : null;
        $categories = ProductCategory::ordered()->get(['id', 'name']);

        return [
            'currentImage' => $product?->imageUrl() ?? asset(Product::DEFAULT_IMAGE),
            'categories' => $categories,
        ];
    }
};
?>

<div class="mx-auto max-w-xl">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.products.index') }}" class="btn-icon -ml-2" aria-label="Kembali"><x-icon name="arrow-left" /></a>
        <h1 class="page-title">{{ $productId ? 'Ubah produk' : 'Tambah produk' }}</h1>
    </div>

    <form wire:submit="save" class="card mt-4 space-y-4 p-4 sm:p-6" novalidate>
        <div class="flex items-center gap-4">
            <div class="relative size-28 shrink-0 overflow-hidden rounded-2xl bg-cream ring-1 ring-latte">
                <img src="{{ $photo && method_exists($photo, 'isPreviewable') && $photo->isPreviewable() ? $photo->temporaryUrl() : $currentImage }}" alt="" class="size-full object-cover">
                <div wire:loading.flex wire:target="photo" class="absolute inset-0 items-center justify-center bg-foam/80 text-xs text-roast">Mengunggah…</div>
            </div>
            <div class="min-w-0">
                <label class="btn btn-secondary cursor-pointer">
                    <x-icon name="image" class="size-4" /> {{ $productId ? 'Ganti foto' : 'Pilih foto' }}
                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
                <p class="mt-1.5 text-xs text-roast">Dipotong otomatis menjadi persegi 300×300.</p>
                @error('photo') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="name" class="label">Nama produk</label>
            <input id="name" type="text" wire:model="name" class="input">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="price" class="label">Harga (Rp)</label>
            <input id="price" type="text" inputmode="numeric" wire:model.blur="price" class="input tabular-nums" placeholder="0">
            @error('price') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="label">Kategori <span class="font-normal text-roast">(boleh lebih dari satu)</span></legend>
            <div class="flex flex-wrap gap-2">
                @forelse ($categories as $c)
                    <label wire:key="cat-{{ $c->id }}" @class([
                        'inline-flex h-10 cursor-pointer items-center gap-1.5 rounded-full px-3.5 text-sm font-medium ring-1 transition select-none',
                        'bg-espresso text-foam ring-espresso' => in_array($c->id, $categoryIds),
                        'bg-white text-espresso-800 ring-latte hover:bg-cream' => ! in_array($c->id, $categoryIds),
                    ])>
                        <input type="checkbox" wire:model.live="categoryIds" value="{{ $c->id }}" class="sr-only">
                        @if (in_array($c->id, $categoryIds))<x-icon name="check" class="size-4" />@endif
                        {{ $c->name }}
                    </label>
                @empty
                    <p class="text-sm text-roast">Belum ada kategori. <a href="{{ route('admin.categories.create') }}" class="font-medium text-caramel-700 underline">Tambah kategori</a></p>
                @endforelse
            </div>
            @error('categoryIds.*') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-xl bg-cream px-3 py-2">
            <span>
                <span class="block text-sm font-medium text-espresso-900">Aktif</span>
                <span class="block text-xs text-roast">Produk nonaktif tidak muncul di halaman transaksi.</span>
            </span>
            <input type="checkbox" wire:model="isActive" class="size-5 rounded-md accent-espresso">
        </label>

        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-between">
            @if ($productId)
                <button type="button" wire:click="delete" wire:confirm="Hapus produk ini? Produk hilang dari menu, tapi tetap tercatat di riwayat transaksi dan laporan." class="btn btn-danger"><x-icon name="trash" class="size-4" /> Hapus</button>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary h-12 sm:h-11" wire:loading.attr="disabled" wire:target="save,photo"><x-icon name="check" class="size-4" /> Simpan</button>
        </div>
        @error('delete') <p class="rounded-xl bg-cherry/10 px-3 py-2 text-sm text-cherry">{{ $message }}</p> @enderror
    </form>
</div>
