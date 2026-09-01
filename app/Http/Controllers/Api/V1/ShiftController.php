<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends BaseApiController
{
    /**
     * Get current open shift (if any)
     * GET /api/v1/shifts/current
     */
    public function current(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $location_id = $request->input('location_id');

        $query = DB::table('pos_shifts')
            ->where('business_id', $business_id)
            ->where('status', 'open');

        if ($location_id) {
            $query->where('location_id', $location_id);
        }

        $shift = $query->first();

        if ($shift) {
            // Calculate live totals for open shift using transactions table
            $shift->total_sales = DB::table('transactions')
                ->where('business_id', $business_id)
                ->whereDate('created_at', date('Y-m-d', strtotime($shift->opened_at)))
                ->where('is_quotation', 0)
                ->where('type', 'sell')
                ->sum('final_total') ?? 0;

            $shift->total_transactions = DB::table('transactions')
                ->where('business_id', $business_id)
                ->whereDate('created_at', date('Y-m-d', strtotime($shift->opened_at)))
                ->where('is_quotation', 0)
                ->where('type', 'sell')
                ->count();

            // Payment breakdown
            $payments = DB::table('transaction_payments')
                ->where('business_id', $business_id)
                ->whereDate('created_at', date('Y-m-d', strtotime($shift->opened_at)))
                ->select('method', DB::raw('SUM(amount) as total'))
                ->groupBy('method')
                ->get();

            $shift->payment_breakdown = $payments;
        }

        return $this->successResponse(['shift' => $shift]);
    }

    /**
     * Open a new shift
     * POST /api/v1/shifts/open
     */
    public function open(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $user_id = auth()->user()->id ?? 1;

        // Check if there's already an open shift
        $existingShift = DB::table('pos_shifts')
            ->where('business_id', $business_id)
            ->where('status', 'open')
            ->first();

        if ($existingShift) {
            return $this->errorResponse('A shift is already open. Close it first.', 400);
        }

        $id = DB::table('pos_shifts')->insertGetId([
            'business_id' => $business_id,
            'location_id' => $request->input('location_id'),
            'user_id' => $user_id,
            'opened_at' => now(),
            'status' => 'open',
            'opening_cash' => $request->input('opening_cash', 0),
            'opening_notes' => $request->input('opening_notes'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse([
            'shift_id' => $id,
            'opened_at' => now()->toDateTimeString(),
        ], 'Shift opened successfully');
    }

    /**
     * Get daily summary for closing
     * GET /api/v1/shifts/daily-summary
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $date = $request->input('date', now()->toDateString());

        // Get transactions (sells) for the day
        $sells = DB::table('transactions')
            ->where('business_id', $business_id)
            ->whereDate('created_at', $date)
            ->where('is_quotation', 0)
            ->where('type', 'sell')
            ->get();

        $totalSales = $sells->sum('final_total');
        $totalTax = $sells->sum('tax_amount');
        $totalDiscount = $sells->sum('discount_amount');
        $totalTransactions = $sells->count();

        // Payment breakdown
        $paymentBreakdown = DB::table('transaction_payments')
            ->where('business_id', $business_id)
            ->whereDate('created_at', $date)
            ->where('is_return', 0)
            ->select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        // Refunds (if sell_return table exists)
        $totalRefunds = 0;
        $refundCount = 0;
        try {
            $totalRefunds = DB::table('sell_return')
                ->where('business_id', $business_id)
                ->whereDate('created_at', $date)
                ->sum('return_amount') ?? 0;
            $refundCount = DB::table('sell_return')
                ->where('business_id', $business_id)
                ->whereDate('created_at', $date)
                ->count();
        } catch (\Exception $e) {
            // Table might not exist
        }

        // Top selling products
        $topProducts = collect();
        try {
            $topProducts = DB::table('transaction_sell_lines')
                ->join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_sell_lines.product_id', '=', 'products.id')
                ->where('transactions.business_id', $business_id)
                ->whereDate('transactions.created_at', $date)
                ->where('transactions.type', 'sell')
                ->select('products.name', DB::raw('SUM(transaction_sell_lines.quantity) as total_qty'), DB::raw('SUM(transaction_sell_lines.unit_price * transaction_sell_lines.quantity) as total_revenue'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_qty')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            // Table might not exist
        }

        return $this->successResponse([
            'date' => $date,
            'total_sales' => $totalSales,
            'total_tax' => $totalTax,
            'total_discount' => $totalDiscount,
            'total_transactions' => $totalTransactions,
            'total_refunds' => $totalRefunds,
            'refund_count' => $refundCount,
            'net_sales' => $totalSales - $totalRefunds,
            'payment_breakdown' => $paymentBreakdown,
            'top_products' => $topProducts,
        ]);
    }

    /**
     * Close current shift
     * POST /api/v1/shifts/close
     */
    public function close(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $shift = DB::table('pos_shifts')
            ->where('business_id', $business_id)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return $this->errorResponse('No open shift found', 404);
        }

        // Calculate daily totals using transactions table
        $date = date('Y-m-d', strtotime($shift->opened_at));

        $totalSales = DB::table('transactions')
            ->where('business_id', $business_id)
            ->whereDate('created_at', $date)
            ->where('is_quotation', 0)
            ->where('type', 'sell')
            ->sum('final_total') ?? 0;

        $totalTax = DB::table('transactions')
            ->where('business_id', $business_id)
            ->whereDate('created_at', $date)
            ->where('is_quotation', 0)
            ->where('type', 'sell')
            ->sum('tax_amount') ?? 0;

        $totalDiscount = DB::table('transactions')
            ->where('business_id', $business_id)
            ->whereDate('created_at', $date)
            ->where('is_quotation', 0)
            ->where('type', 'sell')
            ->sum('discount_amount') ?? 0;

        $totalTransactions = DB::table('transactions')
            ->where('business_id', $business_id)
            ->whereDate('created_at', $date)
            ->where('is_quotation', 0)
            ->where('type', 'sell')
            ->count();

        // Payment breakdown by method
        $cashSales = DB::table('transaction_payments')
            ->where('business_id', $business_id)
            ->whereDate('created_at', $date)
            ->where('method', 'cash')
            ->where('is_return', 0)
            ->sum('amount') ?? 0;

        $cardSales = DB::table('transaction_payments')
            ->where('business_id', $business_id)
            ->whereDate('created_at', $date)
            ->where('method', '!=', 'cash')
            ->where('is_return', 0)
            ->sum('amount') ?? 0;

        // Expected cash: opening_cash + cash_sales
        $expectedCash = $shift->opening_cash + $cashSales;
        $countedCash = $request->input('counted_cash', $expectedCash);
        $cashDifference = $countedCash - $expectedCash;

        // Update shift
        DB::table('pos_shifts')
            ->where('id', $shift->id)
            ->update([
                'closed_at' => now(),
                'status' => 'closed',
                'total_sales' => $totalSales,
                'total_cash_sales' => $cashSales,
                'total_card_sales' => $cardSales,
                'total_other_sales' => $totalSales - $cashSales - $cardSales,
                'total_transactions' => $totalTransactions,
                'total_tax' => $totalTax,
                'total_discount' => $totalDiscount,
                'expected_cash' => $expectedCash,
                'counted_cash' => $countedCash,
                'cash_difference' => $cashDifference,
                'closing_notes' => $request->input('closing_notes'),
                'updated_at' => now(),
            ]);

        return $this->successResponse([
            'shift_id' => $shift->id,
            'total_sales' => $totalSales,
            'total_cash_sales' => $cashSales,
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'cash_difference' => $cashDifference,
        ], 'Shift closed successfully');
    }

    /**
     * Get shift history
     * GET /api/v1/shifts/history
     */
    public function history(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $limit = $request->input('limit', 30);

        $shifts = DB::table('pos_shifts')
            ->where('business_id', $business_id)
            ->orderByDesc('opened_at')
            ->limit($limit)
            ->get();

        return $this->successResponse(['shifts' => $shifts]);
    }
}
