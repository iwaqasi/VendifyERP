<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BookingService extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The booking this service belongs to.
     */
    public function booking()
    {
        return $this->belongsTo(\App\Restaurant\Booking::class, 'booking_id');
    }

    /**
     * The product/service being booked.
     */
    public function product()
    {
        return $this->belongsTo(\App\Product::class, 'product_id');
    }

    /**
     * The service staff assigned to this service.
     */
    public function serviceStaff()
    {
        return $this->belongsTo(\App\User::class, 'service_staff_id');
    }
}
