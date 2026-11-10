<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CreateRestaurantOrderRequest;
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
    public function storeOrder(CreateRestaurantOrderRequest $request): JsonResponse
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
                'station' => $item['station'] ?? 'kitchen',
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
                    'station' => $item->station ?? 'kitchen',
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

    /**
     * Get KDS active orders for kitchen, bar, dessert stations
     * GET /api/v1/restaurant/kds/orders
     */
    public function kdsOrders(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $station = $request->input('station', 'all');
        $location_id = $request->input('location_id');

        $query = DB::table('pos_kot_items as ki')
            ->join('pos_orders as o', 'ki.order_id', '=', 'o.id')
            ->leftJoin('pos_tables as t', 'o.table_id', '=', 't.id')
            ->where('o.business_id', $business_id)
            ->whereIn('ki.status', ['pending', 'preparing', 'ready'])
            ->select([
                'ki.id as kot_item_id',
                'ki.order_id',
                'ki.kot_number',
                'ki.station',
                'ki.item_name',
                'ki.quantity',
                'ki.notes as item_notes',
                'ki.status as item_status',
                'ki.sent_at',
                'ki.started_at',
                'ki.completed_at',
                'o.order_number',
                'o.order_type',
                'o.notes as order_notes',
                't.name as table_name',
                'o.created_at as order_created_at',
            ]);

        if ($station !== 'all') {
            $query->where('ki.station', $station);
        }

        if ($location_id) {
            $query->where('o.location_id', $location_id);
        }

        $items = $query->orderBy('ki.sent_at', 'asc')->get();

        $ordersMap = [];
        foreach ($items as $item) {
            $orderId = $item->order_id;
            if (!isset($ordersMap[$orderId])) {
                $sentTime = \Carbon\Carbon::parse($item->sent_at ?? $item->order_created_at);
                $ordersMap[$orderId] = [
                    'order_id'        => $orderId,
                    'order_number'    => $item->order_number,
                    'kot_number'      => $item->kot_number,
                    'order_type'      => $item->order_type,
                    'table_name'      => $item->table_name ?? 'Walk-in',
                    'order_notes'     => $item->order_notes,
                    'sent_at'         => $item->sent_at,
                    'elapsed_minutes' => round($sentTime->diffInMinutes(now())),
                    'items'           => [],
                ];
            }

            $ordersMap[$orderId]['items'][] = [
                'id'           => $item->kot_item_id,
                'name'         => $item->item_name,
                'quantity'     => (float) $item->quantity,
                'station'      => $item->station,
                'status'       => $item->item_status,
                'notes'        => $item->item_notes,
                'started_at'   => $item->started_at,
                'completed_at' => $item->completed_at,
            ];
        }

        return $this->successResponse([
            'station' => $station,
            'tickets' => array_values($ordersMap),
        ]);
    }

    /**
     * Transition a KDS item status
     * PUT /api/v1/restaurant/kds/items/{id}/status
     */
    public function updateKdsItemStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,preparing,ready,served,cancelled',
        ]);

        $business_id = $this->getBusinessId($request);
        $newStatus = $request->input('status');

        $kotItem = DB::table('pos_kot_items as ki')
            ->join('pos_orders as o', 'ki.order_id', '=', 'o.id')
            ->where('ki.id', $id)
            ->where('o.business_id', $business_id)
            ->select('ki.*')
            ->first();

        if (!$kotItem) {
            return $this->errorResponse('KOT item not found', 404);
        }

        $updates = [
            'status'     => $newStatus,
            'updated_at' => now(),
        ];

        if ($newStatus === 'preparing' && empty($kotItem->started_at)) {
            $updates['started_at'] = now();
        } elseif ($newStatus === 'ready' && empty($kotItem->completed_at)) {
            $updates['completed_at'] = now();
        }

        DB::table('pos_kot_items')->where('id', $id)->update($updates);

        if (!empty($kotItem->order_item_id)) {
            $orderItemUpdates = ['kot_status' => $newStatus, 'updated_at' => now()];
            if ($newStatus === 'ready') {
                $orderItemUpdates['prepared_at'] = now();
            } elseif ($newStatus === 'served') {
                $orderItemUpdates['served_at'] = now();
            }
            DB::table('pos_order_items')->where('id', $kotItem->order_item_id)->update($orderItemUpdates);
        }

        $remaining = DB::table('pos_kot_items')
            ->where('order_id', $kotItem->order_id)
            ->whereIn('status', ['pending', 'preparing'])
            ->count();

        if ($remaining === 0) {
            DB::table('pos_orders')
                ->where('id', $kotItem->order_id)
                ->update(['status' => 'ready', 'updated_at' => now()]);
        }

        return $this->successResponse([
            'kot_item_id' => $id,
            'status'      => $newStatus,
            'order_ready' => ($remaining === 0),
        ], 'Item status updated to ' . $newStatus);
    }

    /**
     * Mark an entire restaurant order as served
     * PUT /api/v1/restaurant/kds/orders/{order_id}/serve
     */
    public function serveOrder(Request $request, int $order_id): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $order = DB::table('pos_orders')
            ->where('id', $order_id)
            ->where('business_id', $business_id)
            ->first();

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        DB::table('pos_kot_items')
            ->where('order_id', $order_id)
            ->update([
                'status'     => 'served',
                'updated_at' => now(),
            ]);

        DB::table('pos_order_items')
            ->where('order_id', $order_id)
            ->update([
                'kot_status' => 'served',
                'served_at'  => now(),
                'updated_at' => now(),
            ]);

        DB::table('pos_orders')
            ->where('id', $order_id)
            ->update([
                'status'     => 'served',
                'updated_at' => now(),
            ]);

        return $this->successResponse(['order_id' => $order_id], 'Order and all items marked as served');
    }
}
