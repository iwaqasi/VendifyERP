<?php

namespace App\Http\Controllers\Api\V1;

use App\Contact;
use App\Transaction;
use App\TransactionSellLine;
use App\TransactionPayment;
use App\BusinessLocation;
use App\Product;
use App\Variation;
use App\Util;
use App\Utils\TransactionUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellController extends BaseApiController
{
    protected $transactionUtil;

    public function __construct()
    {
        $this->transactionUtil = new TransactionUtil();
    }

    /**
     * List sales with filters
     *
     * @queryParam status string Filter: final, draft, quotation, cancelled. Example: final
     * @queryParam location_id int Filter by location. Example: 1
     * @queryParam contact_id int Filter by customer. Example: 1
     * @queryParam start_date string Start date (YYYY-MM-DD). Example: 2026-01-01
     * @queryParam end_date string End date (YYYY-MM-DD). Example: 2026-08-22
     * @queryParam per_page int Items per page. Example: 20
     */
    public function index(Request $request)
    {
        $business_id = $this->getBusinessId($request);
        $per_page = $request->input('per_page', 20);

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->with(['contact:id,name,mobile', 'location:id,name', 'payment_lines:id,method,amount,transaction_id']);

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by payment status
        if ($payment_status = $request->input('payment_status')) {
            $query->where('payment_status', $payment_status);
        }

        // Filter by location
        if ($location_id = $request->input('location_id')) {
            $query->where('location_id', $location_id);
        }

        // Filter by customer
        if ($contact_id = $request->input('contact_id')) {
            $query->where('contact_id', $contact_id);
        }

        // Search by customer name or phone
        if ($search = $request->input('search')) {
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        // Invoice number search
        if ($invoice_no = $request->input('invoice_no')) {
            $query->where('invoice_no', 'like', "%{$invoice_no}%");
        }

        // Date range
        if ($start_date = $request->input('start_date')) {
            $query->whereDate('transaction_date', '>=', $start_date);
        }
        if ($end_date = $request->input('end_date')) {
            $query->whereDate('transaction_date', '<=', $end_date);
        }

        $sells = $query->orderBy('transaction_date', 'desc')
            ->paginate($per_page);

        return $this->paginatedResponse($sells);
    }

    /**
     * Get single sale with line items
     */
    public function show(Request $request, $id)
    {
        $business_id = $this->getBusinessId($request);

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->with([
                'contact:id,name,mobile,email',                    'location:id,name',
                    'sell_lines' => function ($query) {
                        $query->with(['product:id,name,image']);
                    },
                    'payment_lines',
                    'service_staff:id,first_name,last_name',
            ])
            ->find($id);

        if (!$transaction) {
            return $this->errorResponse('Sale not found.', 404);
        }

        return $this->successResponse($transaction);
    }

    /**
     * Create a new sale (POS transaction)
     *
     * This is the main endpoint for the Flutter POS app.
     *
     * @bodyParam contact_id int Customer ID. Example: 1
     * @bodyParam location_id int required Business location. Example: 1
     * @bodyParam products array required Array of product lines. Each line: {product_id, variation_id, quantity, unit_price, discount, tax_id}
     * @bodyParam payments array required Array of payments. Each: {amount, method: "cash"|"card"|"other", reference}
     * @bodyParam discount_type string Discount type: fixed or percentage. Example: fixed
     * @bodyParam discount_amount float Discount amount. Example: 5.00
     * @bodyParam shipping_charges float Shipping charges. Example: 10.00
     * @bodyParam additional_notes string Notes. Example: Walk-in customer
     * @bodyParam sale_note string Sale note. Example: Express service
     * @bodyParam types_of_service_id int Service type (dine-in, takeaway). Example: 1
     * @bodyParam table_id int Table ID for dine-in. Example: 1
     */
    public function store(Request $request)
    {
        $business_id = $this->getBusinessId($request);
        $user_id = $request->user()->id;

        // NOTE: No request-body logging here. Sale payloads contain PII
        // (customers, orders, payments) and must never land in logs.

        $request->validate([
            'location_id' => 'required|integer',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer',
            'products.*.variation_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_price' => 'nullable|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.tax_id' => 'nullable|integer',
            'products.*.service_staff_id' => 'nullable|integer',
            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.method' => 'required|string',
            'contact_id' => 'nullable|integer',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_charges' => 'nullable|numeric|min:0',
            'types_of_service_id' => 'nullable|integer',
            'table_id' => 'nullable|integer',
            'local_transaction_id' => 'nullable|string|max:64',
        ]);

        DB::beginTransaction();

        try {
            // ============ IDEMPOTENCY: retried offline sales must not create duplicates ============
            if ($local_transaction_id = $request->input('local_transaction_id')) {
                $existing = Transaction::where('business_id', $business_id)
                    ->where('type', 'sell')
                    ->where('local_transaction_id', $local_transaction_id)
                    ->first();

                if ($existing) {
                    DB::rollBack();
                    $existing->load(['contact', 'location', 'sell_lines.product', 'payment_lines']);

                    return $this->successResponse($existing, 'Sale already synced (idempotent response)');
                }
            }

            // ============ TENANT OWNERSHIP VALIDATION (fail-closed, IDOR protection) ============
            $locationExists = BusinessLocation::where('business_id', $business_id)
                ->where('id', $request->location_id)
                ->exists();

            if (! $locationExists) {
                DB::rollBack();

                return $this->errorResponse('Location not found for this business.', 422);
            }

            $contact = null;
            if (! empty($request->contact_id)) {
                $contact = Contact::where('business_id', $business_id)
                    ->where('id', $request->contact_id)
                    ->first();

                if (! $contact) {
                    DB::rollBack();

                    return $this->errorResponse('Customer not found for this business.', 422);
                }
            }

            if (! empty($request->types_of_service_id)) {
                $tosExists = \App\TypesOfService::where('business_id', $business_id)
                    ->where('id', $request->types_of_service_id)
                    ->exists();

                if (! $tosExists) {
                    DB::rollBack();

                    return $this->errorResponse('Type of service not found for this business.', 422);
                }
            }

            if (! empty($request->table_id) && class_exists(\App\Restaurant\ResTable::class)) {
                $tableExists = \App\Restaurant\ResTable::where('business_id', $business_id)
                    ->where('id', $request->table_id)
                    ->exists();

                if (! $tableExists) {
                    DB::rollBack();

                    return $this->errorResponse('Table not found for this business.', 422);
                }
            }

            $canEditPrice = $request->user()->can('edit_product_price');

            // ============ RESOLVE PRODUCTS SERVER-SIDE ============
            // Every referenced entity is scoped to this business and unit
            // prices are re-derived from the catalogue unless the user has
            // explicit permission to edit prices.
            $resolvedLines = [];
            $priceOverrides = [];

            foreach ($request->products as $line) {
                $product = Product::where('business_id', $business_id)->find($line['product_id']);

                if (! $product) {
                    DB::rollBack();

                    return $this->errorResponse("Product not found for this business: {$line['product_id']}", 422);
                }

                $variation = Variation::where('product_id', $line['product_id'])
                    ->find($line['variation_id']);

                if (! $variation) {
                    DB::rollBack();

                    return $this->errorResponse("Variation not found for product: {$line['product_id']}", 422);
                }

                // Authoritative price: the catalogue already exposes the
                // tax-inclusive price (sell_price_inc_tax) to the POS UI.
                $serverUnitPrice = $variation->sell_price_inc_tax ?? $variation->default_sell_price;

                if ($serverUnitPrice === null) {
                    DB::rollBack();

                    return $this->errorResponse("No selling price configured for product: {$product->name}", 422);
                }

                $serverUnitPrice = (float) $serverUnitPrice;
                $clientUnitPrice = isset($line['unit_price']) ? (float) $line['unit_price'] : null;
                $unitPrice = $serverUnitPrice;
                $priceOverridden = false;

                if ($canEditPrice && $clientUnitPrice !== null) {
                    $unitPrice = $clientUnitPrice;
                } elseif ($clientUnitPrice !== null && abs($clientUnitPrice - $serverUnitPrice) > 0.001) {
                    // Client value differs from the catalogue and the user has
                    // no price-edit permission â€” fall back to the server price.
                    $unitPrice = $serverUnitPrice;
                    $priceOverridden = true;
                    $priceOverrides[] = $product->id;
                }

                // Tax resolution (scoped to the business)
                $taxRate = 0;
                $taxId = null;
                if (! empty($line['tax_id'])) {
                    $tax = \App\TaxRate::where('business_id', $business_id)
                        ->where('id', $line['tax_id'])
                        ->first();

                    if ($tax) {
                        $taxRate = (float) $tax->amount;
                        $taxId = $tax->id;
                    }
                }

                // Service staff (scoped to the business)
                $serviceStaffId = null;
                if (! empty($line['service_staff_id'])) {
                    $staffExists = \App\User::where('business_id', $business_id)
                        ->where('id', $line['service_staff_id'])
                        ->exists();

                    if (! $staffExists) {
                        DB::rollBack();

                        return $this->errorResponse('Service staff not found for this business.', 422);
                    }
                    $serviceStaffId = (int) $line['service_staff_id'];
                }

                // Stock availability (only for stock-managed products)
                if ($product->enable_stock) {
                    $stock = $this->getVariationStock($variation->id, $request->location_id);
                    if ($stock < $line['quantity']) {
                        DB::rollBack();

                        return $this->errorResponse(
                            "Insufficient stock for {$product->name}. Available: {$stock}, Requested: {$line['quantity']}",
                            422
                        );
                    }
                }

                $resolvedLines[] = [
                    'product' => $product,
                    'variation' => $variation,
                    'quantity' => (float) $line['quantity'],
                    'unit_price' => $unitPrice,
                    'discount' => (float) ($line['discount'] ?? 0),
                    'tax_rate' => $taxRate,
                    'tax_id' => $taxId,
                    'service_staff_id' => $serviceStaffId,
                    'price_overridden' => $priceOverridden,
                ];
            }

            // ============ CALCULATE TOTALS SERVER-SIDE ============
            // Tax handling follows the product's tax_type:
            //   - exclusive: line price excludes tax -> tax is ADDED on top
            //   - inclusive: line price already contains tax -> tax is EXTRACTED
            $total_before_tax = 0;
            $total_tax = 0;

            foreach ($resolvedLines as $index => $line) {
                $line_total = ($line['quantity'] * $line['unit_price']) - $line['discount'];
                $rate = (float) $line['tax_rate'];
                $isInclusive = (($line['product']->tax_type ?? 'exclusive') === 'inclusive') && $rate > 0;

                if ($isInclusive) {
                    $line_base = $line_total / (1 + $rate / 100);
                    $line_tax = $line_total - $line_base;
                    $unitPriceExc = $line['unit_price'] / (1 + $rate / 100);
                } else {
                    $line_base = $line_total;
                    $line_tax = $line_total * ($rate / 100);
                    $unitPriceExc = $line['unit_price'];
                }

                $unitPriceInc = $unitPriceExc + ($unitPriceExc * $rate / 100);

                $total_tax += $line_tax;
                $total_before_tax += $line_base;

                $resolvedLines[$index]['unit_price_exc'] = $unitPriceExc;
                $resolvedLines[$index]['unit_price_inc'] = $unitPriceInc;
                $resolvedLines[$index]['line_tax'] = $line_tax;
            }

            $discount = $request->discount_amount ?? 0;
            if (($request->discount_type ?? 'fixed') === 'percentage') {
                $discount = $total_before_tax * ($discount / 100);
            }

            $shipping = $request->shipping_charges ?? 0;
            $final_total = round($total_before_tax + $total_tax - $discount + $shipping, 4);

            // Calculate total payments
            $total_paid = collect($request->payments)->sum('amount');
            $amount_remaining = $final_total - $total_paid;

            // Determine payment status
            if ($amount_remaining <= 0) {
                $payment_status = 'paid';
            } elseif ($total_paid > 0) {
                $payment_status = 'partial';
            } else {
                $payment_status = 'due';
            }

            // Server-generated invoice number (never trust client-supplied numbers,
            // and the scheme must receive the real status: 'final', not 'sell').
            $invoice_no = $this->transactionUtil->getInvoiceNumber($business_id, 'final', $request->location_id);

            // Create transaction
            // Note: column names must match the actual DB schema:
            //   transactions.tax_amount (not 'tax'),
            //   transactions.staff_note (not 'sale_note'),
            //   transactions.res_table_id (not 'table_id')
            //   amount_paid/amount_remaining are computed from transaction_payments, not stored
            $transaction = Transaction::create([
                'business_id' => $business_id,
                'location_id' => $request->location_id,
                'local_transaction_id' => $request->input('local_transaction_id'),
                'type' => 'sell',
                'sub_type' => $request->input('sub_type'),
                'status' => 'final',
                'contact_id' => $request->contact_id,
                'invoice_no' => $invoice_no,
                'ref_no' => $request->input('ref_no'),
                'transaction_date' => now(),
                'total_before_tax' => $total_before_tax,
                'tax_amount' => $total_tax,
                'final_total' => $final_total,
                'payment_status' => $payment_status,
                'discount_type' => $request->discount_type ?? 'fixed',
                'discount_amount' => $discount,
                'shipping_charges' => $shipping,
                'additional_notes' => $request->additional_notes,
                'staff_note' => $request->sale_note,
                'created_by' => $user_id,
                'types_of_service_id' => $request->types_of_service_id,
                'res_table_id' => $request->table_id,
                'is_created_from_api' => 1,
                'invoice_token' => \Str::random(32),
                'rp_redeemed' => !empty($request->rp_redeemed) ? $request->rp_redeemed : 0,
                'rp_redeemed_amount' => !empty($request->rp_redeemed_amount) ? $request->rp_redeemed_amount : 0,
            ]);

            // Create sell lines (using the server-resolved, tax-correct prices)
            foreach ($resolvedLines as $line) {
                TransactionSellLine::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $line['product']->id,
                    'variation_id' => $line['variation']->id,
                    'quantity' => $line['quantity'],
                    'unit_price_before_discount' => $line['unit_price_exc'],
                    'unit_price' => $line['unit_price_exc'],
                    'unit_price_inc_tax' => $line['unit_price_inc'],
                    'line_discount_amount' => $line['discount'],
                    'line_discount_type' => 'fixed',
                    'item_tax' => $line['line_tax'],
                    'tax_id' => $line['tax_id'],
                    'res_service_staff_id' => $line['service_staff_id'],
                ]);

                // Update stock if applicable
                if ($line['product']->enable_stock) {
                    $this->updateStock($line['variation']->id, $request->location_id, $line['quantity'], 'decrease');
                }
            }

            // Resolve payment accounts from the location's
            // default_payment_accounts map ({method: account_id}) so the
            // Accounting module can reconcile POS payments.
            $paymentAccountMap = [];
            $saleLocation = BusinessLocation::find($request->location_id);
            if ($saleLocation && ! empty($saleLocation->default_payment_accounts)) {
                $decoded = json_decode($saleLocation->default_payment_accounts, true);
                if (is_array($decoded)) {
                    $paymentAccountMap = $decoded;
                }
            }

            // Create payment lines
            foreach ($request->payments as $payment) {
                TransactionPayment::create([
                    'transaction_id' => $transaction->id,
                    'business_id' => $business_id,
                    'amount' => $payment['amount'],
                    'method' => $payment['method'],
                    'payment_ref_no' => $payment['reference'] ?? null,
                    'account_id' => $paymentAccountMap[$payment['method']] ?? null,
                    'created_by' => $user_id,
                    'paid_on' => now(),
                ]);
            }

            DB::commit();

            // Update customer reward points
            if (!empty($request->contact_id)) {
                $business = \App\Business::find($business_id);
                if (!empty($business->enable_rp)) {
                    // Calculate earned points
                    $rp_earned = $this->transactionUtil->calculateRewardPoints($business_id, $final_total);
                    $rp_redeemed = !empty($request->rp_redeemed) ? $request->rp_redeemed : 0;

                    // Update customer points
                    $contact = Contact::find($request->contact_id);
                    if ($contact) {
                        $contact->total_rp = ($contact->total_rp ?? 0) + $rp_earned - $rp_redeemed;
                        $contact->total_rp_used = ($contact->total_rp_used ?? 0) + $rp_redeemed;
                        $contact->save();

                        // Update transaction with earned points
                        $transaction->rp_earned = $rp_earned;
                        $transaction->save();
                    }
                }
            }

            // Return created sale
            $transaction = Transaction::with(['contact', 'location', 'sell_lines.product', 'payment_lines'])
                ->find($transaction->id);

            $responseExtra = ['invoice_number' => $transaction->invoice_no];

            if (! empty($priceOverrides)) {
                // Transparency: the client submitted unit prices that were not
                // honoured (no price-edit permission). Client should refresh.
                $responseExtra['price_overridden_product_ids'] = array_values(array_unique($priceOverrides));
            }

            $response = array_merge($transaction->toArray(), $responseExtra);

            return $this->successResponse($response, 'Sale created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('POS Sale creation failed: business=' . $business_id
                . ' user=' . ($user_id ?? 'n/a')
                . ' | ' . $e->getMessage());

            // Never leak internal exception details to the API consumer.
            return $this->errorResponse('Failed to create sale. Please try again.', 500);
        }
    }

    /**
     * Get drafts (held transactions)
     */
    public function drafts(Request $request)
    {
        $business_id = $this->getBusinessId($request);
        $location_id = $request->input('location_id');
        $per_page = $request->input('per_page', 20);

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'draft')
            ->with(['contact:id,name', 'sell_lines' => function ($q) {
                $q->with(['product:id,name', 'variation:id,name']);
            }]);

        if ($location_id) {
            $query->where('location_id', $location_id);
        }

        $drafts = $query->orderBy('created_at', 'desc')
            ->paginate($per_page);

        return $this->paginatedResponse($drafts);
    }

    /**
     * Delete a draft
     */
    public function destroyDraft(Request $request, $id)
    {
        $business_id = $this->getBusinessId($request);

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'draft')
            ->find($id);

        if (!$transaction) {
            return $this->errorResponse('Draft not found.', 404);
        }

        // Restore stock by checking if the product has stock management enabled
        foreach ($transaction->sell_lines as $line) {
            $product = \App\Product::find($line->product_id);
            if ($product && $product->enable_stock) {
                $this->updateStock($line->variation_id, $transaction->location_id, $line->quantity, 'increase');
            }
        }

        $transaction->sell_lines()->delete();
        $transaction->delete();

        return $this->successResponse(null, 'Draft deleted successfully');
    }

    /**
     * Add payment to an existing sale
     */
    public function addPayment(Request $request, $id)
    {
        $business_id = $this->getBusinessId($request);
        $user_id = $request->user()->id;

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->find($id);

        if (!$transaction) {
            return $this->errorResponse('Sale not found.', 404);
        }

        if ($transaction->payment_status === 'paid') {
            return $this->errorResponse('Sale is already fully paid.', 422);
        }

        $payment = TransactionPayment::create([
            'transaction_id' => $transaction->id,
            'business_id' => $business_id,
            'amount' => $request->amount,
            'method' => $request->method,
            'payment_ref_no' => $request->reference,
            'created_by' => $user_id,
            'paid_on' => now(),
        ]);

        // Update transaction payment status
        $total_paid = TransactionPayment::where('transaction_id', $id)->sum('amount');
        $amount_remaining = $transaction->final_total - $total_paid;

        if ($amount_remaining <= 0) {
            $payment_status = 'paid';
        } elseif ($total_paid > 0) {
            $payment_status = 'partial';
        } else {
            $payment_status = 'due';
        }

        $transaction->update([
            'payment_status' => $payment_status,
        ]);

        return $this->successResponse([
            'payment' => $payment,
            'payment_status' => $payment_status,
            'amount_remaining' => max(0, $amount_remaining),
        ], 'Payment added successfully');
    }

    /**
     * Get daily sales summary
     */
    public function dailySummary(Request $request)
    {
        $business_id = $this->getBusinessId($request);
        $location_id = $request->input('location_id');
        $date = $request->input('date', now()->format('Y-m-d'));

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereDate('transaction_date', $date);

        if ($location_id) {
            $query->where('location_id', $location_id);
        }

        $total_sales = (clone $query)->sum('final_total');
        $total_tax = (clone $query)->sum('tax_amount');
        $total_discount = (clone $query)->sum('discount_amount');
        $transaction_count = (clone $query)->count();

        // Payment breakdown
        $payments = TransactionPayment::where('business_id', $business_id)
            ->whereDate('paid_on', $date)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->get();

        return $this->successResponse([
            'date' => $date,
            'total_sales' => $total_sales,
            'total_tax' => $total_tax,
            'total_discount' => $total_discount,

            'transaction_count' => $transaction_count,
            'average_sale' => $transaction_count > 0 ? $total_sales / $transaction_count : 0,
            'payment_breakdown' => $payments,
        ]);
    }

    /**
     * Update stock level
     */
    private function updateStock(int $variation_id, int $location_id, float $quantity, string $action): void
    {
        $stock_detail = \App\VariationLocationDetails::where('variation_id', $variation_id)
            ->where('location_id', $location_id)
            ->first();

        if ($stock_detail) {
            if ($action === 'decrease') {
                $stock_detail->decrement('qty_available', $quantity);
            } else {
                $stock_detail->increment('qty_available', $quantity);
            }
        }
    }

    /**
     * Get stock for a variation at a location
     */
    private function getVariationStock(int $variation_id, int $location_id): float
    {
        $stock = \App\VariationLocationDetails::where('variation_id', $variation_id)
            ->where('location_id', $location_id)
            ->value('qty_available');

        return (float) ($stock ?? 0);
    }

    /**
     * Return items from a previous sale
     */
    public function returnItems(Request $request, int $id): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->with(['sell_lines'])
            ->find($id);

        if (!$transaction) {
            return $this->errorResponse('Sale not found.', 404);
        }

        $request->validate([
            'return_items' => 'required|array|min:1',
            'return_items.*.sell_line_id' => 'required|integer',
            'return_items.*.quantity' => 'required|numeric|min:0.01',
            'return_items.*.return_reason' => 'nullable|string',
            'refund_method' => 'nullable|in:cash,card,bank_transfer,other',
            'exchange_product_id' => 'nullable|integer',
            'exchange_variation_id' => 'nullable|integer',
            'exchange_quantity' => 'nullable|numeric|min:0.01',
            'exchange_unit_price' => 'nullable|numeric|min:0',
        ]);

        $total_return_amount = 0;
        $return_lines = [];

        DB::beginTransaction();
        try {
            foreach ($request->return_items as $item) {
                $sell_line = \App\TransactionSellLine::where('transaction_id', $id)
                    ->where('id', $item['sell_line_id'])
                    ->first();

                if (!$sell_line) {
                    DB::rollBack();
                    return $this->errorResponse('Sell line not found: ' . $item['sell_line_id'], 422);
                }

                $returnable = $sell_line->quantity - ($sell_line->quantity_returned ?? 0);
                if ($item['quantity'] > $returnable) {
                    DB::rollBack();
                    return $this->errorResponse('Cannot return more than purchased. Returnable: ' . $returnable, 422);
                }

                $line_return_amount = $item['quantity'] * $sell_line->unit_price;
                $total_return_amount += $line_return_amount;

                // Update quantity returned
                $sell_line->quantity_returned += $item['quantity'];
                $sell_line->save();

                // Restore stock if stock management is enabled
                $product = \App\Product::find($sell_line->product_id);
                if ($product && $product->enable_stock) {
                    \App\VariationLocationDetails::where('variation_id', $sell_line->variation_id)
                        ->where('location_id', $transaction->location_id)
                        ->decrement('qty_available', $item['quantity']);
                }

                $return_lines[] = [
                    'sell_line_id' => $sell_line->id,
                    'product_id' => $sell_line->product_id,
                    'variation_id' => $sell_line->variation_id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $sell_line->unit_price,
                    'line_total' => $line_return_amount,
                    'reason' => $item['return_reason'] ?? null,
                ];
            }

            // Create refund payment
            $refund_method = $request->refund_method ?? 'cash';
            $user_id = auth()->user()->id ?? 1;

            \App\TransactionPayment::create([
                'transaction_id' => $id,
                'business_id' => $business_id,
                'method' => $refund_method,
                'amount' => -$total_return_amount,
                'is_return' => 1,
                'paid_on' => now(),
                'created_by' => $user_id,
            ]);

            // Update transaction totals
            $total_returned = \App\TransactionPayment::where('transaction_id', $id)
                ->where('is_return', 1)
                ->sum('amount');

            // Check if this is an exchange
            $exchange_sale_id = null;
            if ($request->exchange_product_id && $request->exchange_variation_id && $request->exchange_quantity) {
                // Create exchange sale
                $exchange_final = $request->exchange_quantity * $request->exchange_unit_price;
                $exchange_sale_id = DB::table('transactions')->insertGetId([
                    'business_id' => $business_id,
                    'location_id' => $transaction->location_id,
                    'contact_id' => $transaction->contact_id,
                    'type' => 'sell',
                    'status' => 'final',
                    'total_before_tax' => $request->exchange_unit_price * $request->exchange_quantity,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'final_total' => $exchange_final,
                    'payment_status' => 'due',
                    'invoice_no' => 'RET-' . time(),
                    'transaction_date' => now(),
                    'created_by' => $user_id,
                ]);

                // Deduct exchange payment from refund
                $exchange_diff = $total_return_amount - $exchange_final;
                if ($exchange_diff > 0) {
                    // Customer gets refund difference
                } elseif ($exchange_diff < 0) {
                    // Customer pays extra
                    \App\TransactionPayment::create([
                        'transaction_id' => $exchange_sale_id,
                        'business_id' => $business_id,
                        'method' => 'cash',
                        'amount' => abs($exchange_diff),
                        'paid_on' => now(),
                        'created_by' => $user_id,
                    ]);
                }
            }

            DB::commit();

            return $this->successResponse([
                'return_amount' => $total_return_amount,
                'refund_method' => $refund_method,
                'return_lines' => $return_lines,
                'exchange_sale_id' => $exchange_sale_id,
            ], 'Return processed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Sell return error: ' . $e->getMessage());
            return $this->errorResponse('Failed to process return: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Pay customer's credit balance
     */
    public function payCredit(Request $request, int $contact_id): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $user_id = $request->user()->id;

        $request->validate([
            'amount' => 'required|numeric|min:0.001',
            'method' => 'required|in:cash,card,bank_transfer,cheque,other',
            'reference' => 'nullable|string',
        ]);

        $contact = \App\Contact::where('business_id', $business_id)
            ->where('id', $contact_id)
            ->first();

        if (!$contact) {
            return $this->errorResponse('Customer not found.', 404);
        }

        // Find due transactions (not fully paid) by checking payment_status
        $due_transactions = Transaction::where('business_id', $business_id)
            ->where('contact_id', $contact_id)
            ->where('type', 'sell')
            ->where('payment_status', '!=', 'paid')
            ->orderBy('transaction_date', 'asc')
            ->get();

        $remaining = $request->amount;
        $payments_created = [];

        DB::beginTransaction();
        try {
            foreach ($due_transactions as $due_txn) {
                if ($remaining <= 0) break;

                // Calculate amount paid from payments table (amount_paid column doesn't exist)
                $total_paid_for_txn = \App\TransactionPayment::where('transaction_id', $due_txn->id)
                    ->where('is_return', 0)
                    ->sum('amount');
                $txn_due = $due_txn->final_total - $total_paid_for_txn;
                $pay_amount = min($remaining, $txn_due);

                \App\TransactionPayment::create([
                    'transaction_id' => $due_txn->id,
                    'business_id' => $business_id,
                    'method' => $request->method,
                    'amount' => $pay_amount,
                    'payment_ref_no' => $request->reference,
                    'paid_on' => now(),
                    'created_by' => $user_id,
                ]);

                // Update payment status based on total payments from payments table
                $new_total_paid = \App\TransactionPayment::where('transaction_id', $due_txn->id)
                    ->where('is_return', 0)
                    ->sum('amount');
                $due_txn->payment_status = $new_total_paid >= $due_txn->final_total ? 'paid' : 'partial';
                $due_txn->save();

                $payments_created[] = [
                    'transaction_id' => $due_txn->id,
                    'amount' => $pay_amount,
                ];

                $remaining -= $pay_amount;
            }

            DB::commit();

            // Recalculate balance by summing payments from the payments table
            $due_sells = Transaction::where('business_id', $business_id)
                ->where('contact_id', $contact_id)
                ->where('type', 'sell')
                ->get();
            $new_balance = 0;
            foreach ($due_sells as $sell) {
                $sell_paid = \App\TransactionPayment::where('transaction_id', $sell->id)
                    ->where('is_return', 0)
                    ->sum('amount');
                $sell_due = $sell->final_total - $sell_paid;
                if ($sell_due > 0) {
                    $new_balance += $sell_due;
                }
            }

            return $this->successResponse([
                'paid_amount' => $request->amount - $remaining,
                'remaining_credit' => max(0, $new_balance),
                'payments' => $payments_created,
            ], 'Credit payment recorded successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }
}
