<?php

namespace App\Http\Controllers\Api\V1;

use App\Product;
use App\Category;
use App\Brands;
use App\Unit;
use App\TaxRate;
use App\Variation;
use App\VariationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseApiController
{
    /**
     * List products with search, filter, and pagination
     *
     * @queryParam search string Search by name, SKU, barcode. Example: hair
     * @queryParam category_id int Filter by category. Example: 1
     * @queryParam brand_id int Filter by brand. Example: 1
     * @queryParam type string Filter by type: single, variable, service. Example: service
     * @queryParam location_id int Filter by location stock. Example: 1
     * @queryParam per_page int Items per page. Example: 20
     */
    public function index(Request $request)
    {
        $business_id = $this->getBusinessId($request);
        $location_id = $request->input('location_id') ?? $this->getDefaultLocationId($business_id);
        $per_page = $request->input('per_page', 20);

        $query = Product::where('business_id', $business_id)
            ->where('is_inactive', 0)
            ->with(['category:id,name', 'unit:id,short_name']);

        // Search by name, SKU, or barcode
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%")
                  ->orWhere('barcode', 'LIKE', "%{$search}%");
            });
        }

        // Filter by category
        // Filter by category
        if ($category_id = $request->input('category_id')) {
            $query->where('category_id', $category_id);
        }



        // Filter by product type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by location availability
        if ($location_id) {
            $query->where(function ($q) use ($location_id) {
                $q->whereHas('product_locations', function ($subQuery) use ($location_id) {
                    $subQuery->where('product_locations.location_id', $location_id);
                })->orWhere('products.enable_stock', 0);
            });
        }

        // Only products for sale (not_for_selling = 0)
        $query->where('not_for_selling', 0);

        $products = $query->orderBy('name', 'asc')->get();

        // Transform each product for API response
        $transformed = $products->map(function ($product) use ($location_id) {
            return $this->transformProduct($product, $location_id);
        })->values();

        // Manual pagination
        $page = (int) $request->input('page', 1);
        $perPage = (int) $per_page;
        $total = $transformed->count();
        $items = $transformed->slice(($page - 1) * $perPage, $perPage)->values();
        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'links' => [
                'next' => $page < $lastPage ? url('/api/v1/products?page=' . ($page + 1)) : null,
                'prev' => $page > 1 ? url('/api/v1/products?page=' . ($page - 1)) : null,
            ],
        ]);
    }

    /**
     * Get single product with variations
     */
    public function show(Request $request, int $id)
    {
        $business_id = $this->getBusinessId($request);
        $location_id = $request->input('location_id') ?? $this->getDefaultLocationId($business_id);

        $product = Product::where('business_id', $business_id)
            ->with([
                'category:id,name',
                'sub_category:id,name',
                'brand:id,name',
                'unit:id,short_name',
                'product_tax:id,name,amount',
            ])
            ->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found.', 404);
        }

        // Get variations with stock
        $variations = $product->product_variations()
            ->with(['variations'])
            ->get();

        $variationData = [];
        foreach ($variations as $pv) {
            foreach ($pv->variations as $variation) {
                $stock = $this->getVariationStock($variation->id, $location_id);
                $variationData[] = [
                    'id' => $variation->id,
                    'name' => $pv->product_name . ($variation->name != 'DUMMY' ? ' - ' . $variation->name : ''),
                    'sku' => $variation->sub_sku,
                    'barcode' => $variation->sub_sku,
                    'sell_price_inc_tax' => $variation->sell_price_inc_tax,
                    'unit_price_inc_tax' => $variation->unit_price_inc_tax,
                    'qty_available' => $stock,
                    'image' => $variation->image_path,
                ];
            }
        }

        $productData = $this->transformProduct($product, $location_id);
        $productData['variations'] = $variationData;

        return $this->successResponse($productData);
    }

    /**
     * Get variations for a product
     */
    public function variations(Request $request, int $id)
    {
        $business_id = $this->getBusinessId($request);
        $location_id = $request->input('location_id') ?? $this->getDefaultLocationId($business_id);

        $product = Product::where('business_id', $business_id)->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found.', 404);
        }

        $variations = $product->product_variations()
            ->with(['variations'])
            ->get();

        $data = [];
        foreach ($variations as $pv) {
            foreach ($pv->variations as $variation) {
                $stock = $this->getVariationStock($variation->id, $location_id);
                $data[] = [
                    'id' => $variation->id,
                    'name' => $variation->name != 'DUMMY' ? $variation->name : $product->name,
                    'sku' => $variation->sub_sku,
                    'barcode' => $variation->sub_sku,
                    'sell_price_inc_tax' => (float) $variation->sell_price_inc_tax,
                    'unit_price_inc_tax' => (float) $variation->unit_price_inc_tax,
                    'qty_available' => (float) $stock,
                    'enable_stock' => (bool) $product->enable_stock,
                ];
            }
        }

        return $this->successResponse($data);
    }

    /**
     * List categories with product counts
     */
    public function categories(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        // Get parent categories (parent_id = 0 or null)
        $categories = Category::where('business_id', $business_id)
            ->where(function ($q) {
                $q->where('parent_id', 0)
                  ->orWhereNull('parent_id');
            })
            ->get(['id', 'name', 'parent_id', 'image']);

        // Add product count to each category
        $data = $categories->map(function ($category) use ($business_id) {
            $count = Product::where('business_id', $business_id)
                ->where('category_id', $category->id)
                ->where('is_inactive', 0)
                ->count();

            // Also count products in child categories
            $childIds = Category::where('parent_id', $category->id)->pluck('id')->toArray();
            if (!empty($childIds)) {
                $count += Product::where('business_id', $business_id)
                    ->whereIn('category_id', $childIds)
                    ->where('is_inactive', 0)
                    ->count();
            }

            return [
                'id' => $category->id,
                'name' => $category->name,
                'image' => $category->image,
                'image_url' => $category->image_url,
                'product_count' => $count,
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * List brands
     */
    public function brands(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $brands = \App\Brands::where('business_id', $business_id)
            ->get(['id', 'name']);

        return $this->successResponse($brands);
    }

    /**
     * List units
     */
    public function units(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $units = Unit::where('business_id', $business_id)
            ->get(['id', 'actual_name', 'short_name']);

        return $this->successResponse($units);
    }

    /**
     * List tax rates
     */
    public function taxRates(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $taxRates = TaxRate::where('business_id', $business_id)
            ->where('is_tax_group', 0)
            ->get(['id', 'name', 'amount']);

        return $this->successResponse($taxRates);
    }

    /**
     * Upload category image (base64)
     *
     * @bodyParam id int required Category ID. Example: 1
     * @bodyParam image string required Base64 encoded image. Example: data:image/png;base64,iVBOR...
     */
    public function uploadCategoryImage(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $request->validate([
            'id' => 'required|integer',
            'image' => 'required|string',
        ]);

        $category = Category::where('business_id', $business_id)
            ->where('id', $request->id)
            ->first();

        if (!$category) {
            return $this->errorResponse('Category not found.', 404);
        }

        // Delete old image if exists
        $category->deleteImage();

        // Decode base64 image
        $imageData = $request->input('image');
        $imageData = str_replace('data:image/' . explode(';', explode(':', $imageData)[1])[0] . ';base64,', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $imageBinary = base64_decode($imageData);

        // Determine extension
        $extension = 'png';
        if (str_contains($request->input('image'), 'data:image/jpeg')) $extension = 'jpg';
        if (str_contains($request->input('image'), 'data:image/gif')) $extension = 'gif';
        if (str_contains($request->input('image'), 'data:image/webp')) $extension = 'webp';

        // Save file
        $filename = 'category_' . time() . '_' . uniqid() . '.' . $extension;
        file_put_contents(public_path('uploads/img/' . $filename), $imageBinary);

        $category->update(['image' => $filename]);

        return $this->successResponse([
            'id' => $category->id,
            'name' => $category->name,
            'image' => $category->image,
            'image_url' => $category->image_url,
        ], 'Category image uploaded successfully');
    }

    /**
     * Delete category image
     */
    public function deleteCategoryImage(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $request->validate([
            'id' => 'required|integer',
        ]);

        $category = Category::where('business_id', $business_id)
            ->where('id', $request->id)
            ->first();

        if (!$category) {
            return $this->errorResponse('Category not found.', 404);
        }

        $category->deleteImage();
        $category->update(['image' => null]);

        return $this->successResponse(null, 'Category image deleted successfully');
    }

    /**
     * Transform product for API response
     * When stock at current location is 0, includes stock at other locations
     */
    private function transformProduct(Product $product, ?int $location_id): array
    {
        // Get first variation for pricing (prices live on variations)
        $pv = $product->product_variations()->first();
        $variation = $pv ? $pv->variations()->first() : null;

        // Get stock at current location
        $totalStock = 0;
        if ($product->enable_stock && $location_id && $variation) {
            $totalStock = $this->getVariationStock($variation->id, $location_id);
        }

        // Cross-location stock: when current location is out of stock,
        // show stock available at other locations so cashier knows where to transfer from
        $stockAtOtherLocations = [];
        if ($product->enable_stock && $totalStock <= 0 && $location_id && $variation) {
            $stockAtOtherLocations = $this->getStockAtOtherLocations(
                $variation->id,
                $location_id,
                $product->business_id
            );
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'type' => $product->type,
            'category_id' => $product->category_id,
            'category_name' => $product->category->name ?? null,
            'brand_id' => $product->brand_id,
            'brand_name' => $product->brand->name ?? null,
            'unit_id' => $product->unit_id,
            'unit_name' => $product->unit->short_name ?? null,
            'sell_price_inc_tax' => $variation ? (float) $variation->sell_price_inc_tax : 0,
            'product_cost_price' => $variation ? (float) $variation->default_purchase_price : 0,
            'enable_stock' => $product->enable_stock,
            'qty_available' => $totalStock,
            'stock_at_other_locations' => $stockAtOtherLocations,
            'is_flexible_price' => $product->is_flexible_price ?? false,
            'image' => $product->image_url,
            'tax_id' => $product->tax_id,
            'tax_name' => $product->product_tax->name ?? null,
            'tax_rate' => $product->product_tax->amount ?? 0,
            'description' => $product->product_description,
            'is_service_product' => $product->type === 'service',
            'has_variations' => $product->product_variations()->count() > 1,
            'modifiers' => $product->modifier_sets()->get(['id', 'name']),
            'variation_id' => $variation ? $variation->id : null,
            'service_time' => $product->preparation_time_in_minutes ?? null,
        ];
    }

    /**
     * Get stock for a variation at a location
     */
    private function getVariationStock(int $variation_id, ?int $location_id): float
    {
        if (!$location_id) {
            return 0;
        }

        $stock = \App\VariationLocationDetails::where('variation_id', $variation_id)
            ->where('location_id', $location_id)
            ->value('qty_available');

        return (float) ($stock ?? 0);
    }

    /**
     * Get stock at other locations (excluding current location)
     * Used when current location is out of stock — shows where to transfer from
     */
    private function getStockAtOtherLocations(int $variation_id, int $currentLocationId, int $business_id): array
    {
        $stocks = \App\VariationLocationDetails::join('business_locations', 'variation_location_details.location_id', '=', 'business_locations.id')
            ->where('variation_location_details.variation_id', $variation_id)
            ->where('variation_location_details.location_id', '!=', $currentLocationId)
            ->where('business_locations.business_id', $business_id)
            ->where('business_locations.is_active', 1)
            ->where('variation_location_details.qty_available', '>', 0)
            ->select(
                'variation_location_details.location_id',
                'business_locations.name as location_name',
                'variation_location_details.qty_available'
            )
            ->get()
            ->toArray();

        return $stocks;
    }
