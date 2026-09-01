<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepairController extends BaseApiController
{
    /**
     * Get all repair tickets
     * GET /api/v1/repairs
     */
    public function index(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $status = $request->input('status');
        $search = $request->input('search');

        $query = DB::table('pos_repair_tickets')
            ->where('business_id', $business_id)
            ->whereNull('deleted_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%")
                  ->orWhere('customer_phone', 'LIKE', "%{$search}%")
                  ->orWhere('device_type', 'LIKE', "%{$search}%");
            });
        }

        $repairs = $query->orderByDesc('created_at')->get();

        return $this->successResponse(['repairs' => $repairs]);
    }

    /**
     * Create repair ticket
     * POST /api/v1/repairs
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'customer_name' => 'required|string',
            'device_type' => 'required|string',
            'device_brand' => 'required|string',
            'reported_issue' => 'required|string',
        ]);

        $business_id = $this->getBusinessId($request);
        
        // Generate ticket number
        $lastTicket = DB::table('pos_repair_tickets')
            ->where('business_id', $business_id)
            ->orderByDesc('id')
            ->value('ticket_number');
        
        $nextNum = 1;
        if ($lastTicket && preg_match('/RPR-(\d+)/', $lastTicket, $m)) {
            $nextNum = intval($m[1]) + 1;
        }
        $ticketNumber = 'RPR-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $id = DB::table('pos_repair_tickets')->insertGetId([
            'business_id' => $business_id,
            'contact_id' => $request->input('contact_id'),
            'location_id' => $request->input('location_id'),
            'ticket_number' => $ticketNumber,
            'customer_name' => $request->input('customer_name'),
            'customer_phone' => $request->input('customer_phone'),
            'customer_email' => $request->input('customer_email'),
            'device_type' => $request->input('device_type'),
            'device_brand' => $request->input('device_brand'),
            'device_model' => $request->input('device_model'),
            'device_serial' => $request->input('device_serial'),
            'device_condition' => $request->input('device_condition'),
            'reported_issue' => $request->input('reported_issue'),
            'estimated_cost' => $request->input('estimated_cost', 0),
            'status' => 'received',
            'priority' => $request->input('priority', 'normal'),
            'technician_id' => $request->input('technician_id'),
            'received_date' => now()->toDateString(),
            'estimated_completion' => $request->input('estimated_completion'),
            'notes' => $request->input('notes'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log initial status
        DB::table('pos_repair_status_history')->insert([
            'repair_ticket_id' => $id,
            'from_status' => null,
            'to_status' => 'received',
            'notes' => 'Repair ticket created',
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse([
            'repair_id' => $id,
            'ticket_number' => $ticketNumber,
        ], 'Repair ticket created');
    }

    /**
     * Update repair status
     * PUT /api/v1/repairs/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:received,diagnosed,waiting_parts,in_repair,testing,completed,ready_pickup,delivered,cancelled',
        ]);

        $business_id = $this->getBusinessId($request);

        $repair = DB::table('pos_repair_tickets')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->first();

        if (!$repair) {
            return $this->errorResponse('Repair ticket not found', 404);
        }

        $newStatus = $request->input('status');

        DB::table('pos_repair_tickets')
            ->where('id', $id)
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

        // Log status change
        DB::table('pos_repair_status_history')->insert([
            'repair_ticket_id' => $id,
            'from_status' => $repair->status,
            'to_status' => $newStatus,
            'notes' => $request->input('notes'),
            'changed_by' => auth()->user()->id ?? null,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse([], 'Status updated to ' . $newStatus);
    }

    /**
     * Update repair costs
     * PUT /api/v1/repairs/{id}/costs
     */
    public function updateCosts(Request $request, int $id): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        DB::table('pos_repair_tickets')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->update([
                'estimated_cost' => $request->input('estimated_cost'),
                'actual_cost' => $request->input('actual_cost'),
                'parts_cost' => $request->input('parts_cost'),
                'labor_cost' => $request->input('labor_cost'),
                'updated_at' => now(),
            ]);

        return $this->successResponse([], 'Costs updated');
    }
}
