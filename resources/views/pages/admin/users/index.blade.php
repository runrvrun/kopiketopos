<?php

use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengguna')] class extends Component
{
    public function with(): array
    {
        return [
            'users' => User::with('stores:id,name')->withCount('transactions')->orderByRaw("role = 'admin' DESC")->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-between gap-3">
        <h1 class="page-title">Pengguna</h1>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah</a>
    </div>

    <ul class="card mt-4 divide-y divide-latte/70 overflow-hidden">
        @foreach ($users as $u)
            <li>
                <a href="{{ route('admin.users.edit', $u) }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-cream">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-caramel-100 font-semibold text-caramel-700">{{ $u->initials() }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-medium text-espresso-900">
                            <span class="truncate">{{ $u->name }}</span>
                            @if ($u->id === auth()->id())<span class="chip bg-cream text-roast">Anda</span>@endif
                        </p>
                        <p class="truncate text-xs text-roast">{{ $u->email }}</p>
                        <p class="mt-1 flex flex-wrap gap-1">
                            @if ($u->isAdmin())
                                <span class="chip bg-espresso text-foam">Admin · semua toko</span>
                            @else
                                @forelse ($u->stores as $store)
                                    <span class="chip bg-cream text-espresso-800 ring-1 ring-latte">{{ $store->name }}</span>
                                @empty
                                    <span class="chip bg-cherry/10 text-cherry">Belum ada akses toko</span>
                                @endforelse
                            @endif
                        </p>
                    </div>
                    <x-icon name="chevron-right" class="size-4 text-latte" />
                </a>
            </li>
        @endforeach
    </ul>
</div>
