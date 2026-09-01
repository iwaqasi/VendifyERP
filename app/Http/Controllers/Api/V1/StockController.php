<?php

namespace App\Http\Controllers\Api\V1;

use App\Product;
use App\VariationLocationDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends BaseApiController
{
    /**
     * Get stock levels for all products at a location
     *
     * @queryParam location_id int required Business location ID. Example: 1
     * @queryParam search string Search by product name or SKU. Example: hair
     * @queryParam low_stock bool Only show low stock items. Example: true
     * @queryParam per_page int Items per page. Example: 50
     */
    public function index(Request $request)
    {
        $business_id = $this->getBusinessId($request);
        $location_id = $request->input('location_id');
        $per_page = $request->input('per_page', 50);

        if (!$location_id) {
            return $this->errorResponse('location_id is required.', 422);
        }

        $query = Product::where('business_id', $business_id)
            ->where('enable_stock', 1)
            ->where('is_inactive', 0)
            ->with(['unit:id,short_name']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->get();

        $stockData = [];
        foreach ($products as $product) {
            $pv = $product->product_variations()->first();
            if (!$pv) continue;

            foreach ($pv->variations as $variation) {
                $stock = VariationLocationDetails::where('variation_id', $variation->id)
                    ->where('location_id', $location_id)
                    ->first();

                $qty = $stock ? (float) $stock->qty_available : 0;
                $min_stock = $product->stock_limit_min ?? 0;

                $row = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $variation->sku,
                    'variation_id' => $variation->id,
                    'variation_name' => $variation->name,
                    'unit' => $product->unit->short_name ?? null,
                    'qty_available' => $qty,
                    'stock_limit_min' => $min_stock,
                    'stock_limit_max' => $product->stock_limit_max ?? 0,
                    'is_low_stock' => $min_stock > 0 && $qty <= $min_stock,
                    'is_out_of_stock' => $qty <= 0,
                ];

                // Filter low stock only
                if ($request->boolean('low_stock') && !$row['is_low_stock'] && !$row['is_out_of_stock']) {
                    continue;
                }

                $stockData[] = $row;
            }
        }

        // Paginate manually
        $page = $request->input('page', 1);
        $paginated = collect($stockData)->slice(($page - 1) * $per_page, $per_page)->values();

        return response()->json([
            'success' => true,
            'message' => 'Stock levels retrieved',
            'data' => $paginated,
            'meta' => [
                'current_page' => $page,
                'last_page' => ceil(count($stockData) / $per_page),
                'per_page' => $per_page,
                'total' => count($stockData),
            ],
        ]);
    }

    /**
     * Get stock for a specific product
     */
    public function productStock(Request $request, int $product_id)
    {
        $business_id = $this->getBusinessId($request);
        $location_id = $request->input('location_id');

        $product = Product::where('business_id', $business_id)
            ->with(['unit:id,short_name', 'product_variations.variations'])
            ->find($product_id);

        if (!$product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $variations = [];
        foreach ($product->product_variations as $pv) {
            foreach ($pv->variations as $variation) {
                $stockQuery = VariationLocationDetails::where('variation_id', $variation->id);

                if ($location_id) {
                    $stockQuery->where('location_id', $location_id);
                }

                $stocks = $stockQuery->get(['location_id', 'qty_available']);

                $variations[] = [
                    'id' => $variation->id,
                    'name' => $variation->name,
                    'sku' => $variation->sku,
                    'stocks' => $stocks->map(function ($s) {
                        return [
                            'location_id' => $s->location_id,
                            'qty_available' => (float) $s->qty_available,
                        ];
                    }),
                ];
            }
        }

        return $this->successResponse([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit->short_name ?? null,
            'enable_stock' => (bool) $product->enable_stock,
            'variations' => $variations,
        ]);
    }
}
