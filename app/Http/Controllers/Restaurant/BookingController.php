<?php

namespace App\Http\Controllers\Restaurant;

use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Restaurant\Booking;
use App\User;
use App\Utils\RestaurantUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yajra\DataTables\Facades\DataTables;

class BookingController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected $restUtil;

    public function __construct(Util $commonUtil, RestaurantUtil $restUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');

        $user_id = request()->has('user_id') ? request()->user_id : null;
        if (! auth()->user()->hasPermissionTo('crud_all_bookings') && ! $this->restUtil->is_admin(auth()->user(), $business_id)) {
            $user_id = request()->session()->get('user.id');
        }
        if (request()->ajax()) {
            $filters = [
                'start_date' => request()->start,
                'end_date' => request()->end,
                'user_id' => $user_id,
                'location_id' => ! empty(request()->location_id) ? request()->location_id : null,
                'business_id' => $business_id,
            ];

            $events = $this->restUtil->getBookingsForCalendar($filters);

            return $events;
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        $customers = Contact::customersDropdown($business_id, false);

        $correspondents = User::forDropdown($business_id, false);

        $types = Contact::getContactTypes();
        $customer_groups = CustomerGroup::forDropdown($business_id);

        // Get service products (not for selling = services)
        $service_products = \App\Product::where('business_id', $business_id)
            ->where('not_for_selling', 0)
            ->with(['product_variations' => function($q) {
                $q->with('variations');
            }])
            ->get()
            ->map(function($product) {
                $variation = $product->product_variations->first() ? $product->product_variations->first()->variations->first() : null;
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'default_sell_price' => $variation ? $variation->default_sell_price : 0,
                    'is_flexible_price' => $product->is_flexible_price ?? 0,
                ];
            });

        // Format correspondents for JavaScript (array of {id, name} objects)
        $correspondents_for_js = $correspondents->filter()->map(function($name, $id) {
            return ['id' => $id, 'name' => trim($name)];
        })->values();

        return view('restaurant.booking.index', compact('business_locations', 'customers', 'correspondents', 'types', 'customer_groups', 'service_products', 'correspondents_for_js'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            if ($request->ajax()) {
                $business_id = request()->session()->get('user.business_id');
                $user_id = request()->session()->get('user.id');

                $input = $request->input();
                $booking_start = $this->commonUtil->uf_date($input['booking_start'], true);
                $booking_end = $this->commonUtil->uf_date($input['booking_end'], true);
                $date_range = [$booking_start, $booking_end];

                //Check if booking is available for the required input
                $query = Booking::where('business_id', $business_id)
                                ->where('location_id', $input['location_id'])
                                ->where('contact_id', $input['contact_id'])
                                ->where(function ($q) use ($date_range) {
                                    $q->whereBetween('booking_start', $date_range)
                                    ->orWhereBetween('booking_end', $date_range);
                                });

                if (isset($input['res_table_id'])) {
                    $query->where('table_id', $input['res_table_id']);
                }

                $existing_booking = $query->first();
                if (empty($existing_booking)) {
                    $input['business_id'] = $business_id;
                    $input['created_by'] = $user_id;
                    $input['booking_start'] = $booking_start;
                    $input['booking_end'] = $booking_end;
                    $booking = Booking::createBooking($input);

                    // Save service lines if provided
                    if (!empty($input["services"]) && is_array($input["services"])) {
                        foreach ($input["services"] as $service) {
                            if (!empty($service["product_id"])) {
                                \App\BookingService::create([
                                    'booking_id' => $booking->id,
                                    'product_id' => $service["product_id"],
                                    'service_staff_id' => !empty($service["service_staff_id"]) ? $service["service_staff_id"] : null,
                                    'quantity' => !empty($service["quantity"]) ? $service["quantity"] : 1,
                                    'unit_price' => !empty($service["unit_price"]) ? $service["unit_price"] : 0,
                                    'line_total' => !empty($service["line_total"]) ? $service["line_total"] : 0,
                                ]);
                            }
                        }
                    }

                    $output = ['success' => 1,
                        'msg' => trans('lang_v1.added_success'),
                    ];

                    //Send notification to customer
                    if (isset($input['send_notification']) && $input['send_notification'] == 1) {
                        $output['send_notification'] = 1;
                        $output['notification_url'] = action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $booking->id, 'template_for' => 'new_booking']);
                    }
                } else {
                    $time_range = $this->commonUtil->format_date($existing_booking->booking_start, true).' ~ '.
                                    $this->commonUtil->format_date($existing_booking->booking_end, true);

                    $output = ['success' => 0,
                        'msg' => trans(
                            'restaurant.booking_not_available',
                            ['customer_name' => $existing_booking->customer->name,
                                'booking_time_range' => $time_range, ]
                        ),
                    ];
                }
            } else {
                exit(__('messages.something_went_wrong'));
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $booking = Booking::where('business_id', $business_id)
                                ->where('id', $id)
                                ->with(['table', 'customer', 'correspondent', 'waiter', 'location'])
                                ->first();
            if (! empty($booking)) {
                $booking_start = $this->commonUtil->format_date($booking->booking_start, true);
                $booking_end = $this->commonUtil->format_date($booking->booking_end, true);

                $booking_statuses = [
                    'waiting' => __('lang_v1.waiting'),
                    'booked' => __('restaurant.booked'),
                    'completed' => __('restaurant.completed'),
                    'cancelled' => __('restaurant.cancelled'),
                ];

                return view('restaurant.booking.show', compact('booking', 'booking_start', 'booking_end', 'booking_statuses'));
            }
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function edit(Booking $booking)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $business_id = $request->session()->get('user.business_id');
            $booking = Booking::where('business_id', $business_id)
                                ->find($id);
            if (! empty($booking)) {
                $booking->booking_status = $request->booking_status;
                $booking->save();
            }

            $output = ['success' => 1,
                'msg' => trans('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $business_id = request()->session()->get('user.business_id');
            $booking = Booking::where('business_id', $business_id)
                                ->where('id', $id)
                                ->delete();
            $output = ['success' => 1,
                'msg' => trans('lang_v1.deleted_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Convert booking to invoice/sell.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function convertToInvoice($id)
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        
        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');
            
            $booking = Booking::where('business_id', $business_id)
                ->where('id', $id)
                ->with(['services.product', 'services.serviceStaff', 'customer', 'location'])
                ->first();
            
            if (empty($booking)) {
                return response()->json(['success' => 0, 'msg' => 'Booking not found.']);
            }
            
            if ($booking->booking_status == 'cancelled') {
                return response()->json(['success' => 0, 'msg' => 'Cannot convert cancelled booking.']);
            }
            
            // Generate invoice number
            $transactionUtil = app(\App\Utils\TransactionUtil::class);
            $invoice_no = $transactionUtil->getInvoiceNumber($business_id, 'final', $booking->location_id);
            
            // Create a sell from the booking
            $total = 0;
            
            // Create the transaction (sell) record
            $sell = new \App\Transaction();
            $sell->business_id = $business_id;
            $sell->location_id = $booking->location_id;
            $sell->contact_id = $booking->contact_id;
            $sell->transaction_date = now()->format('Y-m-d H:i:s');
            $sell->created_by = $user_id;
            $sell->total_before_tax = $total;
            $sell->final_total = $total;
            $sell->type = 'sell';
            $sell->status = 'final';
            $sell->payment_status = 'due';
            $sell->invoice_no = $invoice_no;
            $sell->save();
            
            // Add sell lines
            foreach ($booking->services as $service) {
                $variation = optional(optional($service->product->product_variations->first())->variations->first());
                $variation_id = $variation->id ?? null;
                
                // If no variation found, get the default variation for this product
                if (empty($variation_id)) {
                    $defaultVariation = \App\Variation::where('product_id', $service->product_id)->first();
                    $variation_id = $defaultVariation ? $defaultVariation->id : null;
                }
                
                // If still no variation, skip this line
                if (empty($variation_id)) {
                    continue;
                }
                
                $line_total = $service->quantity * $service->unit_price;
                $total += $line_total;
                
                $sellLine = new \App\TransactionSellLine();
                $sellLine->transaction_id = $sell->id;
                $sellLine->product_id = $service->product_id;
                $sellLine->variation_id = $variation_id;
                $sellLine->quantity = $service->quantity;
                $sellLine->unit_price = $service->unit_price;
                $sellLine->unit_price_inc_tax = $service->unit_price;
                $sellLine->unit_price_before_discount = $service->unit_price;
                $sellLine->item_tax = 0;
                $sellLine->res_service_staff_id = $service->service_staff_id ?? null;
                $sellLine->save();
            }
            
            // Update total
            $sell->total_before_tax = $total;
            $sell->final_total = $total;
            $sell->save();
            
            // Update booking status
            $booking->booking_status = 'completed';
            $booking->save();
            
            $output = [
                'success' => 1,
                'msg' => 'Booking converted to invoice successfully. Opening POS for payment...',
                'sell_id' => $sell->id,
                'pos_url' => action([\App\Http\Controllers\SellPosController::class, 'edit'], [$sell->id]),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());
            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }
        
        return $output;
    }

    /**
     * Retrieves todays bookings
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function getTodaysBookings()
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');
            $today = \Carbon::now()->format('Y-m-d');
            $query = Booking::where('business_id', $business_id)
                        ->where('booking_status', 'booked')
                        ->whereDate('booking_start', $today)
                        ->with(['table', 'customer', 'correspondent', 'waiter', 'location']);

            if (! empty(request()->location_id)) {
                $query->where('location_id', request()->location_id);
            }

            if (! auth()->user()->hasPermissionTo('crud_all_bookings') && ! $this->commonUtil->is_admin(auth()->user(), $business_id)) {
                $query->where(function ($query) use ($user_id) {
                    $query->where('created_by', $user_id)
                        ->orWhere('correspondent_id', $user_id)
                        ->orWhere('waiter_id', $user_id);
                });

                //$query->where('created_by', $user_id);
            }

            return Datatables::of($query)
                ->editColumn('table', function ($row) {
                    return ! empty($row->table->name) ? $row->table->name : '--';
                })
                ->editColumn('customer', function ($row) {
                    return ! empty($row->customer->name) ? $row->customer->name : '--';
                })
                ->editColumn('correspondent', function ($row) {
                    return ! empty($row->correspondent->user_full_name) ? $row->correspondent->user_full_name : '--';
                })
                ->editColumn('waiter', function ($row) {
                    return ! empty($row->waiter->user_full_name) ? $row->waiter->user_full_name : '--';
                })
                ->editColumn('location', function ($row) {
                    return ! empty($row->location->name) ? $row->location->name : '--';
                })
                ->editColumn('booking_start', function ($row) {
                    return $this->commonUtil->format_date($row->booking_start, true);
                })
                ->editColumn('booking_end', function ($row) {
                    return $this->commonUtil->format_date($row->booking_end, true);
                })
               ->removeColumn('id')
                ->make(true);
        }
    }
}
