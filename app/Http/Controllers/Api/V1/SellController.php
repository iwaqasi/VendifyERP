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

        \Log::info('POS Sell Store - Received data:', [
            'business_id' => $business_id,
            'user_id' => $user_id,
            'all_input' => $request->all(),
            'json_input' => $request->input(),
        ]);

        $request->validate([
            'location_id' => 'required|integer',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer',
            'products.*.variation_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.tax_id' => 'nullable|integer',
            'products.*.service_staff_id' => 'nullable|integer',
            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.method' => 'required|string',
            'contact_id' => 'nullable|integer',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_charges' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Validate stock availability
            foreach ($request->products as $line) {
                $variation = Variation::find($line['variation_id']);
                $product = Product::find($line['product_id']);

                if (!$variation || !$product) {
                    DB::rollBack();
                    return $this->errorResponse("Product or variation not found for ID: {$line['product_id']}", 422);
                }

                if ($product->enable_stock) {
                    $stock = $this->getVariationStock($line['variation_id'], $request->location_id);
                    if ($stock < $line['quantity']) {
                        DB::rollBack();
                        return $this->errorResponse(
                            "Insufficient stock for {$product->name}. Available: {$stock}, Requested: {$line['quantity']}",
                            422
                        );
                    }
                }
            }

            // Calculate totals
            $total_before_tax = 0;
            $total_tax = 0;

            foreach ($request->products as $line) {
                $line_total = $line['quantity'] * $line['unit_price'];
                $line_discount = $line['discount'] ?? 0;
                $line_total -= $line_discount;

                // Calculate tax
                $tax_rate = 0;
                if (!empty($line['tax_id'])) {
                    $tax = \App\TaxRate::find($line['tax_id']);
                    if ($tax) {
                        $tax_rate = $tax->rate;
                    }
                }
                $line_tax = $line_total * ($tax_rate / 100);
                $total_tax += $line_tax;
                $total_before_tax += ($line_total - $line_tax);
            }

            $discount = $request->discount_amount ?? 0;
            if (($request->discount_type ?? 'fixed') === 'percentage') {
                $discount = $total_before_tax * ($discount / 100);
            }

            $shipping = $request->shipping_charges ?? 0;
            $final_total = $total_before_tax + $total_tax - $discount + $shipping;

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

            // Use POS-provided invoice number if available, otherwise generate one
            $invoice_no = $request->input('invoice_no');
            if (empty($invoice_no)) {
                $invoice_no = $this->transactionUtil->getInvoiceNumber($business_id, 'sell', $request->location_id);
            }

            // Create transaction
            $transaction = Transaction::create([
                'business_id' => $business_id,
                'location_id' => $request->location_id,
                'type' => 'sell',
                'sub_type' => $request->input('sub_type', 'sales_order'),
                'status' => 'final',
                'contact_id' => $request->contact_id,
                'invoice_no' => $invoice_no,
                'ref_no' => $request->input('ref_no'),
                'transaction_date' => now(),
                'total_before_tax' => $total_before_tax,
                'tax' => $total_tax,
                'final_total' => $final_total,
                'amount_paid' => $total_paid,
                'amount_remaining' => $amount_remaining,
                'payment_status' => $payment_status,
                'discount_type' => $request->discount_type ?? 'fixed',
                'discount_amount' => $discount,
                'shipping_charges' => $shipping,
                'additional_notes' => $request->additional_notes,
                'sale_note' => $request->sale_note,
                'created_by' => $user_id,
                'types_of_service_id' => $request->types_of_service_id,
                'table_id' => $request->table_id,
                'is_created_from_api' => 1,
                'invoice_token' => \Str::random(32),
                'rp_redeemed' => !empty($request->rp_redeemed) ? $request->rp_redeemed : 0,
                'rp_redeemed_amount' => !empty($request->rp_redeemed_amount) ? $request->rp_redeemed_amount : 0,
            ]);

            // Create sell lines
            foreach ($request->products as $line) {
                $product = Product::find($line['product_id']);
                $line_total = $line['quantity'] * $line['unit_price'];
                $line_discount = $line['discount'] ?? 0;

                $tax_rate = 0;
                $tax_id = null;
                if (!empty($line['tax_id'])) {
                    $tax = \App\TaxRate::find($line['tax_id']);
                    if ($tax) {
                        $tax_rate = $tax->rate;
                        $tax_id = $tax->id;
                    }
                }
                $item_tax = ($line_total - $line_discount) * ($tax_rate / 100);

                TransactionSellLine::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $line['product_id'],
                    'variation_id' => $line['variation_id'],
                    'quantity' => $line['quantity'],
                    'unit_price_before_discount' => $line['unit_price'],
                    'unit_price' => $line['unit_price'],
                    'unit_price_inc_tax' => $line['unit_price'] + ($line['unit_price'] * $tax_rate / 100),
                    'discount' => $line_discount,
                    'discount_type' => 'fixed',
                    'item_tax' => $item_tax,
                    'tax_id' => $tax_id,
                    'res_service_staff_id' => $line['service_staff_id'] ?? null,
                    'enable_stock' => $product->enable_stock,
                ]);

                // Update stock if applicable
                if ($product->enable_stock) {
                    $this->updateStock($line['variation_id'], $request->location_id, $line['quantity'], 'decrease');
                }
            }

            // Create payment lines
            foreach ($request->payments as $payment) {
                TransactionPayment::create([
                    'transaction_id' => $transaction->id,
                    'business_id' => $business_id,
                    'amount' => $payment['amount'],
                    'method' => $payment['method'],
                    'reference_no' => $payment['reference'] ?? null,
                    'payment_date' => now(),
                    'created_by' => $user_id,
                    'paid_on' => now(),
                    'is_advance' => 0,
                    'is_change_return' => 0,
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

            return $this->successResponse($transaction, 'Sale created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('POS Sale Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to create sale: ' . $e->getMessage(), 500);
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

        // Restore stock
        foreach ($transaction->sell_lines as $line) {
            if ($line->enable_stock) {
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
            'reference_no' => $request->reference,
            'payment_date' => now(),
            'created_by' => $user_id,
            'paid_on' => now(),
            'is_advance' => 0,
            'is_change_return' => 0,
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
            'amount_paid' => $total_paid,
            'amount_remaining' => max(0, $amount_remaining),
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
                $stock_detail->decrement('stock_checked', $quantity);
            } else {
                $stock_detail->increment('qty_available', $quantity);
                $stock_detail->increment('stock_checked', $quantity);
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
                $sell_line = \App\SellLine::where('transaction_id', $id)
                    ->where('id', $item['sell_line_id'])
                    ->first();

                if (!$sell_line) {
                    DB::rollBack();
                    return $this->errorResponse('Sell line not found: ' . $item['sell_line_id'], 422);
                }

                if ($item['quantity'] > $sell_line->quantity_returned + $sell_line->quantity - $sell_line->quantity) {
                    // Allow return up to original quantity minus already returned
                }
                $returnable = $sell_line->quantity - $sell_line->quantity_returned;
                if ($item['quantity'] > $returnable) {
                    DB::rollBack();
                    return $this->errorResponse('Cannot return more than purchased. Returnable: ' . $returnable, 422);
                }

                $line_return_amount = $item['quantity'] * $sell_line->unit_price;
                $total_return_amount += $line_return_amount;

                // Update quantity returned
                $sell_line->quantity_returned += $item['quantity'];
                $sell_line->save();

                // Restore stock if manage stock
                $product = \App\Product::find($sell_line->product_id);
                if ($product && $product->manage_stock) {
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
                    'sub_total' => $request->exchange_unit_price * $request->exchange_quantity,
                    'tax' => 0,
                    'discount' => 0,
                    'final_total' => $exchange_final,
                    'invoice_no' => 'RET-' . time(),
                    'transaction_date' => now(),
                    'created_by' => $user_id,
                    'is_return' => 1,
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

        // Create a credit payment transaction
        // Find or create a due transaction to pay against
        $due_transactions = Transaction::where('business_id', $business_id)
            ->where('contact_id', $contact_id)
            ->where('type', 'sell')
            ->whereColumn('final_total', '>', DB::raw('amount_paid'))
            ->orderBy('transaction_date', 'asc')
            ->get();

        $remaining = $request->amount;
        $payments_created = [];

        DB::beginTransaction();
        try {
            foreach ($due_transactions as $due_txn) {
                if ($remaining <= 0) break;

                $txn_due = $due_txn->final_total - $due_txn->amount_paid;
                $pay_amount = min($remaining, $txn_due);

                \App\TransactionPayment::create([
                    'transaction_id' => $due_txn->id,
                    'business_id' => $business_id,
                    'method' => $request->method,
                    'amount' => $pay_amount,
                    'reference' => $request->reference,
                    'paid_on' => now(),
                    'created_by' => $user_id,
                ]);

                $due_txn->amount_paid += $pay_amount;
                $due_txn->payment_status = $due_txn->amount_paid >= $due_txn->final_total ? 'paid' : 'partial';
                $due_txn->save();

                $payments_created[] = [
                    'transaction_id' => $due_txn->id,
                    'amount' => $pay_amount,
                ];

                $remaining -= $pay_amount;
            }

            DB::commit();

            // Recalculate balance
            $new_balance = Transaction::where('business_id', $business_id)
                ->where('contact_id', $contact_id)
                ->where('type', 'sell')
                ->sum(DB::raw('final_total - amount_paid'));

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
