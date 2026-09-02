<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Tests\Concerns\CreatesPosWorld;

class ProductPaginationTest extends TestCase
{
    use CreatesPosWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosWorld();
        $this->licensed();

        // 30 sellable products for OUR tenant (default product already exists).
        for ($i = 0; $i < 29; $i++) {
            $this->createPosProduct(['name' => 'AAAA_'.$i.'_'.uniqid()]);
        }

        // 5 products for a DIFFERENT tenant must never leak in.
        $foreign = $this->createForeignBusinessWithProduct();
    }

    /** @test */
    public function products_are_paginated_at_the_database_level(): void
    {
        $response = $this->actingAsPosUser()
            ->getJson('/api/v1/products?per_page=10&page=2')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(30, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(3, $response->json('meta.last_page'));
        $this->assertNotNull($response->json('links.next'));
        $this->assertNotNull($response->json('links.prev'));
    }

    /** @test */
    public function other_tenants_products_never_appear(): void
    {
        $response = $this->actingAsPosUser()
            ->getJson('/api/v1/products?per_page=100')
            ->assertOk();

        $this->assertEquals(30, $response->json('meta.total'));

        foreach ($response->json('data') as $product) {
            $this->assertStringStartsNotWith('FOREIGN_', $product['name'] ?? '');
        }
    }

    /** @test */
    public function per_page_is_capped_to_protect_the_server(): void
    {
        $response = $this->actingAsPosUser()
            ->getJson('/api/v1/products?per_page=99999')
            ->assertOk();

        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }

    /**
     * A second tenant with its own product.
     */
    private function createForeignBusinessWithProduct(): array
    {
        $currency_id = \DB::table('currencies')->insertGetId([
            'country' => 'Otherland', 'currency' => 'Other Dollar', 'code' => 'OTH',
            'symbol' => 'O$', 'thousand_separator' => ',', 'decimal_separator' => '.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $owner = \App\User::create([
            'surname' => 'Other', 'first_name' => 'Owner',
            'username' => 'other_'.uniqid(), 'email' => uniqid().'@other.local',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        $business = \App\Business::create([
            'name' => 'Rival '.uniqid(), 'owner_id' => $owner->id,
            'currency_id' => $currency_id, 'stop_selling_before' => 0,
            'weighing_scale_setting' => '{}',
        ]);

        for ($i = 0; $i < 5; $i++) {
            \App\Product::create([
                'name' => 'FOREIGN_'.$i, 'business_id' => $business->id,
                'type' => 'single', 'sku' => 'FRN-'.uniqid(), 'tax_type' => 'exclusive',
                'enable_stock' => 1, 'created_by' => $owner->id,
            ]);
        }

        return compact('business', 'owner');
    }
}