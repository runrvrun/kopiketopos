<?php

use App\Models\Store;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Toko')] class extends Component
{
    public function with(): array
    {
        return ['stores' => Store::withCount(['transactions', 'users'])->orderByDesc('is_active')->orderBy('name')->get()];
    }
};
?>

<div>
    <div class="flex items-center justify-between gap-3">
        <h1 class="page-title">Toko</h1>
        <a href="{{ route('admin.stores.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah</a>
    </div>

    <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        @forelse ($stores as $store)
            <li class="min-w-0">
                <a href="{{ route('admin.stores.edit', $store) }}" class="card flex items-start gap-3 p-4 transition hover:ring-caramel-300">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-caramel-100 text-caramel-700"><x-icon name="store" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-semibold text-espresso-900">
                            {{ $store->name }}
                            @unless ($store->is_active)<span class="chip bg-cream text-roast ring-1 ring-latte">Nonaktif</span>@endunless
                        </p>
                        <p class="line-clamp-2 text-xs text-roast">{{ $store->address ?: 'Alamat belum diisi' }}</p>
                        <p class="mt-1.5 text-xs text-roast">{{ number_format($store->transactions_count, 0, ',', '.') }} transaksi · {{ $store->users_count }} staf</p>
                    </div>
                    <x-icon name="chevron-right" class="mt-2 size-4 text-latte" />
                </a>
            </li>
        @empty
            <li class="card p-6 text-center text-sm text-roast">Belum ada toko.</li>
        @endforelse
    </ul>
</div>
