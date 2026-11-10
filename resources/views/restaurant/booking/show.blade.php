<div class="modal-dialog modal-lg" role="document">
	<div class="modal-content">
		<div class="modal-header" style="background: #2c3e50; color: #fff; border-top-left-radius: 4px; border-top-right-radius: 4px;">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" style="font-weight: 600;">
				<i class="fa fa-calendar-check-o"></i> @lang('restaurant.booking_details') #{{ $booking->id }}
				<span class="badge" style="background: #e67e22; font-size: 13px; margin-left: 10px;">{{ strtoupper($booking->booking_status) }}</span>
			</h4>
		</div>

		<div class="modal-body" style="padding: 20px;">
			{{-- Booking Overview Details --}}
			<div class="row">
				<div class="col-sm-6">
					<div class="well well-sm" style="background: #fdfefe; border-left: 4px solid #3498db;">
						<p style="margin-bottom: 6px;"><strong class="text-muted"><i class="fa fa-user"></i> @lang('contact.customer'):</strong> <span style="font-size: 15px; font-weight: bold; color: #2c3e50;">{{ $booking->customer->name ?? 'Walk-In Customer' }}</span></p>
						@if(!empty($booking->customer->mobile))
						<p style="margin-bottom: 6px;"><strong class="text-muted"><i class="fa fa-phone"></i> @lang('contact.mobile'):</strong> {{ $booking->customer->mobile }}</p>
						@endif
						<p style="margin-bottom: 6px;"><strong class="text-muted"><i class="fa fa-user-circle"></i> Stylist / Service Staff:</strong> <span class="badge bg-aqua">{{ $booking->waiter->user_full_name ?? 'Any Stylist' }}</span></p>
						@if(!empty($booking->correspondent))
						<p style="margin-bottom: 6px;"><strong class="text-muted"><i class="fa fa-user-secret"></i> @lang('restaurant.correspondent'):</strong> {{ $booking->correspondent->user_full_name }}</p>
						@endif
					</div>
				</div>
				<div class="col-sm-6">
					<div class="well well-sm" style="background: #fdfefe; border-left: 4px solid #2ecc71;">
						<p style="margin-bottom: 6px;"><strong class="text-muted"><i class="fa fa-map-marker"></i> @lang('messages.location'):</strong> {{ $booking->location->name ?? 'Default' }}</p>
						@if(!empty($booking->table->name))
						<p style="margin-bottom: 6px;"><strong class="text-muted"><i class="fa fa-cut"></i> Station / Chair:</strong> {{ $booking->table->name }}</p>
						@endif
						<p style="margin-bottom: 6px;"><strong class="text-muted"><i class="fa fa-clock-o"></i> Starts:</strong> {{ $booking_start }}</p>
						<p style="margin-bottom: 6px;"><strong class="text-muted"><i class="fa fa-clock-o"></i> Ends:</strong> {{ $booking_end }}</p>
					</div>
				</div>
			</div>

			@if(!empty($booking->booking_note))
			<div class="alert alert-info" style="padding: 8px 15px; margin-bottom: 15px;">
				<i class="fa fa-sticky-note-o"></i> <strong>@lang('restaurant.customer_note'):</strong> {{ $booking->booking_note }}
			</div>
			@endif

			{{-- ==================== SERVICES & ADD-ONS (Scenario 1) ==================== --}}
			<div class="box box-solid box-primary" style="margin-top: 15px; border: 1px solid #3c8dbc;">
				<div class="box-header with-border" style="background: #3c8dbc; color: #fff; padding: 10px 15px;">
					<h4 class="box-title" style="font-size: 16px; font-weight: bold;">
						<i class="fa fa-scissors"></i> Services & Add-ons
					</h4>
					<div class="box-tools pull-right">
						<span class="badge" style="background: #27ae60; font-size: 14px; font-weight: bold; padding: 6px 12px;">
							Total: KD {{ number_format($booking->total_services_amount, 3) }}
						</span>
					</div>
				</div>
				<div class="box-body" style="padding: 10px 15px;">
					@if($booking->services->count() > 0)
					<div class="table-responsive">
						<table class="table table-bordered table-striped table-condensed" style="margin-bottom: 10px;">
							<thead>
								<tr style="background: #f8f9fa;">
									<th style="width: 35%;">@lang('Service / Product')</th>
									<th style="width: 25%;">@lang('Stylist / Staff')</th>
									<th style="width: 10%;" class="text-center">@lang('Qty')</th>
									<th style="width: 15%;" class="text-right">@lang('Unit Price (KD)')</th>
									<th style="width: 15%;" class="text-right">@lang('Total (KD)')</th>
								</tr>
							</thead>
							<tbody>
								@foreach($booking->services as $service)
								<tr>
									<td><strong style="color: #2c3e50;">{{ $service->product->name ?? 'Custom Service' }}</strong></td>
									<td><span class="text-muted"><i class="fa fa-user-circle-o"></i> {{ $service->serviceStaff->user_full_name ?? ($booking->waiter->user_full_name ?? '--') }}</span></td>
									<td class="text-center">{{ number_format($service->quantity, 0) }}</td>
									<td class="text-right">{{ number_format($service->unit_price, 3) }}</td>
									<td class="text-right"><strong class="text-primary">{{ number_format($service->line_total, 3) }}</strong></td>
								</tr>
								@endforeach
							</tbody>
							<tfoot>
								<tr style="background: #eef2f7;">
									<td colspan="4" class="text-right" style="font-weight: bold; font-size: 14px;">@lang('Total Services Amount'):</td>
									<td class="text-right" style="font-weight: bold; font-size: 15px; color: #27ae60;">KD {{ number_format($booking->total_services_amount, 3) }}</td>
								</tr>
							</tfoot>
						</table>
					</div>
					@else
					<div class="text-center text-muted" style="padding: 15px 0;">
						<i class="fa fa-info-circle fa-2x"></i>
						<p style="margin-top: 5px;">No individual service lines registered yet. You can add services below.</p>
					</div>
					@endif

					{{-- Form to Add More Service to this Booking --}}
					<div class="panel panel-default" style="margin-top: 10px; border: 1px dashed #3c8dbc; background: #fafbfc;">
						<div class="panel-heading" style="background: #f0f7fd; padding: 8px 12px; font-weight: bold; color: #1976d2;">
							<i class="fa fa-plus-circle"></i> Add More Service / Add-on Service to this Appointment
						</div>
						<div class="panel-body" style="padding: 12px;">
							<form id="add_more_service_form" method="POST" action="{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'addService'], [$booking->id]) }}">
								{!! csrf_field() !!}
								<div class="row">
									<div class="col-sm-4">
										<div class="form-group" style="margin-bottom: 8px;">
											<label style="font-size: 12px; margin-bottom: 2px;">Service / Product:*</label>
											<select name="product_id" id="service_product_id" class="form-control input-sm select2" style="width: 100%;" required>
												<option value="">-- Select Service --</option>
												@if(!empty($service_products))
													@foreach($service_products as $svc)
														<option value="{{ $svc->id }}">{{ $svc->name }}</option>
													@endforeach
												@endif
											</select>
										</div>
									</div>
									<div class="col-sm-3">
										<div class="form-group" style="margin-bottom: 8px;">
											<label style="font-size: 12px; margin-bottom: 2px;">Stylist / Staff:</label>
											<select name="service_staff_id" class="form-control input-sm select2" style="width: 100%;">
												<option value="">-- Assigned Stylist --</option>
												@if(!empty($service_staff))
													@foreach($service_staff as $sId => $sName)
														<option value="{{ $sId }}" {{ ($booking->waiter_id == $sId) ? 'selected' : '' }}>{{ $sName }}</option>
													@endforeach
												@endif
											</select>
										</div>
									</div>
									<div class="col-sm-2">
										<div class="form-group" style="margin-bottom: 8px;">
											<label style="font-size: 12px; margin-bottom: 2px;">Qty:</label>
											<input type="number" name="quantity" id="service_qty" class="form-control input-sm" value="1" min="1" step="1" required>
										</div>
									</div>
									<div class="col-sm-3">
										<div class="form-group" style="margin-bottom: 8px;">
											<label style="font-size: 12px; margin-bottom: 2px;">Price (KD):*</label>
											<input type="number" step="0.001" min="0" name="unit_price" id="service_unit_price" class="form-control input-sm" placeholder="0.000" required>
										</div>
									</div>
								</div>

								{{-- Quick Denomination Buttons for Service Price --}}
								<div class="row" style="margin-top: 4px;">
									<div class="col-sm-9">
										<span style="font-size: 11px; font-weight: bold; color: #7f8c8d; margin-right: 6px;">Quick Price (KD):</span>
										<button type="button" class="btn btn-default btn-xs service-denom-btn" data-amt="1.000">+1 KD</button>
										<button type="button" class="btn btn-default btn-xs service-denom-btn" data-amt="2.000">+2 KD</button>
										<button type="button" class="btn btn-default btn-xs service-denom-btn" data-amt="5.000">+5 KD</button>
										<button type="button" class="btn btn-default btn-xs service-denom-btn" data-amt="10.000">+10 KD</button>
										<button type="button" class="btn btn-default btn-xs service-denom-btn" data-amt="20.000">+20 KD</button>
										<button type="button" class="btn btn-default btn-xs service-denom-btn" data-amt="50.000">+50 KD</button>
										<button type="button" class="btn btn-danger btn-xs service-clear-denom-btn" style="padding: 1px 6px;">Clear</button>
									</div>
									<div class="col-sm-3 text-right">
										<button type="submit" class="btn btn-primary btn-sm btn-block" id="btn_submit_add_service">
											<i class="fa fa-plus"></i> Add Service
										</button>
									</div>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>

			{{-- ==================== TIPPINGS SECTION (Scenario 2) ==================== --}}
			<div class="box box-solid box-warning" style="margin-top: 15px; border: 1px solid #f39c12;">
				<div class="box-header with-border" style="background: #f39c12; color: #fff; padding: 10px 15px;">
					<h4 class="box-title" style="font-size: 16px; font-weight: bold;">
						<i class="fa fa-heart"></i> Staff Tips
					</h4>
					<div class="box-tools pull-right">
						<span class="badge" style="background: #27ae60; font-size: 14px; font-weight: bold; padding: 6px 12px;">
							Total Tips: KD {{ number_format($booking->total_tips_amount, 3) }}
						</span>
					</div>
				</div>
				<div class="box-body" style="padding: 10px 15px;">
					{{-- Existing Tips Table --}}
					@if(!empty($tips) && $tips->count() > 0)
					<div class="table-responsive">
						<table class="table table-bordered table-condensed table-striped" style="margin-bottom: 10px;">
							<thead>
								<tr style="background: #fcf8e3;">
									<th>Stylist / Staff</th>
									<th class="text-right">Tip Amount (KD)</th>
									<th class="text-center">Payment Method</th>
									<th class="text-center">Status</th>
									<th>Recorded At</th>
								</tr>
							</thead>
							<tbody>
								@foreach($tips as $t)
								<tr>
									<td><strong style="color: #2c3e50;"><i class="fa fa-user"></i> {{ $t->staff->user_full_name ?? ('Staff #' . $t->staff_id) }}</strong></td>
									<td class="text-right"><strong class="text-green" style="font-size: 13px;">KD {{ number_format($t->amount, 3) }}</strong></td>
									<td class="text-center"><span class="label label-info">{{ strtoupper($t->payment_method) }}</span></td>
									<td class="text-center">
										@if($t->status == 'paid_out')
											<span class="label label-success"><i class="fa fa-check"></i> Paid Out</span>
										@else
											<span class="label label-warning"><i class="fa fa-clock-o"></i> Pending</span>
										@endif
									</td>
									<td class="text-muted" style="font-size: 11px;">{{ $t->created_at->format('Y-m-d H:i') }}</td>
								</tr>
								@endforeach
							</tbody>
							<tfoot>
								<tr style="background: #fff8e1;">
									<td class="text-right" style="font-weight: bold;">Total Tips:</td>
									<td class="text-right" style="font-weight: bold; color: #27ae60; font-size: 14px;">KD {{ number_format($tips->sum('amount'), 3) }}</td>
									<td colspan="3"></td>
								</tr>
							</tfoot>
						</table>
					</div>
					@else
					<p class="text-muted" style="font-style: italic; margin-bottom: 10px;"><i class="fa fa-info-circle"></i> No tips recorded yet for this appointment.</p>
					@endif

					{{-- Form to Record a New Tip --}}
					<div class="panel panel-default" style="margin-top: 8px; border: 1px dashed #f39c12; background: #fffcf5;">
						<div class="panel-heading" style="background: #fff8e7; padding: 8px 12px; font-weight: bold; color: #d35400;">
							<i class="fa fa-money"></i> Record Staff Tip (Cash / Card)
						</div>
						<div class="panel-body" style="padding: 12px;">
							<form id="record_tip_form" method="POST" action="{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'recordTip'], [$booking->id]) }}">
								{!! csrf_field() !!}
								<div class="row">
									<div class="col-sm-4">
										<div class="form-group" style="margin-bottom: 8px;">
											<label style="font-size: 12px; margin-bottom: 2px;">Stylist / Staff:*</label>
											<select name="staff_id" class="form-control input-sm select2" style="width: 100%;" required>
												<option value="">-- Select Staff --</option>
												@if(!empty($service_staff))
													@foreach($service_staff as $sId => $sName)
														<option value="{{ $sId }}" {{ ($booking->waiter_id == $sId) ? 'selected' : '' }}>{{ $sName }}</option>
													@endforeach
												@endif
											</select>
										</div>
									</div>
									<div class="col-sm-3">
										<div class="form-group" style="margin-bottom: 8px;">
											<label style="font-size: 12px; margin-bottom: 2px;">Tip Amount (KD):*</label>
											<input type="number" step="0.001" min="0.100" name="amount" id="tip_amount_input" class="form-control input-sm" placeholder="0.000" required>
										</div>
									</div>
									<div class="col-sm-3">
										<div class="form-group" style="margin-bottom: 8px;">
											<label style="font-size: 12px; margin-bottom: 2px;">Payment Method:</label>
											<select name="payment_method" class="form-control input-sm">
												<option value="cash" selected>Cash</option>
												<option value="card">Card / K-Net</option>
												<option value="other">Other</option>
											</select>
										</div>
									</div>
									<div class="col-sm-2">
										<label style="font-size: 12px; margin-bottom: 2px;">&nbsp;</label>
										<button type="submit" class="btn btn-warning btn-sm btn-block" id="btn_submit_record_tip">
											<i class="fa fa-check"></i> Add Tip
										</button>
									</div>
								</div>

								{{-- Quick Tip Denomination Buttons --}}
								<div class="row" style="margin-top: 4px;">
									<div class="col-sm-12">
										<span style="font-size: 11px; font-weight: bold; color: #7f8c8d; margin-right: 6px;">Quick Tip:</span>
										<button type="button" class="btn btn-default btn-xs tip-denom-btn" data-amt="0.500">0.500 KD</button>
										<button type="button" class="btn btn-default btn-xs tip-denom-btn" data-amt="1.000">1.000 KD</button>
										<button type="button" class="btn btn-default btn-xs tip-denom-btn" data-amt="2.000">2.000 KD</button>
										<button type="button" class="btn btn-default btn-xs tip-denom-btn" data-amt="5.000">5.000 KD</button>
										<button type="button" class="btn btn-default btn-xs tip-denom-btn" data-amt="10.000">10.000 KD</button>
										<button type="button" class="btn btn-default btn-xs tip-denom-btn" data-amt="20.000">20.000 KD</button>
										<button type="button" class="btn btn-danger btn-xs tip-clear-denom-btn" style="padding: 1px 6px;">Clear</button>
									</div>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>

			{{-- Notification & Status Updates --}}
			<hr style="margin-top: 15px; margin-bottom: 15px;">
			<div class="row">
				<div class="col-sm-12">
					<button type="button" class="btn btn-info btn-modal pull-right" data-href="{{action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $booking->id,'template_for' => 'new_booking'])}}" data-container=".view_modal">
						<i class="fa fa-envelope-o"></i> @lang('restaurant.send_notification_to_customer')
					</button>
				</div>
			</div>
			<br>
			<div class="row">
				<div class="col-sm-9">
					{!! Form::open(['url' => action([\App\Http\Controllers\Restaurant\BookingController::class, 'update'], [$booking->id]), 'method' => 'PUT', 'id' => 'edit_booking_form' ]) !!}
						<div class="input-group">
							{!! Form::select('booking_status', $booking_statuses, $booking->booking_status, ['class' => 'form-control', 'placeholder' => __('restaurant.change_booking_status'), 'required']); !!}
							<div class="input-group-btn">
								<button type="submit" class="btn btn-primary"><i class="fa fa-refresh"></i> @lang('messages.update')</button>
							</div>
						</div>
					{!! Form::close() !!}
				</div>
				<div class="col-sm-3 text-center">
					<button type="button" class="btn btn-danger btn-block" id="delete_booking" data-href="{{action([\App\Http\Controllers\Restaurant\BookingController::class, 'destroy'], [$booking->id])}}">
						<i class="fa fa-trash"></i> @lang('restaurant.delete_booking')
					</button>
				</div>
			</div>

			{{-- Convert to Invoice Button --}}
			@if($booking->booking_status != 'cancelled')
			<div class="row" style="margin-top: 15px;">
				<div class="col-sm-12 text-center">
					<button type="button" class="btn btn-success btn-lg convert-to-invoice" 
							data-booking-id="{{ $booking->id }}" 
							data-href="{{action([\App\Http\Controllers\Restaurant\BookingController::class, 'convertToInvoice'], [$booking->id])}}">
						<i class="fa fa-file-text-o"></i> @lang('Convert to Invoice') (KD {{ number_format($booking->total_services_amount, 3) }})
					</button>
				</div>
			</div>
			@endif
		</div>

		<div class="modal-footer" style="background: #f9f9f9;">
			<button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
		</div>
	</div>
</div>

<script type="text/javascript">
$(document).ready(function() {
	// Initialize select2 inside modal
	$('.view_modal .select2').select2({
		dropdownParent: $('.view_modal')
	});

	// Service Denomination button clicks
	$(document).off('click', '.service-denom-btn').on('click', '.service-denom-btn', function() {
		var amt = parseFloat($(this).data('amt')) || 0;
		var current = parseFloat($('#service_unit_price').val()) || 0;
		$('#service_unit_price').val((current + amt).toFixed(3));
	});

	$(document).off('click', '.service-clear-denom-btn').on('click', '.service-clear-denom-btn', function() {
		$('#service_unit_price').val('');
	});

	// Tip Denomination button clicks
	$(document).off('click', '.tip-denom-btn').on('click', '.tip-denom-btn', function() {
		var amt = parseFloat($(this).data('amt')) || 0;
		var current = parseFloat($('#tip_amount_input').val()) || 0;
		$('#tip_amount_input').val((current + amt).toFixed(3));
	});

	$(document).off('click', '.tip-clear-denom-btn').on('click', '.tip-clear-denom-btn', function() {
		$('#tip_amount_input').val('');
	});

	// Handle Add More Service via AJAX
	$('#add_more_service_form').off('submit').on('submit', function(e) {
		e.preventDefault();
		var form = $(this);
		var submitBtn = $('#btn_submit_add_service');
		submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');

		$.ajax({
			url: form.attr('action'),
			method: 'POST',
			data: form.serialize(),
			dataType: 'json',
			success: function(res) {
				submitBtn.prop('disabled', false).html('<i class="fa fa-plus"></i> Add Service');
				if (res.success) {
					toastr.success(res.msg || 'Service added successfully');
					// Reload this modal to show updated services & total
					$('div.view_modal').load('{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'show'], [$booking->id]) }}', function() {
						if (typeof todays_bookings_table !== 'undefined') {
							todays_bookings_table.ajax.reload();
						}
					});
				} else {
					toastr.error(res.msg || 'Failed to add service');
				}
			},
			error: function(xhr) {
				submitBtn.prop('disabled', false).html('<i class="fa fa-plus"></i> Add Service');
				toastr.error('Failed to add service: ' + (xhr.responseJSON?.message || 'Server error'));
			}
		});
	});

	// Handle Record Tip via AJAX
	$('#record_tip_form').off('submit').on('submit', function(e) {
		e.preventDefault();
		var form = $(this);
		var submitBtn = $('#btn_submit_record_tip');
		submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

		$.ajax({
			url: form.attr('action'),
			method: 'POST',
			data: form.serialize(),
			dataType: 'json',
			success: function(res) {
				submitBtn.prop('disabled', false).html('<i class="fa fa-check"></i> Add Tip');
				if (res.success) {
					toastr.success(res.msg || 'Tip recorded successfully');
					// Reload this modal to show updated tips & total
					$('div.view_modal').load('{{ action([\App\Http\Controllers\Restaurant\BookingController::class, 'show'], [$booking->id]) }}', function() {
						if (typeof todays_bookings_table !== 'undefined') {
							todays_bookings_table.ajax.reload();
						}
					});
				} else {
					toastr.error(res.msg || 'Failed to record tip');
				}
			},
			error: function(xhr) {
				submitBtn.prop('disabled', false).html('<i class="fa fa-check"></i> Add Tip');
				toastr.error('Failed to record tip: ' + (xhr.responseJSON?.message || 'Server error'));
			}
		});
	});
});
</script>
