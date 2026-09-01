<?php

namespace App\Http\Controllers\Api\V1;

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
    public function storeAppointment(Request $request): JsonResponse
    {
        $request->validate([
            'customer_name' => 'required|string',
            'service_name' => 'required|string',
            'appointment_start' => 'required|date',
            'service_duration_minutes' => 'integer|min:1',
        ]);

        $business_id = $this->getBusinessId($request);
        
        $startTime = \Carbon\Carbon::parse($request->input('appointment_start'));
        $duration = $request->input('service_duration_minutes', 30);
        $endTime = $startTime->copy()->addMinutes($duration);

        $contactId = $request->input('contact_id');
        $staffId = $request->input('staff_id');
        $locationId = $request->input('location_id');
        $userId = $request->user()->id;

        $id = DB::table('pos_appointments')->insertGetId([
            'business_id' => $business_id,
            'contact_id' => $contactId,
            'staff_id' => $staffId,
            'location_id' => $locationId,
            'customer_name' => $request->input('customer_name'),
            'customer_phone' => $request->input('customer_phone'),
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
            $bookingContactId = $walkIn ? $walkIn->id : null;
        }

        DB::table('bookings')->insert([
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
}
