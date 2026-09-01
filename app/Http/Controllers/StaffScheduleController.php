<?php

namespace App\Http\Controllers;

use App\StaffSchedule;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class StaffScheduleController extends Controller
{
    public function __construct()
    {
        // Middleware for authentication
    }

    /**
     * Display a listing of staff schedules.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('user.view') && !auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        
        // Get all service staff for the business
        $service_staff = User::where('business_id', $business_id)
            ->where('is_cmmsn_agnt', 0)
            ->select('id', 'first_name', 'last_name', 'surname')
            ->get();

        // Get existing schedules
        $schedules = StaffSchedule::where('business_id', $business_id)
            ->with('user')
            ->get()
            ->keyBy('user_id');

        return view('staff-schedules.index', compact('service_staff', 'schedules', 'business_id'));
    }

    /**
     * Store a newly created staff schedule.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            
            // Validate input
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'sat_is_off' => 'nullable|boolean',
                'sat_start_time' => 'nullable|date_format:H:i',
                'sat_end_time' => 'nullable|date_format:H:i',
                'sun_is_off' => 'nullable|boolean',
                'sun_start_time' => 'nullable|date_format:H:i',
                'sun_end_time' => 'nullable|date_format:H:i',
                'mon_is_off' => 'nullable|boolean',
                'mon_start_time' => 'nullable|date_format:H:i',
                'mon_end_time' => 'nullable|date_format:H:i',
                'tue_is_off' => 'nullable|boolean',
                'tue_start_time' => 'nullable|date_format:H:i',
                'tue_end_time' => 'nullable|date_format:H:i',
                'wed_is_off' => 'nullable|boolean',
                'wed_start_time' => 'nullable|date_format:H:i',
                'wed_end_time' => 'nullable|date_format:H:i',
                'thu_is_off' => 'nullable|boolean',
                'thu_start_time' => 'nullable|date_format:H:i',
                'thu_end_time' => 'nullable|date_format:H:i',
                'fri_is_off' => 'nullable|boolean',
                'fri_start_time' => 'nullable|date_format:H:i',
                'fri_end_time' => 'nullable|date_format:H:i',
            ]);

            // Create or update schedule
            $schedule = StaffSchedule::updateOrCreate(
                [
                    'user_id' => $request->user_id,
                    'business_id' => $business_id,
                ],
                [
                    'sat_is_off' => $request->has('sat_is_off') ? 1 : 0,
                    'sat_start_time' => $request->sat_start_time,
                    'sat_end_time' => $request->sat_end_time,
                    'sun_is_off' => $request->has('sun_is_off') ? 1 : 0,
                    'sun_start_time' => $request->sun_start_time,
                    'sun_end_time' => $request->sun_end_time,
                    'mon_is_off' => $request->has('mon_is_off') ? 1 : 0,
                    'mon_start_time' => $request->mon_start_time,
                    'mon_end_time' => $request->mon_end_time,
                    'tue_is_off' => $request->has('tue_is_off') ? 1 : 0,
                    'tue_start_time' => $request->tue_start_time,
                    'tue_end_time' => $request->tue_end_time,
                    'wed_is_off' => $request->has('wed_is_off') ? 1 : 0,
                    'wed_start_time' => $request->wed_start_time,
                    'wed_end_time' => $request->wed_end_time,
                    'thu_is_off' => $request->has('thu_is_off') ? 1 : 0,
                    'thu_start_time' => $request->thu_start_time,
                    'thu_end_time' => $request->thu_end_time,
                    'fri_is_off' => $request->has('fri_is_off') ? 1 : 0,
                    'fri_start_time' => $request->fri_start_time,
                    'fri_end_time' => $request->fri_end_time,
                ]
            );

            $output = [
                'success' => 1,
                'msg' => 'Staff schedule saved successfully.',
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());
            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Get available staff for a specific date and time.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getAvailableStaff(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $date = $request->input('date');
        $start_time = $request->input('start_time');
        $end_time = $request->input('end_time');

        // Get all service staff
        $all_staff = User::where('business_id', $business_id)
            ->where('is_cmmsn_agnt', 0)
            ->select('id', 'first_name', 'last_name', 'surname')
            ->get();

        // Get all schedules for this business
        $schedules = StaffSchedule::where('business_id', $business_id)
            ->get()
            ->keyBy('user_id');

        $available_staff = [];

        foreach ($all_staff as $staff) {
            if (isset($schedules[$staff->id])) {
                $schedule = $schedules[$staff->id];
                
                // Check if staff is working on this date
                if ($schedule->isWorkingOnDate($date)) {
                    // Check if requested time falls within schedule
                    $daySchedule = $schedule->getScheduleForDate($date);
                    
                    // Parse times for comparison
                    $scheduleStart = \Carbon::createFromFormat('H:i:s', $daySchedule['start_time'])->format('H:i');
                    $scheduleEnd = \Carbon::createFromFormat('H:i:s', $daySchedule['end_time'])->format('H:i');
                    $bookingStart = \Carbon::createFromFormat('H:i', $start_time)->format('H:i');
                    $bookingEnd = \Carbon::createFromFormat('H:i', $end_time)->format('H:i');
                    
                    // Check if booking is within schedule hours
                    if ($bookingStart >= $scheduleStart && $bookingEnd <= $scheduleEnd) {
                        // Also check for existing bookings at this time
                        $has_conflict = \App\Restaurant\Booking::where('business_id', $business_id)
                            ->where('correspondent_id', $staff->id)
                            ->where('booking_status', '!=', 'cancelled')
                            ->where(function ($q) use ($date, $start_time, $end_time) {
                                $q->where(function ($q2) use ($date, $start_time, $end_time) {
                                    $q2->whereDate('booking_start', $date)
                                        ->whereTime('booking_start', '<', $end_time)
                                        ->whereTime('booking_end', '>', $start_time);
                                });
                            })
                            ->exists();

                        if (!$has_conflict) {
                            $available_staff[] = [
                                'id' => $staff->id,
                                'name' => trim($staff->surname . ' ' . $staff->first_name . ' ' . $staff->last_name),
                                'schedule' => $scheduleStart . ' - ' . $scheduleEnd,
                            ];
                        }
                    }
                }
            } else {
                // No schedule defined - include staff (backwards compatible)
                $available_staff[] = [
                    'id' => $staff->id,
                    'name' => trim($staff->surname . ' ' . $staff->first_name . ' ' . $staff->last_name),
                    'schedule' => null,
                ];
            }
        }

        return response()->json($available_staff);
    }

    /**
     * Check if a staff member is available for a specific time.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkAvailability(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $user_id = $request->input('user_id');
        $date = $request->input('date');
        $start_time = $request->input('start_time');
        $end_time = $request->input('end_time');
        $exclude_booking_id = $request->input('exclude_booking_id'); // For editing existing booking

        $schedule = StaffSchedule::where('user_id', $user_id)
            ->where('business_id', $business_id)
            ->first();

        if (!$schedule) {
            return response()->json([
                'available' => true,
                'message' => 'No schedule defined for this staff member.',
            ]);
        }

        // Check if working on this date
        if (!$schedule->isWorkingOnDate($date)) {
            return response()->json([
                'available' => false,
                'message' => 'Staff member is off on this date.',
            ]);
        }

        // Check if requested time is within schedule
        $daySchedule = $schedule->getScheduleForDate($date);
        $scheduleStart = \Carbon::createFromFormat('H:i:s', $daySchedule['start_time'])->format('H:i');
        $scheduleEnd = \Carbon::createFromFormat('H:i:s', $daySchedule['end_time'])->format('H:i');
        $bookingStart = \Carbon::createFromFormat('H:i', $start_time)->format('H:i');
        $bookingEnd = \Carbon::createFromFormat('H:i', $end_time)->format('H:i');

        if ($bookingStart < $scheduleStart || $bookingEnd > $scheduleEnd) {
            return response()->json([
                'available' => false,
                'message' => 'Requested time is outside staff schedule hours (' . $scheduleStart . ' - ' . $scheduleEnd . ').',
            ]);
        }

        // Check for existing bookings
        $query = \App\Restaurant\Booking::where('business_id', $business_id)
            ->where('correspondent_id', $user_id)
            ->where('booking_status', '!=', 'cancelled')
            ->whereDate('booking_start', $date)
            ->whereTime('booking_start', '<', $end_time)
            ->whereTime('booking_end', '>', $start_time);

        if ($exclude_booking_id) {
            $query->where('id', '!=', $exclude_booking_id);
        }

        $existing_booking = $query->first();

        if ($existing_booking) {
            $booking_start = \Carbon::createFromFormat('Y-m-d H:i:s', $existing_booking->booking_start)->format('g:i A');
            $booking_end = \Carbon::createFromFormat('Y-m-d H:i:s', $existing_booking->booking_end)->format('g:i A');
            
            return response()->json([
                'available' => false,
                'message' => 'Staff member already has a booking from ' . $booking_start . ' to ' . $booking_end . '.',
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'Staff member is available.',
        ]);
    }
}
