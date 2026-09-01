<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosDashboardController extends Controller
{
    /**
     * Show POS Dashboard
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $business = null;
        $currency_symbol = 'KD';
        $todaySales = 0;
        $todayTransactions = 0;
        $monthSales = 0;
        $monthTransactions = 0;
        $totalProducts = 0;
        $lowStockCount = 0;
        $totalCustomers = 0;
        $newCustomersToday = 0;
        $paymentBreakdown = [];
        $recentSales = collect();
        $topProducts = collect();
        $currentShift = null;

        if (!$business_id) {
            $business_id = 0;
        }

        // Get business
        try {
            $business = \App\Business::find($business_id);
        } catch (\Exception $e) {
            Log::error('POS Dashboard business error: ' . $e->getMessage());
        }

        // Get currency symbol
        try {
            if ($business && $business->currency_id) {
                $currency = DB::table('currencies')->where('id', $business->currency_id)->first();
                if ($currency) {
                    $currency_symbol = $currency->symbol ?? 'KD';
                }
            }
        } catch (\Exception $e) {
            // Default
        }

        // Today's sales (table: transactions, amount column: final_total)
        try {
            $todaySales = (float) DB::table('transactions')
                ->where('business_id', $business_id)
                ->whereDate('created_at', now()->toDateString())
                ->where('is_quotation', 0)
                ->where('type', 'sell')
                ->sum('final_total');

            $todayTransactions = (int) DB::table('transactions')
                ->where('business_id', $business_id)
                ->whereDate('created_at', now()->toDateString())
                ->where('is_quotation', 0)
                ->where('type', 'sell')
                ->count();
        } catch (\Exception $e) {
            Log::error('POS Dashboard today sales error: ' . $e->getMessage());
        }

        // Monthly sales
        try {
            $monthSales = (float) DB::table('transactions')
                ->where('business_id', $business_id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('is_quotation', 0)
                ->where('type', 'sell')
                ->sum('final_total');

            $monthTransactions = (int) DB::table('transactions')
                ->where('business_id', $business_id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('is_quotation', 0)
                ->where('type', 'sell')
                ->count();
        } catch (\Exception $e) {
            Log::error('POS Dashboard month sales error: ' . $e->getMessage());
        }

        // Products
        try {
            $totalProducts = (int) DB::table('products')
                ->where('business_id', $business_id)
                ->where('is_disabled', 0)
                ->count();
        } catch (\Exception $e) {
            Log::error('POS Dashboard products error: ' . $e->getMessage());
        }

        // Low stock
        try {
            $lowStockCount = (int) DB::table('products')
                ->where('business_id', $business_id)
                ->where('is_disabled', 0)
                ->where('enable_stock', 1)
                ->whereColumn('stock_available', '<=', 'alert_stock_count')
                ->count();
        } catch (\Exception $e) {
            // Column might not exist
        }

        // Customers
        try {
            $totalCustomers = (int) DB::table('contacts')
                ->where('business_id', $business_id)
                ->where('type', 'customer')
                ->count();

            $newCustomersToday = (int) DB::table('contacts')
                ->where('business_id', $business_id)
                ->where('type', 'customer')
                ->whereDate('created_at', now()->toDateString())
                ->count();
        } catch (\Exception $e) {
            Log::error('POS Dashboard customers error: ' . $e->getMessage());
        }

        // Payment breakdown for today
        try {
            $paymentBreakdown = DB::table('transaction_payments')
                ->where('business_id', $business_id)
                ->whereDate('created_at', now()->toDateString())
                ->where('is_return', 0)
                ->select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('method')
                ->get()
                ->keyBy('method')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('POS Dashboard payment error: ' . $e->getMessage());
        }

        // Recent sales
        try {
            $recentSales = DB::table('transactions')
                ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                ->where('transactions.business_id', $business_id)
                ->whereDate('transactions.created_at', now()->toDateString())
                ->where('transactions.is_quotation', 0)
                ->where('transactions.type', 'sell')
                ->select('transactions.*', 'contacts.name as contact_name')
                ->orderByDesc('transactions.created_at')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            Log::error('POS Dashboard recent sales error: ' . $e->getMessage());
        }

        // Top products this month
        try {
            $topProducts = DB::table('transaction_sell_lines')
                ->join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_sell_lines.product_id', '=', 'products.id')
                ->where('transactions.business_id', $business_id)
                ->whereMonth('transactions.created_at', now()->month)
                ->whereYear('transactions.created_at', now()->year)
                ->where('transactions.type', 'sell')
                ->select('products.name', DB::raw('SUM(transaction_sell_lines.quantity) as total_qty'), DB::raw('SUM(transaction_sell_lines.unit_price * transaction_sell_lines.quantity) as total_revenue'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_qty')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            Log::error('POS Dashboard top products error: ' . $e->getMessage());
        }

        // Current open shift
        try {
            $currentShift = DB::table('pos_shifts')
                ->where('business_id', $business_id)
                ->where('status', 'open')
                ->first();
        } catch (\Exception $e) {
            // Table might not exist
        }

        return view('pos.dashboard', compact(
            'business',
            'currency_symbol',
            'todaySales',
            'todayTransactions',
            'monthSales',
            'monthTransactions',
            'totalProducts',
            'lowStockCount',
            'totalCustomers',
            'newCustomersToday',
            'paymentBreakdown',
            'recentSales',
            'topProducts',
            'currentShift'
        ));
    }
}
