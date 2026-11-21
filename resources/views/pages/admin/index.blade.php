<?php

use App\Models\Customer;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin')] class extends Component
{
    public function with(): array
    {
        return [
            'sections' => [
                ['label' => 'Analisa Penjualan', 'hint' => 'Tren, produk & saran', 'route' => 'admin.analytics', 'icon' => 'chart'],
                ['label' => 'Pelanggan', 'hint' => number_format(Customer::count(), 0, ',', '.').' pelanggan', 'route' => 'admin.customers.index', 'icon' => 'user'],
                ['label' => 'Pengguna', 'hint' => User::count().' akun', 'route' => 'admin.users.index', 'icon' => 'users'],
                ['label' => 'Toko', 'hint' => Store::count().' toko', 'route' => 'admin.stores.index', 'icon' => 'store'],
                ['label' => 'Produk', 'hint' => Product::active()->count().' aktif', 'route' => 'admin.products.index', 'icon' => 'coffee'],
                ['label' => 'Kategori', 'hint' => ProductCategory::count().' kategori', 'route' => 'admin.categories.index', 'icon' => 'tag'],
                ['label' => 'Rekening', 'hint' => PaymentAccount::value('bank') ?? 'Belum diatur', 'route' => 'admin.accounts.index', 'icon' => 'bank'],
                ['label' => 'Unduh Riwayat', 'hint' => 'Excel per bulan', 'route' => 'admin.history', 'icon' => 'download'],
            ],
        ];
    }
};
?>

<div>
    <h1 class="page-title">Admin</h1>
    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($sections as $s)
            <a href="{{ route($s['route']) }}" class="card flex flex-col gap-3 p-4 transition hover:ring-caramel-300 active:scale-[0.98]">
                <span class="flex size-11 items-center justify-center rounded-xl bg-caramel-100 text-caramel-700"><x-icon :name="$s['icon']" class="size-6" /></span>
                <span>
                    <span class="block font-semibold text-espresso-900">{{ $s['label'] }}</span>
                    <span class="block text-xs text-roast">{{ $s['hint'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</div>
