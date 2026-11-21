<?php

use App\Models\Transaction;
use App\Support\StoreContext;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Transaksi')] class extends Component
{
    use WithPagination;

    #[Url(as: 'tab', except: 'belum-lunas')]
    public string $tab = 'belum-lunas';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $context = StoreContext::for();
        $storeIds = $context->storeIds();
        $ongoing = $this->tab !== 'lunas';

        $search = trim($this->search);
        $query = Transaction::query()
            ->with('store:id,name')
            ->forStores($storeIds)
            ->when($ongoing, fn ($q) => $q->ongoing(), fn ($q) => $q->paid())
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('customer', 'like', "%{$search}%");
                    if (ctype_digit($search)) {
                        $q->orWhere('key', 'like', "%{$search}%")->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->latest('id');

        return [
            'transactions' => $query->paginate(30),
            'ongoingCount' => Transaction::ongoing()->forStores($storeIds)->count(),
            'showStore' => count($storeIds) > 1,
        ];
    }

    public function dateLabel(\Carbon\CarbonInterface $date): string
    {
        return match (true) {
            $date->isToday() => 'Hari ini',
            $date->isYesterday() => 'Kemarin',
            default => $date->translatedFormat('l, j F Y'),
        };
    }
};
?>

<div>
    <h1 class="page-title">Transaksi</h1>

    {{-- Tabs --}}
    <div class="mt-4 grid grid-cols-2 rounded-2xl bg-latte/50 p-1 sm:inline-grid sm:w-80" role="tablist">
        @foreach (['belum-lunas' => 'Belum lunas', 'lunas' => 'Lunas'] as $value => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $value }}')" aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                @class([
                    'flex h-10 items-center justify-center gap-2 rounded-xl text-sm font-semibold transition',
                    'bg-foam text-espresso-900 shadow-sm' => $tab === $value,
                    'text-roast hover:text-espresso' => $tab !== $value,
                ])>
                {{ $label }}
                @if ($value === 'belum-lunas' && $ongoingCount > 0)
                    <span class="rounded-full bg-caramel px-1.5 text-xs leading-5 text-espresso-950">{{ $ongoingCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Search --}}
    <label class="relative mt-3 block sm:max-w-sm">
        <span class="sr-only">Cari transaksi</span>
        <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-roast" />
        <input type="search" wire:model.live.debounce.300ms="search" class="input pl-10" placeholder="Cari nama pelanggan atau no. invoice">
    </label>

    <div class="mt-4 space-y-5" wire:loading.class="opacity-60">
        @forelse ($transactions->groupBy(fn ($t) => $t->created_at->toDateString()) as $date => $group)
            <section>
                <h2 class="mb-2 flex items-baseline justify-between px-1 text-xs font-semibold uppercase tracking-wider text-roast">
                    <span>{{ $this->dateLabel($group->first()->created_at) }}</span>
                    <span class="normal-case tracking-normal tabular-nums">{{ rupiah($group->sum(fn ($t) => $t->netTotal())) }}</span>
                </h2>
                <ul class="card divide-y divide-latte/70 overflow-hidden">
                    @foreach ($group as $trx)
                        <li wire:key="trx-{{ $trx->id }}">
                            <a href="{{ route('transactions.show', $trx) }}"
                               class="flex items-center gap-3 px-4 py-3 transition hover:bg-cream active:bg-latte/40">
                                <div @class([
                                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                                    'bg-caramel-100 text-caramel-700' => ! $trx->isPaid(),
                                    'bg-cream text-roast' => $trx->isPaid(),
                                ])>
                                    <x-icon :name="$trx->isPaid() ? 'receipt' : 'clock'" class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-espresso-900">{{ $trx->customer ?: 'Tanpa nama' }}</p>
                                    <p class="truncate text-xs text-roast">
                                        {{ $trx->created_at->format('H:i') }} · {{ $trx->total_item }} item
                                        @if ($showStore && $trx->store) · {{ $trx->store->name }} @endif
                                        · {{ $trx->invoiceNumber() }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold tabular-nums text-espresso-900">{{ rupiah($trx->netTotal()) }}</p>
                                    @unless ($trx->isPaid())
                                        <p class="text-xs font-medium text-caramel-700">Belum lunas</p>
                                    @endunless
                                </div>
                                <x-icon name="chevron-right" class="size-4 text-latte" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="card flex flex-col items-center p-8 text-center">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-cream text-caramel-600"><x-icon name="receipt" class="size-6" /></span>
                <p class="mt-3 font-medium text-espresso-900">
                    {{ $search !== '' ? 'Tidak ada transaksi yang cocok.' : ($tab === 'lunas' ? 'Belum ada transaksi lunas.' : 'Semua transaksi sudah lunas.') }}
                </p>
                @if ($tab !== 'lunas' && $search === '')
                    <a href="{{ route('transactions.create') }}" class="btn btn-primary mt-4"><x-icon name="plus" class="size-4" /> Transaksi baru</a>
                @endif
            </div>
        @endforelse
    </div>

    @if ($transactions->hasPages())
        <nav class="mt-5 flex items-center justify-between gap-3" aria-label="Halaman">
            <button type="button" wire:click="previousPage" class="btn btn-secondary" @disabled($transactions->onFirstPage())>
                <x-icon name="arrow-left" class="size-4" /> Sebelumnya
            </button>
            <span class="text-sm text-roast tabular-nums">{{ $transactions->currentPage() }} / {{ $transactions->lastPage() }}</span>
            <button type="button" wire:click="nextPage" class="btn btn-secondary" @disabled(! $transactions->hasMorePages())>
                Berikutnya <x-icon name="chevron-right" class="size-4" />
            </button>
        </nav>
    @endif
</div>
