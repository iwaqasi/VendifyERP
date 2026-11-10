<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CreateAppointmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaloonController extends BaseApiController
{
    /**
     * Get all appointments for today
     * GET /api/v1/saloon/appointments
     */
    public function appointments(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $date = $request->input('date', now()->toDateString());

        $appointments = DB::table('pos_appointments')
            ->where('business_id', $business_id)
            ->whereDate('appointment_start', $date)
            ->orderBy('appointment_start')
            ->get();

        return $this->successResponse(['appointments' => $appointments]);
    }

    /**
     * Create a new appointment
     * POST /api/v1/saloon/appointments
     */
    public function storeAppointment(CreateAppointmentRequest $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        
        $startTime = \Carbon\Carbon::parse($request->input('appointment_start'));
        $duration = (int) $request->input('service_duration_minutes', 30);
        if ($duration < 1) $duration = 30;
        $endTime = $startTime->copy()->addMinutes($duration);

        $contactId = $request->input('contact_id');
        $staffId = $request->input('staff_id');
        $locationId = $request->input('location_id');
        $userId = $request->user()->id;
        $customerName = $request->input('customer_name');
        $customerPhone = $request->input('customer_phone');

        // Ensure location_id is always resolved
        if (empty($locationId)) {
            $firstLoc = DB::table('business_locations')
                ->where('business_id', $business_id)
                ->where('is_active', 1)
                ->first();
            $locationId = $firstLoc ? $firstLoc->id : 1;
        }

        // Try to match existing customer by phone or name if contact_id not provided
        if (empty($contactId)) {
            $phone = $customerPhone;
            $name = $customerName;
            $existing = null;
            if (!empty($phone)) {
                $existing = DB::table('contacts')
                    ->where('business_id', $business_id)
                    ->where('mobile', $phone)
                    ->first();
            }
            if (!$existing && !empty($name)) {
                // If user typed digits/phone into name field
                $cleanDigits = preg_replace('/[^0-9]/', '', $name);
                if (strlen($cleanDigits) >= 7) {
                    $existing = DB::table('contacts')
                        ->where('business_id', $business_id)
                        ->where('mobile', 'LIKE', "%{$cleanDigits}%")
                        ->first();
                }
                if (!$existing) {
                    $existing = DB::table('contacts')
                        ->where('business_id', $business_id)
                        ->where('name', $name)
                        ->first();
                }
            }
            if ($existing) {
                $contactId = $existing->id;
                $customerName = $existing->name;
                $customerPhone = $existing->mobile ?? $customerPhone;
            }
        }

        $id = DB::table('pos_appointments')->insertGetId([
            'business_id' => $business_id,
            'contact_id' => $contactId,
            'staff_id' => $staffId,
            'location_id' => $locationId,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_email' => $request->input('customer_email'),
            'service_name' => $request->input('service_name'),
            'service_price' => $request->input('service_price', 0),
            'service_duration_minutes' => $duration,
            'appointment_start' => $startTime,
            'appointment_end' => $endTime,
            'status' => 'scheduled',
            'notes' => $request->input('notes'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Also save to the bookings table so it appears on the Laravel Bookings page
        $serviceNote = $request->input('service_name', '');
        $notes = $request->input('notes', '');
        if (!empty($request->input('service_price'))) {
            $serviceNote .= ' (KD ' . number_format((float)$request->input('service_price', 0), 3) . ')';
        }
        if (!empty($notes)) {
            $serviceNote .= ' — ' . $notes;
        }

        // Use Walk-In Customer if no contact_id provided
        $bookingContactId = $contactId;
        if (empty($bookingContactId)) {
            $walkIn = DB::table('contacts')
                ->where('business_id', $business_id)
                ->where('name', 'LIKE', '%Walk-In%')
                ->first();
            if (!$walkIn) {
                $walkIn = DB::table('contacts')
                    ->where('business_id', $business_id)
                    ->where('type', 'customer')
                    ->first();
            }
            $bookingContactId = $walkIn ? $walkIn->id : null;
        }

        $bookingId = DB::table('bookings')->insertGetId([
            'contact_id' => $bookingContactId,
            'waiter_id' => $staffId,
            'business_id' => $business_id,
            'location_id' => $locationId,
            'created_by' => $userId,
            'booking_start' => $startTime,
            'booking_end' => $endTime,
            'booking_status' => 'confirmed',
            'booking_note' => $serviceNote,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Also insert into booking_services so web shows service line immediately
        $svcName = $request->input('service_name');
        $svcPrice = (float) $request->input('service_price', 0);
        if (!empty($svcName)) {
            $mProd = DB::table('products')->where('business_id', $business_id)->where('name', $svcName)->first();
            if (!$mProd) {
                $mProd = DB::table('products')->where('business_id', $business_id)->where('name', 'LIKE', '%' . $svcName . '%')->first();
            }
            if ($mProd) {
                DB::table('booking_services')->insert([
                    'booking_id' => $bookingId,
                    'product_id' => $mProd->id,
                    'service_staff_id' => $staffId,
                    'quantity' => 1,
                    'unit_price' => $svcPrice,
                    'line_total' => $svcPrice,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $this->successResponse(
            ['appointment_id' => $id],
            'Appointment created successfully'
        );
    }

    /**
     * Update appointment status
     * PUT /api/v1/saloon/appointments/{id}/status
     */
    public function updateAppointmentStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:scheduled,confirmed,checked_in,in_progress,completed,cancelled,no_show',
        ]);

        $business_id = $this->getBusinessId($request);

        $newStatus = $request->input('status');

        DB::table('pos_appointments')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

        // Map POS status to booking status
        $statusMap = [
            'scheduled' => 'confirmed',
            'confirmed' => 'confirmed',
            'checked_in' => 'confirmed',
            'in_progress' => 'confirmed',
            'completed' => 'finished',
            'cancelled' => 'cancelled',
            'no_show' => 'cancelled',
        ];
        $bookingStatus = $statusMap[$newStatus] ?? 'confirmed';

        // Update the matching booking by time and business
        $appointment = DB::table('pos_appointments')->where('id', $id)->first();
        if ($appointment) {
            DB::table('bookings')
                ->where('business_id', $business_id)
                ->where('contact_id', $appointment->contact_id)
                ->where('booking_start', $appointment->appointment_start)
                ->update(['booking_status' => $bookingStatus, 'updated_at' => now()]);
        }

        return $this->successResponse([], 'Status updated');
    }

    /**
     * Get all staff
     * GET /api/v1/saloon/staff
     */
    public function staff(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        // Fetch service staff from users table (users with Service Staff role)
        $users = \App\User::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereHas('roles', function ($q) use ($business_id) {
                $q->where('name', 'LIKE', 'Service Staff#' . $business_id)
                  ->orWhere('name', 'service_staff');
            })
            ->get([
                'id', 'first_name', 'last_name', 'email',
                'service_staff_pin', 'is_enable_service_staff_pin',
            ]);

        $staff = $users->map(function ($user) {
            $colors = ['#E91E63', '#9C27B0', '#00BCD4', '#4CAF50', '#FF9800', '#2196F3'];
            return [
                'id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'specialization' => 'Service Staff',
                'color' => $colors[$user->id % count($colors)],
                'is_active' => true,
            ];
        })->toArray();

        return $this->successResponse(['staff' => $staff]);
    }

    /**
     * Add staff
     * POST /api/v1/saloon/staff
     */
    public function storeStaff(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $business_id = $this->getBusinessId($request);

        $id = DB::table('pos_staff')->insertGetId([
            'business_id' => $business_id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'specialization' => $request->input('specialization'),
            'color' => $request->input('color', '#00BCD4'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse(['staff_id' => $id], 'Staff added');
    }

    /**
     * Start service timer
     * POST /api/v1/saloon/service/start
     */
    public function startService(Request $request): JsonResponse
    {
        $request->validate([
            'appointment_id' => 'required|integer',
            'staff_id' => 'required|integer',
        ]);

        // Mark appointment as in_progress
        DB::table('pos_appointments')
            ->where('id', $request->input('appointment_id'))
            ->update(['status' => 'in_progress', 'updated_at' => now()]);

        // Create service session
        $sessionId = DB::table('pos_service_sessions')->insertGetId([
            'appointment_id' => $request->input('appointment_id'),
            'staff_id' => $request->input('staff_id'),
            'started_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse(['session_id' => $sessionId], 'Service started');
    }

    /**
     * Complete service timer
     * POST /api/v1/saloon/service/complete
     */
    public function completeService(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|integer',
        ]);

        $session = DB::table('pos_service_sessions')
            ->where('id', $request->input('session_id'))
            ->first();

        if (!$session) {
            return $this->errorResponse('Session not found', 404);
        }

        $startedAt = \Carbon\Carbon::parse($session->started_at);
        $durationSeconds = $startedAt->diffInSeconds(now());

        DB::table('pos_service_sessions')
            ->where('id', $session->id)
            ->update([
                'ended_at' => now(),
                'duration_seconds' => $durationSeconds,
                'status' => 'completed',
                'updated_at' => now(),
            ]);

        // Mark appointment as completed
        DB::table('pos_appointments')
            ->where('id', $session->appointment_id)
            ->update(['status' => 'completed', 'updated_at' => now()]);

        return $this->successResponse([
            'duration_seconds' => $durationSeconds,
        ], 'Service completed');
    }

    /**
     * Get staff commission report and calculations
     * GET /api/v1/saloon/commissions
     */
    public function commissions(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $staffId = $request->input('staff_id');

        $staffQuery = \App\User::where('business_id', $business_id)
            ->where('status', 'active');

        if ($staffId) {
            $staffQuery->where('id', $staffId);
        } else {
            $staffQuery->where(function ($q) use ($business_id) {
                $q->where('is_cmmsn_agnt', 1)
                  ->orWhere('cmmsn_percent', '>', 0)
                  ->orWhereHas('roles', function ($rq) use ($business_id) {
                      $rq->where('name', 'LIKE', 'Service Staff#' . $business_id)
                        ->orWhere('name', 'service_staff');
                  });
            });
        }

        $staffMembers = $staffQuery->get();

        $results = [];
        $totalBusinessCommission = 0;
        $totalBusinessServiceRevenue = 0;

        foreach ($staffMembers as $staff) {
            $rate = (float) ($staff->cmmsn_percent ?? 0);

            $lines = DB::table('transaction_sell_lines as sl')
                ->join('transactions as t', 'sl.transaction_id', '=', 't.id')
                ->leftJoin('products as p', 'sl.product_id', '=', 'p.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where(function ($q) use ($staff) {
                    $q->where('sl.res_service_staff_id', $staff->id)
                      ->orWhere('t.commission_agent', $staff->id);
                })
                ->whereDate('t.transaction_date', '>=', $startDate)
                ->whereDate('t.transaction_date', '<=', $endDate)
                ->select([
                    'sl.id',
                    'sl.transaction_id',
                    't.invoice_no',
                    't.transaction_date',
                    'sl.quantity',
                    'sl.unit_price_inc_tax',
                    'p.name as product_name',
                ])
                ->get();

            $staffRevenue = 0;
            $serviceItems = [];

            foreach ($lines as $l) {
                $lineTotal = (float) $l->quantity * (float) $l->unit_price_inc_tax;
                $staffRevenue += $lineTotal;
                $serviceItems[] = [
                    'line_id'          => $l->id,
                    'invoice_no'       => $l->invoice_no,
                    'date'             => $l->transaction_date,
                    'service_name'     => $l->product_name ?? 'Service',
                    'quantity'         => (float) $l->quantity,
                    'unit_price'       => (float) $l->unit_price_inc_tax,
                    'total'            => (float) $lineTotal,
                    'line_commission'  => round($lineTotal * ($rate / 100), 3),
                ];
            }

            $commissionEarned = round($staffRevenue * ($rate / 100), 3);
            $totalBusinessCommission += $commissionEarned;
            $totalBusinessServiceRevenue += $staffRevenue;

            $results[] = [
                'staff_id'           => $staff->id,
                'staff_name'         => trim($staff->first_name . ' ' . $staff->last_name),
                'commission_percent' => $rate,
                'total_services'     => $lines->count(),
                'total_revenue'      => round($staffRevenue, 3),
                'commission_earned'  => $commissionEarned,
                'services'           => $serviceItems,
            ];
        }

        return $this->successResponse([
            'start_date'                 => $startDate,
            'end_date'                   => $endDate,
            'total_business_revenue'     => round($totalBusinessServiceRevenue, 3),
            'total_business_commission'  => round($totalBusinessCommission, 3),
            'staff_commissions'          => $results,
        ]);
    }

    /**
     * Update appointment services (Scenario 1: add-ons mid appointment)
     * PUT /api/v1/saloon/appointments/{id}/services
     */
    public function updateAppointmentServices(Request $request, int $id): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $appointment = DB::table('pos_appointments')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->first();

        if (!$appointment) {
            return $this->errorResponse('Appointment not found', 404);
        }

        $request->validate([
            'services'                  => 'nullable|array',
            'services.*.name'           => 'required_with:services|string',
            'services.*.price'          => 'nullable|numeric|min:0',
            'services.*.duration_minutes' => 'nullable|integer|min:1',
            'service_name'              => 'nullable|string',
            'service_price'             => 'nullable|numeric|min:0',
            'service_duration_minutes'  => 'nullable|integer|min:1',
        ]);

        if ($request->has('services') && is_array($request->input('services'))) {
            $services = $request->input('services');
            $serviceNames = implode(', ', array_map(fn($s) => $s['name'], $services));
            $servicePrice = array_sum(array_map(fn($s) => (float)($s['price'] ?? 0), $services));
            $durationMinutes = array_sum(array_map(fn($s) => (int)($s['duration_minutes'] ?? 30), $services));
        } else {
            $serviceNames = $request->input('service_name', $appointment->service_name);
            $servicePrice = $request->input('service_price', $appointment->service_price);
            $durationMinutes = $request->input('service_duration_minutes', $appointment->service_duration_minutes);
        }

        $startTime = \Carbon\Carbon::parse($appointment->appointment_start);
        $endTime = $startTime->copy()->addMinutes($durationMinutes);

        DB::table('pos_appointments')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->update([
                'service_name'             => $serviceNames,
                'service_price'            => $servicePrice,
                'service_duration_minutes' => $durationMinutes,
                'appointment_end'          => $endTime,
                'updated_at'               => now(),
            ]);

        // Also update matching booking
        $serviceNote = $serviceNames . ' (KD ' . number_format((float)$servicePrice, 3) . ')';
        if (!empty($appointment->notes)) {
            $serviceNote .= ' — ' . $appointment->notes;
        }

        $matchingBooking = DB::table('bookings')
            ->where('business_id', $business_id)
            ->where('contact_id', $appointment->contact_id)
            ->where('booking_start', $appointment->appointment_start)
            ->first();

        if ($matchingBooking) {
            DB::table('bookings')
                ->where('id', $matchingBooking->id)
                ->update([
                    'booking_note' => $serviceNote,
                    'booking_end'  => $endTime,
                    'updated_at'   => now(),
                ]);

            // Sync booking_services
            DB::table('booking_services')->where('booking_id', $matchingBooking->id)->delete();
            if ($request->has('services') && is_array($request->input('services'))) {
                foreach ($request->input('services') as $s) {
                    $sName = $s['name'] ?? '';
                    $sPrice = (float) ($s['price'] ?? 0);
                    $mProd = DB::table('products')->where('business_id', $business_id)->where('name', $sName)->first();
                    if (!$mProd) {
                        $mProd = DB::table('products')->where('business_id', $business_id)->where('name', 'LIKE', '%' . $sName . '%')->first();
                    }
                    if ($mProd) {
                        DB::table('booking_services')->insert([
                            'booking_id' => $matchingBooking->id,
                            'product_id' => $mProd->id,
                            'service_staff_id' => $appointment->staff_id,
                            'quantity' => 1,
                            'unit_price' => $sPrice,
                            'line_total' => $sPrice,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        $updated = DB::table('pos_appointments')->where('id', $id)->first();

        return $this->successResponse([
            'appointment' => $updated,
        ], 'Services updated successfully');
    }

    /**
     * Get staff tips and payout summary (Scenario 2)
     * GET /api/v1/saloon/tips
     */
    public function tips(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $staffId = $request->input('staff_id');
        $status = $request->input('status'); // 'all', 'pending', 'paid_out'

        $query = DB::table('pos_staff_tips as st')
            ->join('users as u', 'st.staff_id', '=', 'u.id')
            ->leftJoin('transactions as t', 'st.transaction_id', '=', 't.id')
            ->where('st.business_id', $business_id)
            ->whereDate('st.created_at', '>=', $startDate)
            ->whereDate('st.created_at', '<=', $endDate);

        if ($staffId) {
            $query->where('st.staff_id', $staffId);
        }

        if ($status && in_array($status, ['pending', 'paid_out'])) {
            $query->where('st.status', $status);
        }

        $tips = $query->select([
            'st.id',
            'st.staff_id',
            DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as staff_name"),
            'st.transaction_id',
            't.invoice_no',
            'st.appointment_id',
            'st.amount',
            'st.payment_method',
            'st.status',
            'st.paid_out_at',
            'st.notes',
            'st.created_at',
        ])
        ->orderByDesc('st.created_at')
        ->get();

        $totalCollected = (float) $tips->sum('amount');
        $totalPending = (float) $tips->where('status', 'pending')->sum('amount');
        $totalPaidOut = (float) $tips->where('status', 'paid_out')->sum('amount');

        // Group summary by staff member
        $byStaff = [];
        $grouped = $tips->groupBy('staff_id');
        foreach ($grouped as $sId => $staffTips) {
            $first = $staffTips->first();
            $byStaff[] = [
                'staff_id'      => $sId,
                'staff_name'    => trim($first->staff_name),
                'total_tips'    => round((float) $staffTips->sum('amount'), 3),
                'pending_tips'  => round((float) $staffTips->where('status', 'pending')->sum('amount'), 3),
                'paid_out_tips' => round((float) $staffTips->where('status', 'paid_out')->sum('amount'), 3),
                'count'         => $staffTips->count(),
            ];
        }

        return $this->successResponse([
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'total_collected' => round($totalCollected, 3),
            'total_pending'   => round($totalPending, 3),
            'total_paid_out'  => round($totalPaidOut, 3),
            'tips'            => $tips,
            'by_staff'        => $byStaff,
        ]);
    }

    /**
     * Store tip directly (Scenario 2)
     * POST /api/v1/saloon/tips
     */
    public function storeTip(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id'       => 'required|integer',
            'amount'         => 'required|numeric|min:0.001',
            'payment_method' => 'nullable|string',
            'transaction_id' => 'nullable|integer',
            'appointment_id' => 'nullable|integer',
            'location_id'    => 'nullable|integer',
            'notes'          => 'nullable|string|max:500',
        ]);

        $business_id = $this->getBusinessId($request);

        $id = DB::table('pos_staff_tips')->insertGetId([
            'business_id'    => $business_id,
            'staff_id'       => $request->input('staff_id'),
            'transaction_id' => $request->input('transaction_id'),
            'appointment_id' => $request->input('appointment_id'),
            'location_id'    => $request->input('location_id'),
            'amount'         => (float) $request->input('amount'),
            'payment_method' => $request->input('payment_method', 'cash'),
            'status'         => 'pending',
            'notes'          => $request->input('notes'),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return $this->successResponse(['tip_id' => $id], 'Tip recorded successfully');
    }

    /**
     * Mark tips as paid out to staff (Scenario 2)
     * POST /api/v1/saloon/tips/payout
     */
    public function payoutTips(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => 'required|integer',
            'tip_ids'  => 'nullable|array',
            'notes'    => 'nullable|string|max:500',
        ]);

        $business_id = $this->getBusinessId($request);
        $staffId = $request->input('staff_id');
        $tipIds = $request->input('tip_ids');

        $query = DB::table('pos_staff_tips')
            ->where('business_id', $business_id)
            ->where('staff_id', $staffId)
            ->where('status', 'pending');

        if (!empty($tipIds)) {
            $query->whereIn('id', $tipIds);
        }

        $pendingTips = $query->get();
        $payoutAmount = (float) $pendingTips->sum('amount');
        $count = $pendingTips->count();

        if ($count === 0) {
            return $this->errorResponse('No pending tips found to pay out for this staff member', 422);
        }

        $query->update([
            'status'      => 'paid_out',
            'paid_out_at' => now(),
            'paid_out_by' => $request->user()->id,
            'notes'       => DB::raw("CONCAT(COALESCE(notes, ''), ' [Paid out on " . now()->toDateTimeString() . "]')"),
            'updated_at'  => now(),
        ]);

        return $this->successResponse([
            'staff_id'        => $staffId,
            'paid_out_amount' => round($payoutAmount, 3),
            'tips_count'      => $count,
        ], "Paid out KD " . number_format($payoutAmount, 3) . " in tips to staff member.");
    }

    /**
     * Get End of Day Staff Performance & Summary (Scenario 3)
     * GET /api/v1/saloon/staff-daily-summary
     */
    public function staffDailySummary(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $date = $request->input('date', now()->toDateString());
        $locationId = $request->input('location_id');

        // Fetch active service staff for this business
        $staffMembers = \App\User::where('business_id', $business_id)
            ->where('status', 'active')
            ->where(function ($q) use ($business_id) {
                $q->where('is_cmmsn_agnt', 1)
                  ->orWhere('cmmsn_percent', '>', 0)
                  ->orWhereHas('roles', function ($rq) use ($business_id) {
                      $rq->where('name', 'LIKE', 'Service Staff#' . $business_id)
                        ->orWhere('name', 'service_staff');
                  });
            })
            ->get();

        $colors = ['#E91E63', '#9C27B0', '#00BCD4', '#4CAF50', '#FF9800', '#2196F3', '#009688', '#3F51B5'];

        $staffSummaries = [];
        $salonTotalRevenue = 0;
        $salonTotalServices = 0;
        $salonTotalCommission = 0;
        $salonTotalTips = 0;

        foreach ($staffMembers as $index => $staff) {
            $rate = (float) ($staff->cmmsn_percent ?? 0);

            // Fetch transaction sell lines served by this staff on that date
            $linesQuery = DB::table('transaction_sell_lines as sl')
                ->join('transactions as t', 'sl.transaction_id', '=', 't.id')
                ->leftJoin('products as p', 'sl.product_id', '=', 'p.id')
                ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->whereDate('t.transaction_date', $date)
                ->where(function ($q) use ($staff) {
                    $q->where('sl.res_service_staff_id', $staff->id)
                      ->orWhere('t.res_waiter_id', $staff->id)
                      ->orWhere('t.commission_agent', $staff->id);
                });

            if ($locationId) {
                $linesQuery->where('t.location_id', $locationId);
            }

            $lines = $linesQuery->select([
                'sl.id',
                'sl.transaction_id',
                't.invoice_no',
                't.transaction_date',
                'sl.quantity',
                'sl.unit_price_inc_tax',
                'p.name as service_name',
                'c.name as customer_name',
            ])->get();

            // Also check appointments completed on this date
            $completedAppts = DB::table('pos_appointments')
                ->where('business_id', $business_id)
                ->where('staff_id', $staff->id)
                ->whereDate('appointment_start', $date)
                ->whereIn('status', ['completed', 'finished'])
                ->get();

            $serviceItems = [];
            $staffRevenue = 0;

            foreach ($lines as $l) {
                $lineTotal = (float) $l->quantity * (float) $l->unit_price_inc_tax;
                $staffRevenue += $lineTotal;
                $serviceItems[] = [
                    'source'       => 'sale',
                    'invoice_no'   => $l->invoice_no,
                    'customer'     => $l->customer_name ?? 'Walk-in Customer',
                    'service_name' => $l->service_name ?? 'Service',
                    'quantity'     => (float) $l->quantity,
                    'unit_price'   => (float) $l->unit_price_inc_tax,
                    'total'        => round($lineTotal, 3),
                    'commission'   => round($lineTotal * ($rate / 100), 3),
                    'time'         => substr((string)$l->transaction_date, 11, 5),
                ];
            }

            // Fetch tips for this staff member on this date
            $tipsQuery = DB::table('pos_staff_tips')
                ->where('business_id', $business_id)
                ->where('staff_id', $staff->id)
                ->whereDate('created_at', $date);

            $tipsList = $tipsQuery->get();
            $staffTipsTotal = (float) $tipsList->sum('amount');
            $staffTipsPending = (float) $tipsList->where('status', 'pending')->sum('amount');
            $staffTipsPaid = (float) $tipsList->where('status', 'paid_out')->sum('amount');

            $commissionEarned = round($staffRevenue * ($rate / 100), 3);
            $totalPayout = round($commissionEarned + $staffTipsTotal, 3);

            $servicesCount = $lines->count();
            if ($servicesCount === 0 && $completedAppts->isNotEmpty()) {
                $servicesCount = $completedAppts->count();
            }

            $staffSummaries[] = [
                'staff_id'           => $staff->id,
                'staff_name'         => trim($staff->first_name . ' ' . $staff->last_name),
                'specialization'     => 'Stylist / Staff',
                'color'              => $colors[$index % count($colors)],
                'commission_percent' => $rate,
                'services_count'     => $servicesCount,
                'service_revenue'    => round($staffRevenue, 3),
                'commission_earned'  => $commissionEarned,
                'tips_total'         => round($staffTipsTotal, 3),
                'tips_pending'       => round($staffTipsPending, 3),
                'tips_paid_out'      => round($staffTipsPaid, 3),
                'total_payout'       => $totalPayout,
                'services'           => $serviceItems,
            ];

            $salonTotalRevenue += $staffRevenue;
            $salonTotalServices += $servicesCount;
            $salonTotalCommission += $commissionEarned;
            $salonTotalTips += $staffTipsTotal;
        }

        return $this->successResponse([
            'date'                => $date,
            'total_services'      => $salonTotalServices,
            'total_revenue'       => round($salonTotalRevenue, 3),
            'total_commission'    => round($salonTotalCommission, 3),
            'total_tips'          => round($salonTotalTips, 3),
            'total_staff_payout'  => round($salonTotalCommission + $salonTotalTips, 3),
            'staff'               => $staffSummaries,
        ]);
    }
}
