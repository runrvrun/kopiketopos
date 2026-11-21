<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

class AdminTest extends PosTestCase
{
    public function test_staff_cannot_open_admin_pages_or_see_the_menu(): void
    {
        $this->actingAs($this->staff($this->pamulang));

        $this->get('/')->assertOk()->assertDontSee('Unduh Riwayat');
        foreach (['admin.index', 'admin.users.index', 'admin.stores.index', 'admin.products.index', 'admin.history'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('admin.history.download', ['bulan' => now()->format('Ym')]))->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $this->actingAs($admin);

        $this->get('/')->assertOk()->assertSee('Unduh Riwayat');
        foreach ([
            route('admin.index'), route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $admin),
            route('admin.stores.index'), route('admin.stores.create'), route('admin.stores.edit', $this->pamulang),
            route('admin.products.index'), route('admin.products.create'), route('admin.products.edit', $product),
            route('admin.history'), route('admin.categories.index'), route('admin.categories.create'),
            route('admin.categories.edit', ProductCategory::create(['name' => 'Snacks'])),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_creates_staff_with_store_access(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.users.form')
            ->set('name', 'Gandaria')
            ->set('email', 'gandaria@example.com')
            ->set('password', 'rahasia123')
            ->set('role', 'user')
            ->set('storeIds', [$this->senopati->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'gandaria@example.com')->sole();
        $this->assertSame([$this->senopati->id], $user->stores->pluck('id')->all());

        // Promoting to admin clears explicit store rows (admins access all).
        Livewire::test('pages::admin.users.form', ['user' => $user])->set('role', 'admin')->call('save')->assertHasNoErrors();
        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertCount(0, $user->fresh()->stores);
    }

    public function test_admin_cannot_delete_self_or_users_with_transactions(): void
    {
        $admin = $this->admin();
        $staff = $this->staff($this->pamulang);
        Transaction::create(['store_id' => $this->pamulang->id, 'user_id' => $staff->id, 'total_item' => 1, 'total_price' => 1000]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.users.form', ['user' => $admin])->call('delete')->assertHasErrors('delete');
        Livewire::test('pages::admin.users.form', ['user' => $staff])->call('delete')->assertHasErrors('delete');
        $this->assertSame(2, User::count());
    }

    public function test_store_crud_and_soft_delete(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::admin.stores.form')->set('name', 'Gandaria City')->set('phone', '0812')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('stores', ['name' => 'Gandaria City', 'phone' => '0812']);

        Livewire::test('pages::admin.stores.form', ['store' => $this->senopati])->call('delete')->assertRedirect(route('admin.stores.index'));
        $this->assertSoftDeleted($this->senopati);
    }

    public function test_product_create_with_photo_deactivate_and_soft_delete(): void
    {
        $public = sys_get_temp_dir().'/kopiketo-public-'.uniqid();
        mkdir($public);
        $this->app->usePublicPath($public);
        $this->actingAs($this->admin());
        $coffee = ProductCategory::create(['name' => 'Coffee-Based Drinks', 'sort_order' => 1]);
        $boba = ProductCategory::create(['name' => 'Boba Series', 'sort_order' => 2]);

        Livewire::test('pages::admin.products.form')
            ->set('name', 'Es Kopi Keto')
            ->set('price', 'Rp 28.000')
            ->set('categoryIds', [$coffee->id, $boba->id])
            ->set('photo', UploadedFile::fake()->image('kopi.png', 800, 600))
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Es Kopi Keto')->sole();
        $this->assertSame(28000, $product->price);
        $this->assertEqualsCanonicalizing([$coffee->id, $boba->id], $product->categories->pluck('id')->all());
        $this->assertStringStartsWith('uploads/product/', $product->image);
        [$w, $h] = getimagesize($public.'/'.$product->image);
        $this->assertSame([300, 300], [$w, $h]);

        $trx = Transaction::create(['store_id' => $this->pamulang->id, 'total_item' => 1, 'total_price' => 28000]);
        $trx->lines()->create(['product_id' => $product->id, 'amount' => 1, 'price' => 28000]);

        Livewire::test('pages::admin.products.form', ['product' => $product])->set('isActive', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($product->fresh()->is_active);

        Livewire::test('pages::admin.products.form', ['product' => $product])->call('delete')->assertRedirect(route('admin.products.index'));
        $this->assertSoftDeleted($product);
        $this->assertDatabaseCount('product_transaction', 1);
    }
}
