<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use Livewire\Livewire;

class ProductCategoryTest extends PosTestCase
{
    public function test_admin_creates_renames_and_reorders_categories(): void
    {
        $this->actingAs($this->admin());
        $snacks = ProductCategory::create(['name' => 'Snacks', 'sort_order' => 1]);

        Livewire::test('pages::admin.categories.form')->set('name', 'Boba Series')->call('save')->assertHasNoErrors();
        $boba = ProductCategory::where('name', 'Boba Series')->sole();
        $this->assertSame(2, $boba->sort_order); // new categories go last

        Livewire::test('pages::admin.categories.form')->set('name', 'Snacks')->call('save')->assertHasErrors('name');

        Livewire::test('pages::admin.categories.index')->call('move', $boba->id, -1);
        $this->assertSame(['Boba Series', 'Snacks'], ProductCategory::ordered()->pluck('name')->all());

        Livewire::test('pages::admin.categories.form', ['category' => $snacks])->set('name', 'Camilan')->call('save')->assertHasNoErrors();
        $this->assertSame('Camilan', $snacks->fresh()->name);
    }

    public function test_deleting_a_category_keeps_its_products(): void
    {
        $this->actingAs($this->admin());
        $snacks = ProductCategory::create(['name' => 'Snacks']);
        $product = $this->product('Roti Keto', 18000);
        $product->categories()->attach($snacks);

        Livewire::test('pages::admin.categories.form', ['category' => $snacks])->call('delete')->assertRedirect(route('admin.categories.index'));

        $this->assertSoftDeleted($snacks);
        $this->assertModelExists($product);
        $this->assertCount(0, $product->fresh()->categories);
    }

    public function test_order_screen_chips_follow_category_order(): void
    {
        $this->actingAs($this->staff($this->pamulang));
        $snacks = ProductCategory::create(['name' => 'Snacks', 'sort_order' => 1]);
        $coffee = ProductCategory::create(['name' => 'Coffee-Based Drinks', 'sort_order' => 2]);
        ProductCategory::create(['name' => 'Kosong', 'sort_order' => 3]); // no products: no chip
        $boba = ProductCategory::create(['name' => 'Boba Series', 'sort_order' => 4]);
        $this->product('Kopi Susu Boba', 25000)->categories()->attach([$coffee->id, $boba->id]);
        $this->product('Roti Keto', 18000)->categories()->attach($snacks);
        $this->product('Air Mineral', 5000); // no category: "Lainnya"

        Livewire::test('pages::transactions.form')
            ->assertViewHas('categories', fn ($c) => $c->all() === ['Snacks', 'Coffee-Based Drinks', 'Boba Series', 'Lainnya'])
            ->assertViewHas('products', fn ($p) => $p->firstWhere('name', 'Kopi Susu Boba')['categories']->all() === ['Coffee-Based Drinks', 'Boba Series']);
    }

    public function test_staff_cannot_manage_categories(): void
    {
        $this->actingAs($this->staff($this->pamulang))->get(route('admin.categories.index'))->assertForbidden();
    }
}
