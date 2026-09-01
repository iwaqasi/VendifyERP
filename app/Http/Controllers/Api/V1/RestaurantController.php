<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RestaurantController extends BaseApiController
{
    /**
     * Get all tables
     * GET /api/v1/restaurant/tables
     */
    public function tables(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $tables = DB::table('pos_tables')
            ->where('business_id', $business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse(['tables' => $tables]);
    }

    /**
     * Update table status
     * PUT /api/v1/restaurant/tables/{id}/status
     */
    public function updateTableStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:available,occupied,reserved,maintenance',
        ]);

        $business_id = $this->getBusinessId($request);

        DB::table('pos_tables')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->update([
                'status' => $request->input('status'),
                'updated_at' => now(),
            ]);

        return $this->successResponse([], 'Table status updated');
    }

    /**
     * Get active orders
     * GET /api/v1/restaurant/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $status = $request->input('status');

        $query = DB::table('pos_orders')
            ->where('business_id', $business_id)
            ->whereNull('deleted_at');

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->orderByDesc('created_at')->get();

        // Attach items to each order
        foreach ($orders as $order) {
            $order->items = DB::table('pos_order_items')
                ->where('order_id', $order->id)
                ->get();
        }

        return $this->successResponse(['orders' => $orders]);
    }

    /**
     * Create new order
     * POST /api/v1/restaurant/orders
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $request->validate([
            'order_type' => 'required|string|in:dine_in,takeaway,delivery,drive_through',
            'items' => 'required|array|min:1',
        ]);

        $business_id = $this->getBusinessId($request);
        
        // Generate order number
        $lastOrder = DB::table('pos_orders')
            ->where('business_id', $business_id)
            ->orderByDesc('id')
            ->value('order_number');
        
        $nextNum = 100;
        if ($lastOrder && preg_match('/ORD-(\d+)/', $lastOrder, $m)) {
            $nextNum = intval($m[1]) + 1;
        }
        $orderNumber = 'ORD-' . $nextNum;

        // Calculate totals
        $subtotal = 0;
        $taxAmount = 0;
        foreach ($request->input('items') as $item) {
            $lineTotal = ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1);
            $discount = $item['discount'] ?? 0;
            $subtotal += $lineTotal - $discount;
        }

        $grandTotal = $subtotal + $taxAmount - ($request->input('discount_amount', 0));

        // Create order
        $orderId = DB::table('pos_orders')->insertGetId([
            'business_id' => $business_id,
            'table_id' => $request->input('table_id'),
            'contact_id' => $request->input('contact_id'),
            'location_id' => $request->input('location_id'),
            'order_number' => $orderNumber,
            'order_type' => $request->input('order_type'),
            'guest_count' => $request->input('guest_count', 1),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $request->input('discount_amount', 0),
            'grand_total' => $grandTotal,
            'notes' => $request->input('notes'),
            'delivery_address' => $request->input('delivery_address'),
            'delivery_phone' => $request->input('delivery_phone'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create order items
        foreach ($request->input('items') as $item) {
            $lineTotal = ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1);
            
            $itemId = DB::table('pos_order_items')->insertGetId([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'variation_id' => $item['variation_id'],
                'item_name' => $item['item_name'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? 0,
                'discount' => $item['discount'] ?? 0,
                'tax_amount' => 0,
                'line_total' => $lineTotal,
                'kot_status' => 'pending',
                'course_number' => $item['course_number'],
                'notes' => $item['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update table status if dine-in
        if ($request->input('order_type') === 'dine_in' && $request->input('table_id')) {
            DB::table('pos_tables')
                ->where('id', $request->input('table_id'))
                ->update(['status' => 'occupied', 'updated_at' => now()]);
        }

        return $this->successResponse([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
        ], 'Order created');
    }

    /**
     * Update order status
     * PUT /api/v1/restaurant/orders/{id}/status
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,confirmed,preparing,ready,served,completed,cancelled',
        ]);

        $business_id = $this->getBusinessId($request);
        $newStatus = $request->input('status');

        $order = DB::table('pos_orders')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->first();

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        DB::table('pos_orders')
            ->where('id', $id)
            ->update(['status' => $newStatus, 'updated_at' => now()]);

        // Free table when order is completed or cancelled
        if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
            DB::table('pos_tables')
                ->where('id', $order->table_id)
                ->update(['status' => 'available', 'updated_at' => now()]);
        }

        return $this->successResponse([], 'Order status updated to ' . $newStatus);
    }

    /**
     * Send items to KOT (Kitchen Order Ticket)
     * POST /api/v1/restaurant/kot/send
     */
    public function sendToKot(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer',
            'item_ids' => 'required|array',
        ]);

        $business_id = $this->getBusinessId($request);

        $order = DB::table('pos_orders')
            ->where('id', $request->input('order_id'))
            ->where('business_id', $business_id)
            ->first();

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        $kotNumber = 'KOT-' . str_pad($order->id, 4, '0', STR_PAD_LEFT);

        foreach ($request->input('item_ids') as $itemId) {
            $item = DB::table('pos_order_items')
                ->where('id', $itemId)
                ->where('order_id', $order->id)
                ->first();

            if ($item) {
                // Update item KOT status
                DB::table('pos_order_items')
                    ->where('id', $itemId)
                    ->update([
                        'kot_status' => 'sent',
                        'kot_sent_at' => now(),
                        'updated_at' => now(),
                    ]);

                // Create KOT entry
                DB::table('pos_kot_items')->insert([
                    'order_id' => $order->id,
                    'order_item_id' => $itemId,
                    'kot_number' => $kotNumber,
                    'table_name' => $order->table_id ? "Table " . $order->table_id : null,
                    'order_type' => $order->order_type,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'status' => 'pending',
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Update order status
        DB::table('pos_orders')
            ->where('id', $order->id)
            ->update(['status' => 'preparing', 'updated_at' => now()]);

        return $this->successResponse([
            'kot_number' => $kotNumber,
        ], 'KOT sent to kitchen');
    }
}
