<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StaffSchedule extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The user (service staff) this schedule belongs to.
     */
    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    /**
     * The business this schedule belongs to.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    /**
     * Get schedule for a specific day of the week.
     * 
     * @param string $day short day name (sat, sun, mon, tue, wed, thu, fri)
     * @return array ['is_off' => bool, 'start_time' => string|null, 'end_time' => string|null]
     */
    public function getDaySchedule($day)
    {
        $day = strtolower($day);
        return [
            'is_off' => $this->{"{$day}_is_off"},
            'start_time' => $this->{"{$day}_start_time"},
            'end_time' => $this->{"{$day}_end_time"},
        ];
    }

    /**
     * Check if staff is working on a given date at a given time.
     * 
     * @param string $date Y-m-d format
     * @param string $startTime HH:MM format
     * @param string $endTime HH:MM format
     * @return bool true if staff is available (not off and no overlap)
     */
    public function isAvailableAt($date, $startTime, $endTime)
    {
        $dayOfWeek = \Carbon::createFromFormat('Y-m-d', $date)->format('D'); // Mon, Tue, etc.
        
        // Map Carbon day names to our column prefixes
        $dayMap = [
            'Sun' => 'sun',
            'Mon' => 'mon',
            'Tue' => 'tue',
            'Wed' => 'wed',
            'Thu' => 'thu',
            'Fri' => 'fri',
            'Sat' => 'sat',
        ];
        
        $dayKey = $dayMap[$dayOfWeek];
        $schedule = $this->getDaySchedule($dayKey);
        
        // If staff is off, not available
        if ($schedule['is_off'] || empty($schedule['start_time']) || empty($schedule['end_time'])) {
            return false;
        }
        
        // Parse times for comparison
        $scheduleStart = \Carbon::createFromFormat('H:i:s', $schedule['start_time'])->format('H:i');
        $scheduleEnd = \Carbon::createFromFormat('H:i:s', $schedule['end_time'])->format('H:i');
        $bookingStart = \Carbon::createFromFormat('H:i', $startTime)->format('H:i');
        $bookingEnd = \Carbon::createFromFormat('H:i', $endTime)->format('H:i');
        
        // Check if booking falls within schedule hours (with overlap check)
        // Booking is valid if: bookingStart >= scheduleStart AND bookingEnd <= scheduleEnd
        return ($bookingStart >= $scheduleStart && $bookingEnd <= $scheduleEnd);
    }

    /**
     * Get the time range for a specific day.
     * 
     * @param string $day short day name
     * @return string|null formatted time range like "08:00 - 16:00" or null if off
     */
    public function getDayTimeRange($day)
    {
        $schedule = $this->getDaySchedule($day);
        if ($schedule['is_off'] || empty($schedule['start_time']) || empty($schedule['end_time'])) {
            return null;
        }
        
        return \Carbon::createFromFormat('H:i:s', $schedule['start_time'])->format('g:i A') . 
               ' - ' . 
               \Carbon::createFromFormat('H:i:s', $schedule['end_time'])->format('g:i A');
    }

    /**
     * Get schedule for a specific date.
     * 
     * @param string $date Y-m-d format
     * @return array ['is_off' => bool, 'start_time' => string|null, 'end_time' => string|null]
     */
    public function getScheduleForDate($date)
    {
        $dayOfWeek = \Carbon::createFromFormat('Y-m-d', $date)->format('D');
        $dayMap = [
            'Sun' => 'sun',
            'Mon' => 'mon',
            'Tue' => 'tue',
            'Wed' => 'wed',
            'Thu' => 'thu',
            'Fri' => 'fri',
            'Sat' => 'sat',
        ];
        return $this->getDaySchedule($dayMap[$dayOfWeek]);
    }

    /**
     * Check if staff is working on a given date (any time).
     * 
     * @param string $date Y-m-d format
     * @return bool
     */
    public function isWorkingOnDate($date)
    {
        $schedule = $this->getScheduleForDate($date);
        return !$schedule['is_off'] && !empty($schedule['start_time']) && !empty($schedule['end_time']);
    }
}
