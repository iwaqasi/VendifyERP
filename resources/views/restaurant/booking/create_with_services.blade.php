<div class="modal fade" id="add_booking_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

        {!! Form::open(['url' => action([\App\Http\Controllers\Restaurant\BookingController::class, 'store']), 'method' => 'post', 'id' => 'add_booking_form' ]) !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-calendar-plus-o"></i> @lang('restaurant.add_booking')</h4>
            </div>

            <div class="modal-body">
                @if(count($business_locations) == 1)
                    @php 
                        $default_location = current(array_keys($business_locations->toArray())) 
                    @endphp
                @else
                    @php $default_location = null; @endphp
                @endif
                
                {{-- Location --}}
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-map-marker"></i>
                                </span>
                                {!! Form::select('location_id', $business_locations, $default_location, ['class' => 'form-control select2', 'placeholder' => __('purchase.business_location'), 'required', 'id' => 'booking_location_id']); !!}
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Customer --}}
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-user"></i>
                                </span>
                                {!! Form::select('contact_id', 
                                    $customers, null, ['class' => 'form-control select2', 'id' => 'booking_customer_id', 'placeholder' => __('contact.customer'), 'required']); !!}
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default bg-white btn-flat add_new_customer" data-name=""  @if(!auth()->user()->can('customer.create')) disabled @endif><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-clock-o"></i>
                                </span>
                                <span class="input-group-addon" style="background: #f9f9f9;">@lang('restaurant.start_time'):*</span>
                                {!! Form::text('booking_start', null, ['class' => 'form-control', 'placeholder' => __('restaurant.start_time'), 'required', 'id' => 'start_time', 'readonly']); !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-user-md"></i>
                                </span>
                                <span class="input-group-addon" style="background: #f9f9f9;">Correspondent:</span>
                                {!! Form::select('correspondent', 
                                    $correspondents, null, ['class' => 'form-control select2', 'placeholder' => __('restaurant.select_correspondent'), 'id' => 'correspondent']); !!}
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon">
                                    <i class="fa fa-clock-o"></i>
                                </span>
                                <span class="input-group-addon" style="background: #f9f9f9;">@lang('restaurant.end_time'):*</span>
                                {!! Form::text('booking_end', null, ['class' => 'form-control', 'placeholder' => __('restaurant.end_time'), 'required', 'id' => 'end_time', 'readonly']); !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clearfix"></div>
                <div id="restaurant_module_span"></div>
                <div class="clearfix"></div>

                {{-- Service Lines --}}
                <div class="box box-primary" style="margin-top: 15px;">
                    <div class="box-header with-border" style="background: #f4f6f9;">
                        <h3 class="box-title"><i class="fa fa-list"></i> @lang('Services')</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-primary btn-sm" id="add_service_line">
                                <i class="fa fa-plus"></i> @lang('Add Service')
                            </button>
                        </div>
                    </div>
                    <div class="box-body" style="padding: 10px;">
                        <div class="table-responsive">
                            <table class="table table-bordered table-condensed" id="service_lines_table">
                                <thead style="background: #3c8dbc; color: white;">
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 30%;">@lang('Service/Product')</th>
                                        <th style="width: 15%;">@lang('Service Staff')</th>
                                        <th style="width: 10%;">@lang('Qty')</th>
                                        <th style="width: 15%;">@lang('Unit Price')</th>
                                        <th style="width: 15%;">@lang('Total')</th>
                                        <th style="width: 10%;">@lang('Action')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Service lines will be added dynamically --}}
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f4f6f9;">
                                        <td colspan="5" class="text-right"><strong>@lang('Total Amount'): </strong></td>
                                        <td colspan="2"><strong id="booking_total_amount">0.00</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p class="text-muted"><small><i class="fa fa-info-circle"></i> @lang('Add services for this booking. Price can be changed if marked as Flexible in Product settings.')</small></p>
                    </div>
                </div>

                {{-- Customer Note --}}
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            {!! Form::label('booking_note', __('restaurant.customer_note') . ':') !!}
                            {!! Form::textarea('booking_note', null, ['class' => 'form-control', 'placeholder' => __('restaurant.customer_note'), 'rows' => 2]); !!}
                        </div>
                    </div>
                </div>

                {{-- Notification --}}
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            <div class="checkbox">
                                {!! Form::checkbox('send_notification', 1, true, ['class' => 'input-icheck', 'id' => 'send_notification']); !!} @lang('restaurant.send_notification_to_customer')
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fa fa-save"></i> @lang('messages.save')</button>
                <button type="button" class="btn btn-default btn-lg" data-dismiss="modal"><i class="fa fa-times"></i> @lang('messages.close')</button>
            </div>

        {!! Form::close() !!}

        </div>
    </div>
</div>

{{-- Hidden template for service line rows --}}
<template id="service_line_template">
    <tr class="service-line-row">
        <td class="line-number">1</td>
        <td>
            <select name="services[INDEX][product_id]" class="form-control select2 service-product" required style="width: 100%;">
                <option value="">@lang('Select Service')</option>
            </select>
            <input type="hidden" name="services[INDEX][is_flexible]" class="is_flexible_input" value="0">
        </td>
        <td>
            <select name="services[INDEX][service_staff_id]" class="form-control select2 service-staff" style="width: 100%;">
                <option value="">@lang('Select Staff')</option>
            </select>
        </td>
        <td>
            <input type="number" name="services[INDEX][quantity]" class="form-control service-qty" value="1" min="0.01" step="0.01" required>
        </td>
        <td>
            <input type="text" name="services[INDEX][unit_price]" class="form-control service-price input_number" value="0.00" required readonly>
        </td>
        <td>
            <span class="line-total">0.00</span>
            <input type="hidden" name="services[INDEX][line_total]" class="service-line-total" value="0">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm remove-service-line" title="@lang('messages.delete')">
                <i class="fa fa-trash"></i>
            </button>
        </td>
    </tr>
</template>
