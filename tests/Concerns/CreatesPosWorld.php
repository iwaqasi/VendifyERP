<?php

namespace Tests\Concerns;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\InvoiceLayout;
use App\InvoiceScheme;
use App\Product;
use App\ProductVariation;
use App\TaxRate;
use App\Unit;
use App\User;
use App\Variation;
use App\VariationLocationDetails;
use DB;
use Illuminate\Support\Facades\Hash;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds a complete, realistic tenant "world" for API feature tests:
 * currency ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢ user ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢ business ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢ invoice layout/scheme ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢ location ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢
 * unit/category ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢ products with variations + per-location stock ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢
 * customer ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢ tax rate ط£آ¢أ¢â‚¬آ أ¢â‚¬â„¢ (optionally) package + active subscription.
 *
 * All models use $guarded = ['id'], so direct creation is intentional
 * here; only columns verified against the real migrations are supplied.
 */
trait CreatesPosWorld
{
    protected User $posUser;
    protected Business $posBusiness;
    protected BusinessLocation $posLocation;
    protected array $posData = [];

    protected function setUpPosWorld(): void
    {
        $currency_id = DB::table('currencies')->insertGetId([
            'country' => 'Testland', 'currency' => 'Test Dollar', 'code' => 'TST',
            'symbol' => 'T$', 'thousand_separator' => ',', 'decimal_separator' => '.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->posUser = User::create([
            'surname' => 'Test',
            'first_name' => 'Cashier',
            'username' => 'casher_'.uniqid(),
            'email' => uniqid().'@test.local',
            'password' => Hash::make('password'),
        ]);

        $this->posBusiness = Business::create([
            'name' => 'Biz '.uniqid(),
            'owner_id' => $this->posUser->id,
            'currency_id' => $currency_id,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '{}',
        ]);
        $this->posUser->update(['business_id' => $this->posBusiness->id]);

        $layout = InvoiceLayout::create([
            'name' => 'Default', 'business_id' => $this->posBusiness->id, 'is_default' => 1,
        ]);
        $scheme = InvoiceScheme::create([
            'name' => 'Default', 'business_id' => $this->posBusiness->id,
            'scheme_type' => 'blank', 'prefix' => 'INV-', 'is_default' => 1,
        ]);

        $this->posLocation = BusinessLocation::create([
            'business_id' => $this->posBusiness->id,
            'name' => 'Main Street',
            'city' => 'Testville', 'state' => 'TS', 'country' => 'Testland', 'zip_code' => '00000',
            'invoice_layout_id' => $layout->id, 'invoice_scheme_id' => $scheme->id,
        ]);

        $unit = Unit::create([
            'business_id' => $this->posBusiness->id,
            'actual_name' => 'Pieces', 'short_name' => 'Pc', 'allow_decimal' => 0,
            'created_by' => $this->posUser->id,
        ]);
        $category = Category::create([
            'business_id' => $this->posBusiness->id,
            'name' => 'General', 'parent_id' => 0, 'created_by' => $this->posUser->id,
        ]);

        $customer = Contact::create([
            'business_id' => $this->posBusiness->id,
            'type' => 'customer', 'name' => 'Test Customer', 'mobile' => '1234567890',
            'created_by' => $this->posUser->id,
        ]);

        $taxRate = TaxRate::create([
            'business_id' => $this->posBusiness->id,
            'name' => 'VAT', 'amount' => 10, 'is_tax_group' => 0,
            'created_by' => $this->posUser->id,
        ]);

        // Spatie role so permission checks (edit_product_price) behave normally.
        $role = Role::create(['name' => 'Casher '.$this->posBusiness->id, 'business_id' => $this->posBusiness->id, 'guard_name' => 'web']);
        $permission = Permission::findOrCreate('edit_product_price', 'web');
        // Cashiers do NOT get edit_product_price by default — server prices must win.
        $this->posUser->assignRole($role);

        $this->posData = compact('unit', 'category', 'customer', 'taxRate');

        // Default catalogue item: price 100, stock 100 at the main location.
        $this->posData['product'] = $this->createPosProduct();
    }

    /**
     * Create a product with one variation and location stock.
     *
     * @return array{product: Product, variation: Variation}
     */
    protected function createPosProduct(array $overrides = []): array
    {
        $sku = 'SKU-'.uniqid();

        $product = Product::create(array_merge([
            'name' => 'Product '.uniqid(),
            'business_id' => $this->posBusiness->id,
            'type' => 'single',
            'sku' => $sku,
            'tax_type' => 'exclusive',
            'unit_id' => $this->posData['unit']->id ?? null,
            'category_id' => $this->posData['category']->id ?? null,
            'enable_stock' => 1,
            'created_by' => $this->posUser->id,
        ], $overrides));

        $productVariation = ProductVariation::create([
            'name' => 'DUMMY', 'product_id' => $product->id,
        ]);

        $variation = Variation::create(array_merge([
            'name' => 'DUMMY',
            'product_id' => $product->id,
            'product_variation_id' => $productVariation->id,
            'sub_sku' => $sku,
            'default_purchase_price' => 50,
            'dpp_inc_tax' => 50,
            'profit_percent' => 50,
            'default_sell_price' => 100,
            'sell_price_inc_tax' => 100,
        ], $overrides['variation'] ?? []));

        VariationLocationDetails::create([
            'product_id' => $product->id,
            'product_variation_id' => $productVariation->id,
            'variation_id' => $variation->id,
            'location_id' => $this->posLocation->id,
            'qty_available' => $overrides['stock'] ?? 100,
        ]);

        DB::table('product_locations')->insert([
            'product_id' => $product->id,
            'location_id' => $this->posLocation->id,
        ]);

        return compact('product', 'variation');
    }

    /**
     * Attach an approved subscription (with flutter module flags) to the tenant.
     */
    protected function licensed(bool $pos = true, bool $cms = true): Package
    {
        $package = Package::create([
            'name' => 'Plan '.uniqid(),
            'description' => 'Test plan',
            'location_count' => 0, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
            'interval' => 'months', 'interval_count' => 1, 'trial_days' => 0,
            'price' => 10, 'created_by' => $this->posUser->id, 'sort_order' => 0,
            'is_active' => 1, 'mark_package_as_popular' => 0,
            'flutter_pos' => $pos, 'flutter_cms' => $cms,
        ]);

        Subscription::create([
            'business_id' => $this->posBusiness->id,
            'package_id' => $package->id,
            'package_price' => 10,
            'package_details' => json_encode([]),
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'created_id' => $this->posUser->id,
            'status' => 'approved',
        ]);

        return $package;
    }

    protected function actingAsPosUser(): self
    {
        return $this->actingAs($this->posUser, 'api');
    }

    /**
     * A valid POS sale payload: 2 x default product (server price 100),
     * fully paid in cash.
     */
    protected function salePayload(array $overrides = []): array
    {
        return array_merge([
            'location_id' => $this->posLocation->id,
            'contact_id' => $this->posData['customer']->id,
            'products' => [[
                'product_id' => $this->posData['product']['product']->id,
                'variation_id' => $this->posData['product']['variation']->id,
                'quantity' => 2,
                'unit_price' => 100,
            ]],
            'payments' => [[
                'amount' => 200,
                'method' => 'cash',
            ]],
        ], $overrides);
    }

    protected function postSale(array $payload)
    {
        return $this->actingAsPosUser()->postJson('/api/v1/sells', $payload);
    }
}
