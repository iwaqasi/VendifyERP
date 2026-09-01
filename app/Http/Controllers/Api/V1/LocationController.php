<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends BaseApiController
{
    /**
     * Get all locations for business
     * GET /api/v1/locations
     */
    public function index(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $locations = DB::table('business_locations')
            ->where('business_id', $business_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return $this->successResponse(['locations' => $locations]);
    }

    /**
     * Get stock summary across all locations
     * GET /api/v1/locations/stock-summary
     */
    public function stockSummary(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $locations = DB::table('business_locations')
            ->where('business_id', $business_id)
            ->where('is_active', 1)
            ->get();

        $locationStock = [];
        foreach ($locations as $location) {            $stock = DB::table('variation_location_details')
                ->where('variation_location_details.location_id', $location->id)
                ->selectRaw('SUM(qty_available) as total_stock, COUNT(DISTINCT variation_id) as product_count')
                ->first();

            $locationStock[] = [
                'location_id' => $location->id,
                'location_name' => $location->name,
                'total_stock' => $stock->total_stock ?? 0,
                'product_count' => $stock->product_count ?? 0,
            ];
        }

        return $this->successResponse(['locations' => $locationStock]);
    }

    /**
     * Get stock for a specific product across locations
     * GET /api/v1/locations/stock/{product_id}
     */
    public function productStock(Request $request, int $productId): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $stock = DB::table('variation_location_details')
            ->join('variations', 'variation_location_details.variation_id', '=', 'variations.id')
            ->join('product_variations', 'variations.product_variation_id', '=', 'product_variations.id')
            ->join('business_locations', 'variation_location_details.location_id', '=', 'business_locations.id')
            ->where('product_variations.product_id', $productId)
            ->select(
                'variation_location_details.location_id',
                'business_locations.name as location_name',
                'variations.name as variation_name',
                'variation_location_details.quantity',
                'variation_location_details.qty_available as stock_available'
            )
            ->get();

        return $this->successResponse(['stock' => $stock]);
    }

    /**
     * Create stock transfer between locations
     * POST /api/v1/locations/transfer
     */
    public function createTransfer(Request $request): JsonResponse
    {
        $request->validate([
            'from_location_id' => 'required|integer',
            'to_location_id' => 'required|integer|different:from_location_id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer',
            'products.*.variation_id' => 'required|integer',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        $business_id = $this->getBusinessId($request);

        // Check sufficient stock at source location
        foreach ($request->input('products') as $product) {
            $stock = DB::table('variation_location_details')
                ->where('variation_id', $product['variation_id'])
                ->where('location_id', $request->input('from_location_id'))
                ->value('qty_available');

            if ($stock < $product['quantity']) {
                return $this->errorResponse("Insufficient stock for variation {$product['variation_id']}. Available: {$stock}", 400);
            }
        }

        // Generate transfer reference
        $refNo = 'TRF-' . str_pad(
            DB::table('stock_transfers')->where('business_id', $business_id)->count() + 1,
            5, '0', STR_PAD_LEFT
        );

        // Create transfer record
        $transferId = DB::table('stock_transfers')->insertGetId([
            'business_id' => $business_id,
            'reference_no' => $refNo,
            'location_id' => $request->input('from_location_id'),
            'transfer_location_id' => $request->input('to_location_id'),
            'status' => 'completed',
            'total_amount' => 0,
            'shipping_charges' => $request->input('shipping_charges', 0),
            'additional_notes' => $request->input('notes'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Transfer each product
        foreach ($request->input('products') as $product) {
            // Deduct from source location
            DB::table('variation_location_details')
                ->where('variation_id', $product['variation_id'])
                ->where('location_id', $request->input('from_location_id'))
                ->decrement('stock_available', $product['quantity']);

            // Add to destination location
            $existing = DB::table('variation_location_details')
                ->where('variation_id', $product['variation_id'])
                ->where('location_id', $request->input('to_location_id'))
                ->first();

            if ($existing) {
                DB::table('variation_location_details')
                    ->where('variation_id', $product['variation_id'])
                    ->where('location_id', $request->input('to_location_id'))
                    ->increment('qty_available', $product['quantity']);
            } else {
                DB::table('variation_location_details')->insert([
                    'variation_id' => $product['variation_id'],
                    'location_id' => $request->input('to_location_id'),
                    'quantity' => $product['quantity'],
                    'qty_available' => $product['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Log transfer line
            DB::table('stock_transfer_lines')->insert([
                'stock_transfer_id' => $transferId,
                'product_id' => $product['product_id'],
                'variation_id' => $product['variation_id'],
                'quantity_ordered' => $product['quantity'],
                'quantity_sent' => $product['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->successResponse([
            'transfer_id' => $transferId,
            'reference_no' => $refNo,
        ], 'Transfer completed successfully');
    }

    /**
     * Get transfer history
     * GET /api/v1/locations/transfers
     */
    public function transfers(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $transfers = DB::table('stock_transfers')
            ->leftJoin('business_locations as from_loc', 'stock_transfers.location_id', '=', 'from_loc.id')
            ->leftJoin('business_locations as to_loc', 'stock_transfers.transfer_location_id', '=', 'to_loc.id')
            ->where('stock_transfers.business_id', $business_id)
            ->select(
                'stock_transfers.*',
                'from_loc.name as from_location_name',
                'to_loc.name as to_location_name'
            )
            ->orderByDesc('stock_transfers.created_at')
            ->limit(50)
            ->get();

        return $this->successResponse(['transfers' => $transfers]);
    }

    /**
     * Get location-aware sales report
     * GET /api/v1/locations/sales-report
     */
    public function salesReport(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        // Sales by location
        $salesByLocation = DB::table('transactions')
            ->leftJoin('business_locations', 'transactions.location_id', '=', 'business_locations.id')
            ->where('transactions.business_id', $business_id)
            ->whereDate('transactions.created_at', '>=', $dateFrom)
            ->whereDate('transactions.created_at', '<=', $dateTo)
            ->where('transactions.is_quotation', 0)
            ->where('transactions.type', 'sell')
            ->select(
                'transactions.location_id',
                'business_locations.name as location_name',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(transactions.final_total) as total_sales'),
                DB::raw('SUM(transactions.tax_amount) as total_tax'),
                DB::raw('SUM(transactions.discount_amount) as total_discount')
            )
            ->groupBy('transactions.location_id', 'business_locations.name')
            ->get();

        // Top selling products by location
        $topByLocation = DB::table('transaction_sell_lines')
            ->join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_sell_lines.product_id', '=', 'products.id')
            ->leftJoin('business_locations', 'transactions.location_id', '=', 'business_locations.id')
            ->where('transactions.business_id', $business_id)
            ->whereDate('transactions.created_at', '>=', $dateFrom)
            ->whereDate('transactions.created_at', '<=', $dateTo)
            ->where('transactions.type', 'sell')
            ->select(
                'transactions.location_id',
                'business_locations.name as location_name',
                'products.name as product_name',
                DB::raw('SUM(transaction_sell_lines.quantity) as total_qty'),
                DB::raw('SUM(transaction_sell_lines.unit_price * transaction_sell_lines.quantity) as total_revenue')
            )
            ->groupBy('transactions.location_id', 'business_locations.name', 'products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(20)
            ->get();

        return $this->successResponse([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sales_by_location' => $salesByLocation,
            'top_products_by_location' => $topByLocation,
        ]);
    }
}
