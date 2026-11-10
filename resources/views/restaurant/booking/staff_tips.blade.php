@extends('layouts.app')
@section('title', 'Staff Tips & End of Day Summary')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>
        <i class="fa fa-heart text-red"></i> Staff Tips & Daily Summary
        <small>Track salon tips, staff commissions, and payouts</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'index']) }}"><i class="fa fa-calendar"></i> @lang('restaurant.bookings')</a></li>
        <li class="active">Staff Tips</li>
    </ol>
</section>

<!-- Main content -->
<section class="content">
    {{-- Filters Card --}}
    <div class="row">
        <div class="col-sm-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-filter"></i> Filter Tips</h3>
                    <div class="box-tools pull-right">
                        <a href="{{ route('download.apk') }}" target="_blank" class="btn btn-success btn-sm" style="margin-right: 5px;">
                            <i class="fa fa-android"></i> Download POS APK (v{{ config('author.app_version') }})
                        </a>
                        <a href="{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'index']) }}" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Back to Bookings
                        </a>
                    </div>
                </div>
                <div class="box-body">
                    <form method="GET" action="{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'staffTipsReport']) }}" id="tips_filter_form">
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Start Date:</label>
                                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>End Date:</label>
                                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Stylist / Staff:</label>
                                    <select name="staff_id" class="form-control select2" style="width: 100%;">
                                        <option value="">-- All Staff --</option>
                                        @foreach($staff_members as $id => $name)
                                            <option value="{{ $id }}" {{ ($staffId == $id) ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-2">
                                <div class="form-group">
                                    <label>Payout Status:</label>
                                    <select name="status" class="form-control">
                                        <option value="">-- All Statuses --</option>
                                        <option value="pending" {{ ($status == 'pending') ? 'selected' : '' }}>Pending</option>
                                        <option value="paid_out" {{ ($status == 'paid_out') ? 'selected' : '' }}>Paid Out</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-1">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row">
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="info-box bg-aqua">
                <span class="info-box-icon"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Tips Collected</span>
                    <span class="info-box-number" style="font-size: 24px;">KD {{ number_format($totalTips, 3) }}</span>
                    <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                    <span class="progress-description">{{ $tips->count() }} tips recorded</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="info-box bg-yellow">
                <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Pending Staff Payout</span>
                    <span class="info-box-number" style="font-size: 24px;">KD {{ number_format($pendingTips, 3) }}</span>
                    <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                    <span class="progress-description">Awaiting payout</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="info-box bg-green">
                <span class="info-box-icon"><i class="fa fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Tips Paid Out</span>
                    <span class="info-box-number" style="font-size: 24px;">KD {{ number_format($paidTips, 3) }}</span>
                    <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                    <span class="progress-description">Successfully settled</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Staff Summary Table --}}
    @if(!empty($staffSummary))
    <div class="row">
        <div class="col-sm-12">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-users"></i> Staff Payout Breakdown</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover table-bordered">
                        <thead>
                            <tr style="background: #fff8e7;">
                                <th>Stylist / Staff Member</th>
                                <th class="text-center">Tip Count</th>
                                <th class="text-right">Total Tips (KD)</th>
                                <th class="text-right">Paid Out (KD)</th>
                                <th class="text-right">Pending Payout (KD)</th>
                                <th class="text-center" style="width: 160px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($staffSummary as $item)
                            <tr>
                                <td><strong><i class="fa fa-user-circle text-primary"></i> {{ $item['staff_name'] }}</strong></td>
                                <td class="text-center">{{ $item['count'] }}</td>
                                <td class="text-right"><strong>KD {{ number_format($item['total_tips'], 3) }}</strong></td>
                                <td class="text-right text-green">KD {{ number_format($item['paid_tips'], 3) }}</td>
                                <td class="text-right text-yellow" style="font-size: 15px; font-weight: bold;">
                                    KD {{ number_format($item['pending_tips'], 3) }}
                                </td>
                                <td class="text-center">
                                    @if($item['pending_tips'] > 0)
                                    <button type="button" class="btn btn-warning btn-xs btn-payout-staff" data-staff-id="{{ $item['staff_id'] }}" data-staff-name="{{ $item['staff_name'] }}" data-amount="{{ number_format($item['pending_tips'], 3) }}">
                                        <i class="fa fa-hand-holding-usd"></i> Pay Out (KD {{ number_format($item['pending_tips'], 3) }})
                                    </button>
                                    @else
                                    <span class="label label-success"><i class="fa fa-check"></i> Settled</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Detailed Tips List --}}
    <div class="row">
        <div class="col-sm-12">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-list"></i> All Tip Entries</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped" id="staff_tips_detailed_table">
                        <thead>
                            <tr style="background: #f4f6f9;">
                                <th>#</th>
                                <th>Date & Time</th>
                                <th>Stylist / Staff</th>
                                <th>Customer / Booking</th>
                                <th class="text-right">Amount (KD)</th>
                                <th class="text-center">Method</th>
                                <th class="text-center">Status</th>
                                <th>Paid Out Info</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tips as $tip)
                            <tr>
                                <td>{{ $tip->id }}</td>
                                <td>{{ $tip->created_at->format('Y-m-d H:i') }}</td>
                                <td><strong><i class="fa fa-user"></i> {{ $tip->staff->user_full_name ?? ('Staff #' . $tip->staff_id) }}</strong></td>
                                <td>
                                    @if($tip->booking)
                                        <a href="#" class="btn-modal" data-href="{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'show'], [$tip->booking->id]) }}" data-container=".view_modal">
                                            Booking #{{ $tip->booking->id }} ({{ $tip->booking->customer->name ?? 'Walk-In' }})
                                        </a>
                                    @elseif($tip->transaction)
                                        Invoice #{{ $tip->transaction->invoice_no }}
                                    @else
                                        Direct Tip
                                    @endif
                                </td>
                                <td class="text-right" style="font-weight: bold; color: #27ae60; font-size: 14px;">
                                    KD {{ number_format($tip->amount, 3) }}
                                </td>
                                <td class="text-center">
                                    <span class="label label-info">{{ strtoupper($tip->payment_method) }}</span>
                                </td>
                                <td class="text-center">
                                    @if($tip->status == 'paid_out')
                                        <span class="label label-success"><i class="fa fa-check"></i> Paid Out</span>
                                    @else
                                        <span class="label label-warning"><i class="fa fa-clock-o"></i> Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if($tip->status == 'paid_out' && $tip->paid_out_at)
                                        <small class="text-muted">
                                            {{ $tip->paid_out_at->format('Y-m-d H:i') }}
                                            @if($tip->paidOutBy)
                                                by {{ $tip->paidOutBy->user_full_name }}
                                            @endif
                                        </small>
                                    @else
                                        --
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($tip->status == 'pending')
                                    <button type="button" class="btn btn-success btn-xs btn-payout-single" data-tip-id="{{ $tip->id }}" data-staff-name="{{ $tip->staff->user_full_name ?? 'Staff' }}" data-amount="{{ number_format($tip->amount, 3) }}">
                                        <i class="fa fa-check"></i> Pay Out
                                    </button>
                                    @else
                                    <span class="text-muted"><i class="fa fa-check-circle text-green"></i></span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted" style="padding: 30px;">
                                    <i class="fa fa-info-circle fa-2x"></i>
                                    <p style="margin-top: 10px;">No staff tips found for the selected date range and filter.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    $('.select2').select2();

    // Handle payout for all pending tips of a staff member
    $('.btn-payout-staff').click(function() {
        var staffId = $(this).data('staff-id');
        var staffName = $(this).data('staff-name');
        var amount = $(this).data('amount');

        swal({
            title: 'Pay out tips for ' + staffName + '?',
            text: 'Confirm payout of KD ' + amount + ' to ' + staffName + '. This will mark all pending tips as settled.',
            icon: 'warning',
            buttons: ['Cancel', 'Confirm Payout'],
            dangerMode: false,
        }).then((confirmed) => {
            if (confirmed) {
                $.ajax({
                    url: '{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'payoutStaffTips']) }}',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        staff_id: staffId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            toastr.success(res.msg);
                            location.reload();
                        } else {
                            toastr.error(res.msg || 'Payout failed');
                        }
                    },
                    error: function() {
                        toastr.error('Error processing payout');
                    }
                });
            }
        });
    });

    // Handle payout for a single tip
    $('.btn-payout-single').click(function() {
        var tipId = $(this).data('tip-id');
        var staffName = $(this).data('staff-name');
        var amount = $(this).data('amount');

        swal({
            title: 'Pay out tip of KD ' + amount + '?',
            text: 'For staff member: ' + staffName,
            icon: 'info',
            buttons: ['Cancel', 'Confirm Payout'],
        }).then((confirmed) => {
            if (confirmed) {
                $.ajax({
                    url: '{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'payoutStaffTips']) }}',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        tip_ids: [tipId]
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            toastr.success(res.msg);
                            location.reload();
                        } else {
                            toastr.error(res.msg || 'Payout failed');
                        }
                    },
                    error: function() {
                        toastr.error('Error processing payout');
                    }
                });
            }
        });
    });
});
</script>
@endsection

