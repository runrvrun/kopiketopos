<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class PosTestCase extends TestCase
{
    use RefreshDatabase;

    protected Store $pamulang;

    protected Store $senopati;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pamulang = Store::create(['name' => 'Pamulang', 'address' => 'Jl. Alam Asri 1']);
        $this->senopati = Store::create(['name' => 'Senopati']);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Staff user with access to the given stores. */
    protected function staff(Store ...$stores): User
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $user->stores()->sync(collect($stores)->pluck('id'));

        return $user;
    }

    protected function product(string $name = 'Kopi Susu', int $price = 25000, array $extra = []): Product
    {
        return Product::create(['name' => $name, 'price' => $price] + $extra);
    }
}
