<?php

use App\Models\PaymentAccount;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rekening')] class extends Component
{
    public function with(): array
    {
        return ['accounts' => PaymentAccount::orderBy('id')->get()];
    }
};
?>

<div>
    <div class="flex items-center justify-between gap-3">
        <h1 class="page-title">Rekening</h1>
        {{-- Only one payment account is allowed --}}
        @if ($accounts->isEmpty())
            <a href="{{ route('admin.accounts.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah</a>
        @endif
    </div>
    <p class="mt-1 text-sm text-roast">Ditampilkan di struk yang belum lunas sebagai tujuan transfer. Ketuk rekening untuk mengubahnya.</p>

    <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        @forelse ($accounts as $account)
            <li class="min-w-0">
                <a href="{{ route('admin.accounts.edit', $account) }}" class="card flex items-center gap-3 p-4 transition hover:ring-caramel-300">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-caramel-100 text-caramel-700"><x-icon name="bank" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-roast">{{ $account->bank }}</p>
                        <p class="truncate font-mono text-base font-semibold tracking-wide text-espresso-900">{{ $account->account_number }}</p>
                        <p class="truncate text-sm text-roast">a.n. {{ $account->account_owner }}</p>
                    </div>
                    <x-icon name="chevron-right" class="size-4 text-latte" />
                </a>
            </li>
        @empty
            <li class="card p-6 text-center">
                <p class="font-medium text-espresso-900">Belum ada rekening.</p>
                <p class="mt-1 text-sm text-roast">Tambahkan rekening agar muncul di struk yang belum lunas.</p>
            </li>
        @endforelse
    </ul>
</div>
