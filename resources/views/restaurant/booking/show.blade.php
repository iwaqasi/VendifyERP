<div class="modal-dialog" role="document">
	<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title">@lang( 'restaurant.booking_details' )</h4>
			</div>

			<div class="modal-body">
				<div class="row">
					<div class="col-sm-6">
						<strong>@lang('contact.customer'):</strong> {{ $booking->customer->name }}<br>
						<strong>@lang('restaurant.service_staff'):</strong> {{ $booking->waiter->user_full_name ?? '--' }}<br>
						<strong>@lang('restaurant.correspondent'):</strong> {{ $booking->correspondent->user_full_name ?? '--' }}<br>
						@if(!empty($booking->booking_note))
						<strong>@lang('restaurant.customer_note'):</strong> {{ $booking->booking_note }}
						@endif
					</div>
					<div class="col-sm-6">
						<strong>@lang('messages.location'):</strong> {{ $booking->location->name }}<br>
						<strong>@lang('restaurant.table'):</strong> {{ $booking->table->name ?? '--' }}<br>
						<strong>@lang('restaurant.booking_starts'):</strong> {{ $booking_start }}<br>
						<strong>@lang('restaurant.booking_ends'):</strong> {{ $booking_end }}
					</div>
				</div>
				<br>
				@if($booking->services->count() > 0)
				<div class="row">
					<div class="col-sm-12">
						<h4><i class="fa fa-list"></i> @lang('Services')</h4>
						<table class="table table-bordered table-condensed">
							<thead style="background: #3c8dbc; color: white;">
								<tr>
									<th>@lang('Service/Product')</th>
									<th>@lang('Service Staff')</th>
									<th>@lang('Qty')</th>
									<th>@lang('Unit Price')</th>
									<th>@lang('Total')</th>
								</tr>
							</thead>
							<tbody>
								@foreach($booking->services as $service)
								<tr>
									<td>{{ $service->product->name ?? 'N/A' }}</td>
									<td>{{ $service->serviceStaff->user_full_name ?? '--' }}</td>
									<td>{{ $service->quantity }}</td>
									<td>{{ number_format($service->unit_price, 2) }}</td>
									<td><strong>{{ number_format($service->line_total, 2) }}</strong></td>
								</tr>
								@endforeach
							</tbody>
							<tfoot>
								<tr style="background: #f4f6f9;">
									<td colspan="4" class="text-right"><strong>@lang('Total Amount'): </strong></td>
									<td><strong>{{ number_format($booking->services->sum('line_total'), 2) }}</strong></td>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
				@endif
				<hr>
				<div class="row">
					<div class="col-sm-12">
						<button type="button" class="btn btn-info btn-modal pull-right" data-href="{{action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $booking->id,'template_for' => 'new_booking'])}}" data-container=".view_modal">@lang('restaurant.send_notification_to_customer')</button>
					</div>
				</div>
				<br>
				<div class="row">
					<div class="col-sm-9">
						{!! Form::open(['url' => action([\App\Http\Controllers\Restaurant\BookingController::class, 'update'], [$booking->id]), 'method' => 'PUT', 'id' => 'edit_booking_form' ]) !!}
							<div class="input-group">
				                <!-- /btn-group -->
				                {!! Form::select('booking_status', $booking_statuses, $booking->booking_status, ['class' => 'form-control', 'placeholder' => __('restaurant.change_booking_status'), 'required']); !!}
				                <div class="input-group-btn">
				                  <button type="submit" class="btn btn-primary">@lang('messages.update')</button>
				                </div>
				             </div>
						{!! Form::close() !!}
					</div>
					<div class="col-sm-3 text-center">
						<button type="button" class="btn btn-danger" id="delete_booking" data-href="{{action([\App\Http\Controllers\Restaurant\BookingController::class, 'destroy'], [$booking->id])}}">@lang('restaurant.delete_booking')</button>
					</div>
				</div>
				@if($booking->services->count() > 0 && $booking->booking_status != 'cancelled')
				<div class="row" style="margin-top: 15px;">
					<div class="col-sm-12 text-center">
						<button type="button" class="btn btn-success btn-lg convert-to-invoice" 
								data-booking-id="{{ $booking->id }}" 
								data-href="{{action([\App\Http\Controllers\Restaurant\BookingController::class, 'convertToInvoice'], [$booking->id])}}">
							<i class="fa fa-file-text-o"></i> @lang('Convert to Invoice')
						</button>
					</div>
				</div>
				@endif
			<br>
			<div class="modal-footer">
			<button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang( 'messages.close' )</button>
			</div>
		

	</div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->