<?php

namespace App\Services;

use App\Business;
use App\Transaction;
use App\TransactionPayment;
use App\PurchaseLine;
use App\TransactionSellLine;
use App\VariationLocationDetails;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KuwaitXbrlService
{
    /**
     * Compute MOCI Qayd IFRS financial taxonomy metrics for a given date range
     */
    public function computeMetrics(int $business_id, ?string $start_date = null, ?string $end_date = null): array
    {
        $business = Business::find($business_id);
        
        $start = $start_date ? Carbon::parse($start_date)->startOfDay() : Carbon::now()->startOfYear();
        $end = $end_date ? Carbon::parse($end_date)->endOfDay() : Carbon::now()->endOfDay();

        // 1. Gross Sales (Final Sells before discounts)
        $sellsQuery = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween('transaction_date', [$start, $end]);

        $totalSalesIncTax = (float) $sellsQuery->sum('final_total');
        $totalTaxAmount = (float) $sellsQuery->sum('tax_amount');
        $totalDiscountAmount = (float) $sellsQuery->sum('discount_amount');

        // Line-level discounts
        $lineDiscounts = (float) DB::table('transaction_sell_lines')
            ->join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereBetween('transactions.transaction_date', [$start, $end])
            ->selectRaw('SUM(line_discount_amount * quantity) as total_line_discount')
            ->value('total_line_discount') ?? 0.0;

        $discountsAndRebates = round($totalDiscountAmount + $lineDiscounts, 3);
        $revenueFromSaleOfGoods = round($totalSalesIncTax - $totalTaxAmount + $discountsAndRebates, 3);

        // 2. Returns and Allowances
        $returnsQuery = Transaction::where('business_id', $business_id)
            ->where('type', 'sell_return')
            ->whereBetween('transaction_date', [$start, $end]);

        $returnsAndAllowances = round((float) $returnsQuery->sum('final_total'), 3);

        // 3. Net Revenue
        $netRevenue = round($revenueFromSaleOfGoods - $discountsAndRebates - $returnsAndAllowances, 3);

        // 4. Cost of Sales (COGS)
        $cogs = (float) DB::table('transaction_sell_lines')
            ->join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
            ->join('variations', 'transaction_sell_lines.variation_id', '=', 'variations.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereBetween('transactions.transaction_date', [$start, $end])
            ->selectRaw('SUM(transaction_sell_lines.quantity * variations.default_purchase_price) as total_cogs')
            ->value('total_cogs') ?? 0.0;
        $costOfSales = round($cogs, 3);

        // 5. Gross Profit
        $grossProfit = round($netRevenue - $costOfSales, 3);

        // 6. VAT Payable (Output VAT collected minus return VAT)
        $returnTax = (float) $returnsQuery->sum('tax_amount');
        $valueAddedTaxPayable = round(max(0, $totalTaxAmount - $returnTax), 3);

        // 7. Payment breakdowns (KNET vs Cash)
        $payments = DB::table('transaction_payments')
            ->where('business_id', $business_id)
            ->whereBetween('paid_on', [$start, $end])
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->toArray();

        $tradeReceivablesKnet = round((float) ($payments['card'] ?? 0) + (float) ($payments['custom_pay_1'] ?? 0), 3);
        $cashOnHand = round((float) ($payments['cash'] ?? 0), 3);

        // 8. Inventory Valuation (Current Stock * Purchase Price)
        $inventoryValuation = (float) DB::table('variation_location_details')
            ->join('variations', 'variation_location_details.variation_id', '=', 'variations.id')
            ->join('products', 'variations.product_id', '=', 'products.id')
            ->where('products.business_id', $business_id)
            ->where('products.enable_stock', 1)
            ->selectRaw('SUM(variation_location_details.qty_available * variations.default_purchase_price) as total_val')
            ->value('total_val') ?? 0.0;
        $inventories = round($inventoryValuation, 3);

        return [
            'business' => [
                'name' => $business->name ?? 'N/A',
                'cr_number' => $business->kuwait_cr_number ?? 'NOT CONFIGURED',
                'moci_license' => $business->kuwait_moci_license ?? 'NOT CONFIGURED',
                'paci_number' => $business->kuwait_paci_number ?? 'NOT CONFIGURED',
                'currency' => 'KWD',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'generated_at' => Carbon::now()->toIso8601String(),
                'standard' => 'IFRS for SMEs (MOCI Kuwait Qayd v2027.1)',
            ],
            'taxonomy' => [
                'RevenueFromSaleOfGoods' => [
                    'label' => 'Gross Revenue from Sale of Goods & Services',
                    'qayd_element' => 'ifrs-full:RevenueFromSaleOfGoods',
                    'amount' => $revenueFromSaleOfGoods,
                ],
                'DiscountsAndRebates' => [
                    'label' => 'Discounts, Promotions and Rebates Allowed',
                    'qayd_element' => 'ifrs-full:DiscountsAndRebates',
                    'amount' => $discountsAndRebates,
                ],
                'ReturnsAndAllowances' => [
                    'label' => 'Sales Returns, Cancellations & Allowances',
                    'qayd_element' => 'ifrs-full:ReturnsAndAllowances',
                    'amount' => $returnsAndAllowances,
                ],
                'NetRevenue' => [
                    'label' => 'Net Revenue',
                    'qayd_element' => 'ifrs-full:Revenue',
                    'amount' => $netRevenue,
                ],
                'CostOfSales' => [
                    'label' => 'Cost of Sales (COGS)',
                    'qayd_element' => 'ifrs-full:CostOfSales',
                    'amount' => $costOfSales,
                ],
                'GrossProfit' => [
                    'label' => 'Gross Profit / (Loss)',
                    'qayd_element' => 'ifrs-full:GrossProfit',
                    'amount' => $grossProfit,
                ],
                'ValueAddedTaxPayable' => [
                    'label' => 'Value Added Tax (VAT) Payable (5% GCC Ready)',
                    'qayd_element' => 'ifrs-full:ValueAddedTaxPayable',
                    'amount' => $valueAddedTaxPayable,
                ],
                'TradeAndOtherReceivables' => [
                    'label' => 'Trade & Card Settlements (KNET / Credit Cards)',
                    'qayd_element' => 'ifrs-full:TradeAndOtherCurrentReceivables',
                    'amount' => $tradeReceivablesKnet,
                ],
                'CashOnHand' => [
                    'label' => 'Cash and Cash Equivalents',
                    'qayd_element' => 'ifrs-full:CashAndCashEquivalents',
                    'amount' => $cashOnHand,
                ],
                'Inventories' => [
                    'label' => 'Inventories at Cost',
                    'qayd_element' => 'ifrs-full:Inventories',
                    'amount' => $inventories,
                ],
            ],
            'summary' => [
                'total_transactions' => $sellsQuery->count(),
                'total_returns' => $returnsQuery->count(),
                'gross_profit_margin_pct' => $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Generate standard MOCI XBRL CSV
     */
    public function generateCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        // CSV Header
        fputcsv($output, ['# Kuwait MOCI XBRL Financial Audit Export (IFRS for SMEs)']);
        fputcsv($output, ['# Entity Name', $data['business']['name']]);
        fputcsv($output, ['# Kuwait CR Number', $data['business']['cr_number']]);
        fputcsv($output, ['# MOCI License Number', $data['business']['moci_license']]);
        fputcsv($output, ['# PACI Building ID', $data['business']['paci_number']]);
        fputcsv($output, ['# Reporting Period', $data['business']['period_start'] . ' to ' . $data['business']['period_end']]);
        fputcsv($output, ['# Currency', 'KWD']);
        fputcsv($output, []);
        fputcsv($output, ['Taxonomy Element (MOCI / IFRS)', 'Classification Label', 'Amount (KWD)']);

        foreach ($data['taxonomy'] as $item) {
            fputcsv($output, [
                $item['qayd_element'],
                $item['label'],
                number_format($item['amount'], 3, '.', ''),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
