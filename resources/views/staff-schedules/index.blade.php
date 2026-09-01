@extends('layouts.app')
@section('title', __('Staff Schedules'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('Staff Schedules')</h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-sm-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('Service Staff Weekly Schedules')</h3>
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <table class="table table-bordered table-striped" id="schedule_table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">@lang('Sr')</th>
                                <th style="width: 15%;">@lang('Staff Name')</th>
                                <th style="width: 12%;">@lang('Sat')</th>
                                <th style="width: 12%;">@lang('Sun')</th>
                                <th style="width: 12%;">@lang('Mon')</th>
                                <th style="width: 12%;">@lang('Tue')</th>
                                <th style="width: 12%;">@lang('Wed')</th>
                                <th style="width: 12%;">@lang('Thu')</th>
                                <th style="width: 12%;">@lang('Fri')</th>
                                <th style="width: 5%;">@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $sr = 1; @endphp
                            @foreach($service_staff as $staff)
                                @php
                                    $schedule = isset($schedules[$staff->id]) ? $schedules[$staff->id] : null;
                                @endphp
                                <tr>
                                    <td>{{ $sr++ }}</td>
                                    <td><strong>{{ trim($staff->surname . ' ' . $staff->first_name . ' ' . $staff->last_name) }}</strong></td>
                                    <td class="schedule-cell" data-day="sat" data-staff="{{ $staff->id }}">
                                        @if($schedule && $schedule->sat_is_off)
                                            <span class="label label-danger">OFF</span>
                                        @elseif($schedule && $schedule->sat_start_time && $schedule->sat_end_time)
                                            <span class="label label-success">{{ $schedule->getDayTimeRange('sat') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="schedule-cell" data-day="sun" data-staff="{{ $staff->id }}">
                                        @if($schedule && $schedule->sun_is_off)
                                            <span class="label label-danger">OFF</span>
                                        @elseif($schedule && $schedule->sun_start_time && $schedule->sun_end_time)
                                            <span class="label label-success">{{ $schedule->getDayTimeRange('sun') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="schedule-cell" data-day="mon" data-staff="{{ $staff->id }}">
                                        @if($schedule && $schedule->mon_is_off)
                                            <span class="label label-danger">OFF</span>
                                        @elseif($schedule && $schedule->mon_start_time && $schedule->mon_end_time)
                                            <span class="label label-success">{{ $schedule->getDayTimeRange('mon') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="schedule-cell" data-day="tue" data-staff="{{ $staff->id }}">
                                        @if($schedule && $schedule->tue_is_off)
                                            <span class="label label-danger">OFF</span>
                                        @elseif($schedule && $schedule->tue_start_time && $schedule->tue_end_time)
                                            <span class="label label-success">{{ $schedule->getDayTimeRange('tue') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="schedule-cell" data-day="wed" data-staff="{{ $staff->id }}">
                                        @if($schedule && $schedule->wed_is_off)
                                            <span class="label label-danger">OFF</span>
                                        @elseif($schedule && $schedule->wed_start_time && $schedule->wed_end_time)
                                            <span class="label label-success">{{ $schedule->getDayTimeRange('wed') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="schedule-cell" data-day="thu" data-staff="{{ $staff->id }}">
                                        @if($schedule && $schedule->thu_is_off)
                                            <span class="label label-danger">OFF</span>
                                        @elseif($schedule && $schedule->thu_start_time && $schedule->thu_end_time)
                                            <span class="label label-success">{{ $schedule->getDayTimeRange('thu') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="schedule-cell" data-day="fri" data-staff="{{ $staff->id }}">
                                        @if($schedule && $schedule->fri_is_off)
                                            <span class="label label-danger">OFF</span>
                                        @elseif($schedule && $schedule->fri_start_time && $schedule->fri_end_time)
                                            <span class="label label-success">{{ $schedule->getDayTimeRange('fri') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-schedule" 
                                                data-staff-id="{{ $staff->id }}" 
                                                data-staff-name="{{ trim($staff->surname . ' ' . $staff->first_name . ' ' . $staff->last_name) }}"
                                                title="@lang('Edit Schedule')">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- /.box-body -->
            </div>
        </div>
    </div>
</section>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" role="dialog" aria-labelledby="editScheduleModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="editScheduleModalLabel">@lang('Edit Staff Schedule')</h4>
            </div>
            <form id="schedule_form" action="{{ action([\App\Http\Controllers\StaffScheduleController::class, 'store']) }}" method="post">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="user_id" id="schedule_user_id" value="">
                    
                    <div class="row">
                        <div class="col-sm-12">
                            <h4><strong>@lang('Staff:')</strong> <span id="schedule_staff_name"></span></h4>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>@lang('Day')</th>
                                    <th>@lang('Off')</th>
                                    <th>@lang('Start Time')</th>
                                    <th>@lang('End Time')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(['sat' => 'Saturday', 'sun' => 'Sunday', 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday'] as $day => $dayName)
                                <tr>
                                    <td><strong>{{ $dayName }}</strong></td>
                                    <td>
                                        <input type="checkbox" name="{{ $day }}_is_off" class="is-off-checkbox" data-day="{{ $day }}" value="1">
                                    </td>
                                    <td>
                                        <input type="time" name="{{ $day }}_start_time" class="form-control time-input" data-day="{{ $day }}" step="300">
                                    </td>
                                    <td>
                                        <input type="time" name="{{ $day }}_end_time" class="form-control time-input" data-day="{{ $day }}" step="300">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-12">
                            <button type="button" class="btn btn-sm btn-default" id="copy_sat_to_all">@lang('Copy Saturday to All Days')</button>
                            <button type="button" class="btn btn-sm btn-default" id="set_all_off">@lang('Set All Days Off')</button>
                            <button type="button" class="btn btn-sm btn-default" id="clear_all">@lang('Clear All')</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">@lang('Save Schedule')</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('Close')</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    // Pass schedule data to JavaScript
    window.scheduleData = @json($schedules->toArray());
    
    // Initialize DataTable
    $('#schedule_table').DataTable({
        ordering: false,
        pageLength: 25,
        searching: true,
        dom: 'frtip'
    });

    // Edit schedule button click
    $('.edit-schedule').click(function() {
        var staffId = $(this).data('staff-id');
        var staffName = $(this).data('staff-name');
        
        $('#schedule_user_id').val(staffId);
        $('#schedule_staff_name').text(staffName);
        
        // Load existing schedule if available
        loadSchedule(staffId);
        
        $('#editScheduleModal').modal('show');
    });

    // Load schedule data
    function loadSchedule(staffId) {
        // Reset form
        $('form#schedule_form')[0].reset();
        
        // Try to load from page data (will be populated from server)
        var scheduleData = window.scheduleData || {};
        if (scheduleData[staffId]) {
            var schedule = scheduleData[staffId];
            $.each(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'], function(i, day) {
                if (schedule[day + '_is_off']) {
                    $('input[name="' + day + '_is_off"]').prop('checked', true);
                    $('input[name="' + day + '_start_time"]').prop('disabled', true).val('');
                    $('input[name="' + day + '_end_time"]').prop('disabled', true).val('');
                } else {
                    $('input[name="' + day + '_is_off"]').prop('checked', false);
                    $('input[name="' + day + '_start_time"]').prop('disabled', false);
                    $('input[name="' + day + '_end_time"]').prop('disabled', false);
                    if (schedule[day + '_start_time']) {
                        // Handle both "HH:MM:SS" and "HH:MM" formats
                        var startTime = schedule[day + '_start_time'];
                        if (startTime.length > 5) startTime = startTime.substring(0, 5);
                        $('input[name="' + day + '_start_time"]').val(startTime);
                    }
                    if (schedule[day + '_end_time']) {
                        var endTime = schedule[day + '_end_time'];
                        if (endTime.length > 5) endTime = endTime.substring(0, 5);
                        $('input[name="' + day + '_end_time"]').val(endTime);
                    }
                }
            });
        }
    }

    // Toggle time inputs when "Off" checkbox is checked
    $(document).on('change', '.is-off-checkbox', function() {
        var day = $(this).data('day');
        var isOff = $(this).is(':checked');
        
        $('input[name="' + day + '_start_time"]').prop('disabled', isOff).val('');
        $('input[name="' + day + '_end_time"]').prop('disabled', isOff).val('');
    });

    // Copy Saturday to All Days
    $('#copy_sat_to_all').click(function() {
        var satStart = $('input[name="sat_start_time"]').val();
        var satEnd = $('input[name="sat_end_time"]').val();
        var satOff = $('input[name="sat_is_off"]').is(':checked');
        
        $.each(['sun', 'mon', 'tue', 'wed', 'thu', 'fri'], function(i, day) {
            $('input[name="' + day + '_is_off"]').prop('checked', satOff);
            if (satOff) {
                $('input[name="' + day + '_start_time"]').prop('disabled', true).val('');
                $('input[name="' + day + '_end_time"]').prop('disabled', true).val('');
            } else {
                $('input[name="' + day + '_start_time"]').prop('disabled', false).val(satStart);
                $('input[name="' + day + '_end_time"]').prop('disabled', false).val(satEnd);
            }
        });
    });

    // Set All Days Off
    $('#set_all_off').click(function() {
        $.each(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'], function(i, day) {
            $('input[name="' + day + '_is_off"]').prop('checked', true);
            $('input[name="' + day + '_start_time"]').prop('disabled', true).val('');
            $('input[name="' + day + '_end_time"]').prop('disabled', true).val('');
        });
    });

    // Clear All
    $('#clear_all').click(function() {
        $.each(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'], function(i, day) {
            $('input[name="' + day + '_is_off"]').prop('checked', false);
            $('input[name="' + day + '_start_time"]').prop('disabled', false).val('');
            $('input[name="' + day + '_end_time"]').prop('disabled', false).val('');
        });
    });

    // Form submission
    $('form#schedule_form').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var data = form.serialize();

        $.ajax({
            method: "POST",
            url: form.attr("action"),
            dataType: "json",
            data: data,
            beforeSend: function(xhr) {
                __disable_submit_button(form.find('button[type="submit"]'));
            },
            success: function(result) {
                if (result.success == true) {
                    toastr.success(result.msg);
                    $('#editScheduleModal').modal('hide');
                    location.reload();
                } else {
                    toastr.error(result.msg);
                }
                form.find('button[type="submit"]').attr('disabled', false);
            }
        });
    });
});
</script>
@endsection
