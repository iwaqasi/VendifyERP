<?php

namespace Tests\Feature\Api\V1;

use App\Transaction;
use App\TransactionPayment;
use App\VariationLocationDetails;
use Tests\TestCase;
use Tests\Concerns\CreatesPosWorld;

class SellControllerTest extends TestCase
{
    use CreatesPosWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosWorld();
        $this->licensed();
    }

    /** @test */
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/v1/sells', $this->salePayload())
            ->assertStatus(401);
    }

    /** @test */
    public function sale_is_priced_from_the_catalogue_not_the_client(): void
    {
        // Client tries to buy a 100-priced item for 1.
        $payload = $this->salePayload([
            'products' => [[
                'product_id' => $this->posData['product']['product']->id,
                'variation_id' => $this->posData['product']['variation']->id,
                'quantity' => 2,
                'unit_price' => 1,
            ]],
            'payments' => [['amount' => 200, 'method' => 'cash']],
        ]);

        $response = $this->postSale($payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $transaction = Transaction::findOrFail($response->json('data.id'));

        // Server price (100 x 2) wins over the client's forged price.
        $this->assertEquals(200, (float) $transaction->final_total);
        $this->assertEquals('paid', $transaction->payment_status);

        // The POS app is told which product prices were overridden.
        $this->assertEquals(
            [$this->posData['product']['product']->id],
            $response->json('data.price_overridden_product_ids')
        );
    }

    /** @test */
    public function sale_rejects_products_from_another_tenant(): void
    {
        $foreign = $this->createForeignProduct();

        $payload = $this->salePayload([
            'products' => [[
                'product_id' => $foreign['product']->id,
                'variation_id' => $foreign['variation']->id,
                'quantity' => 1,
            ]],
        ]);

        $this->postSale($payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEquals(0, Transaction::count());
    }

    /** @test */
    public function retried_offline_sales_are_idempotent(): void
    {
        $payload = $this->salePayload(['local_transaction_id' => 'offline-abc-123']);

        $first = $this->postSale($payload)->assertStatus(201);
        $second = $this->postSale($payload)->assertOk();

        // Same transaction returned, never duplicated.
        $this->assertEquals($first->json('data.id'), $second->json('data.id'));
        $this->assertEquals(1, Transaction::where('local_transaction_id', 'offline-abc-123')->count());

        // Stock decremented exactly once (100 - 2).
        $stock = VariationLocationDetails
            ::where('variation_id', $this->posData['product']['variation']->id)
            ->where('location_id', $this->posLocation->id)
            ->value('qty_available');
        $this->assertEquals(98, (float) $stock);
    }

    /** @test */
    public function invoice_numbers_come_from_the_invoice_scheme(): void
    {
        $first = $this->postSale($this->salePayload())->assertStatus(201);
        $second = $this->postSale($this->salePayload())->assertStatus(201);

        $invoice1 = $first->json('data.invoice_no') ?? $first->json('data.invoice_number');
        $invoice2 = $second->json('data.invoice_no') ?? $second->json('data.invoice_number');

        $this->assertNotEmpty($invoice1);
        $this->assertStringStartsWith('INV-', $invoice1);
        $this->assertNotEquals($invoice1, $invoice2, 'Each sale must get a unique scheme-based invoice number.');
    }

    /** @test */
    public function insufficient_stock_is_rejected(): void
    {
        $payload = $this->salePayload([
            'products' => [[
                'product_id' => $this->posData['product']['product']->id,
                'variation_id' => $this->posData['product']['variation']->id,
                'quantity' => 1000,
            ]],
        ]);

        $this->postSale($payload)
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertEquals(0, Transaction::count());
    }

    /** @test */
    public function tax_rate_amount_is_applied_to_the_total(): void
    {
        // 2 x 100 with 10% tax = 220.
        $payload = $this->salePayload([
            'products' => [[
                'product_id' => $this->posData['product']['product']->id,
                'variation_id' => $this->posData['product']['variation']->id,
                'quantity' => 2,
                'tax_id' => $this->posData['taxRate']->id,
            ]],
            'payments' => [['amount' => 220, 'method' => 'cash']],
        ]);

        $response = $this->postSale($payload)->assertStatus(201);

        $transaction = Transaction::findOrFail($response->json('data.id'));
        $this->assertEquals(220, (float) $transaction->final_total);
        $this->assertEquals(20, round((float) $transaction->tax_amount, 4));
    }

    /** @test */
    public function inclusive_tax_extracts_tax_from_the_line_total(): void
    {
        // Product with tax_type = 'inclusive' and sell_price_inc_tax = 110.
        // 2 x 110 = 220 inclusive. Tax is EXTRACTED, not added:
        //   base = 220 / 1.10 = 200, tax = 20.
        $inclProduct = $this->createPosProduct([
            'tax_type' => 'inclusive',
        ], [
            'variation' => [
                'default_sell_price' => 100,
                'sell_price_inc_tax' => 110, // 100 + 10% tax
            ],
        ]);

        $payload = $this->salePayload([
            'products' => [[
                'product_id' => $inclProduct['product']->id,
                'variation_id' => $inclProduct['variation']->id,
                'quantity' => 2,
                'tax_id' => $this->posData['taxRate']->id,
            ]],
            'payments' => [['amount' => 220, 'method' => 'cash']],
        ]);

        $response = $this->postSale($payload)->assertStatus(201);

        $transaction = Transaction::findOrFail($response->json('data.id'));
        // Total is 220 (server price 110 x 2), tax extracted is 20.
        $this->assertEquals(220, (float) $transaction->final_total);
        $this->assertEquals(20, round((float) $transaction->tax_amount, 4));
        // base_before_tax should be 200, not 220.
        $this->assertEquals(200, round((float) $transaction->total_before_tax, 4));
    }

    /** @test */
    public function payment_rows_are_created_for_the_tenant(): void
    {
        $response = $this->postSale($this->salePayload())->assertStatus(201);

        $payment = TransactionPayment::where('transaction_id', $response->json('data.id'))->first();

        $this->assertNotNull($payment);
        $this->assertEquals(200, (float) $payment->amount);
        $this->assertEquals('cash', $payment->method);
        $this->assertEquals($this->posBusiness->id, $payment->business_id);
    }

    /**
     * Build a product belonging to a different business.
     */
    private function createForeignProduct(): array
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

        $product = \App\Product::create([
            'name' => 'Foreign Product', 'business_id' => $business->id,
            'type' => 'single', 'sku' => 'FRN-'.uniqid(), 'tax_type' => 'exclusive',
            'enable_stock' => 1, 'created_by' => $owner->id,
        ]);

        $pv = \App\ProductVariation::create(['name' => 'DUMMY', 'product_id' => $product->id]);

        $variation = \App\Variation::create([
            'name' => 'DUMMY', 'product_id' => $product->id,
            'product_variation_id' => $pv->id, 'sub_sku' => 'FRN-'.uniqid(),
            'default_purchase_price' => 50, 'dpp_inc_tax' => 50, 'profit_percent' => 50,
            'default_sell_price' => 100, 'sell_price_inc_tax' => 100,
        ]);

        return compact('product', 'variation');
    }
}
