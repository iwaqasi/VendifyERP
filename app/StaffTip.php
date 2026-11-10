<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StaffTip extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pos_staff_tips';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'float',
        'paid_out_at' => 'datetime',
    ];

    /**
     * Staff member (User) receiving the tip.
     */
    public function staff()
    {
        return $this->belongsTo(\App\User::class, 'staff_id');
    }

    /**
     * Appointment / Booking associated with this tip.
     */
    public function booking()
    {
        return $this->belongsTo(\App\Restaurant\Booking::class, 'appointment_id');
    }

    /**
     * POS Transaction linked to this tip.
     */
    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }

    /**
     * Business location.
     */
    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    /**
     * User who processed the payout.
     */
    public function paidOutBy()
    {
        return $this->belongsTo(\App\User::class, 'paid_out_by');
    }

    /**
     * Business this tip belongs to.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }
}
