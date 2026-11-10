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
                                ->with(['table', 'customer', 'correspondent', 'waiter', 'location', 'services.product', 'services.serviceStaff', 'tips.staff'])
                                ->first();
            if (! empty($booking)) {
                // If services relation is empty, check pos_appointments for matching appointment
                if ($booking->services->isEmpty()) {
                    $posAppt = \DB::table('pos_appointments')
                        ->where('business_id', $business_id)
                        ->where(function($q) use ($booking) {
                            $q->where('contact_id', $booking->contact_id)
                              ->where('appointment_start', $booking->booking_start);
                        })
                        ->first();

                    if ($posAppt && !empty($posAppt->service_name)) {
                        $serviceNames = explode(',', $posAppt->service_name);
                        $totalCount = count($serviceNames);
                        $unitPrice = $totalCount > 0 ? ((float)$posAppt->service_price / $totalCount) : (float)$posAppt->service_price;
                        foreach ($serviceNames as $svcName) {
                            $trimmedName = trim($svcName);
                            if (empty($trimmedName)) continue;
                            $prod = \App\Product::where('business_id', $business_id)
                                ->where('name', $trimmedName)
                                ->first();
                            if (!$prod) {
                                $prod = \App\Product::where('business_id', $business_id)
                                    ->where('name', 'LIKE', '%' . $trimmedName . '%')
                                    ->first();
                            }
                            if ($prod) {
                                \App\BookingService::create([
                                    'booking_id' => $booking->id,
                                    'product_id' => $prod->id,
                                    'service_staff_id' => $booking->waiter_id ?? $posAppt->staff_id,
                                    'quantity' => 1,
                                    'unit_price' => $unitPrice,
                                    'line_total' => $unitPrice,
                                ]);
                            }
                        }
                        $booking->load(['services.product', 'services.serviceStaff']);
                    }
                }

                $booking_start = $this->commonUtil->format_date($booking->booking_start, true);
                $booking_end = $this->commonUtil->format_date($booking->booking_end, true);

                $booking_statuses = [
                    'waiting' => __('lang_v1.waiting'),
                    'booked' => __('restaurant.booked'),
                    'confirmed' => __('Confirmed'),
                    'completed' => __('restaurant.completed'),
                    'cancelled' => __('restaurant.cancelled'),
                ];

                // Load available services / products for adding more services
                $service_products = \App\Product::where('business_id', $business_id)
                    ->where('is_inactive', 0)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();

                // Load service staff
                $service_staff = \App\User::forDropdown($business_id, false);

                // Load tips for this booking
                $tips = \App\StaffTip::where('business_id', $business_id)
                    ->where('appointment_id', $booking->id)
                    ->with('staff')
                    ->orderBy('created_at', 'desc')
                    ->get();

                return view('restaurant.booking.show', compact('booking', 'booking_start', 'booking_end', 'booking_statuses', 'service_products', 'service_staff', 'tips'));
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
                        ->whereIn('booking_status', ['booked', 'confirmed', 'waiting', 'completed'])
                        ->whereDate('booking_start', $today)
                        ->with(['table', 'customer', 'correspondent', 'waiter', 'location', 'services.product', 'tips']);

            if (! empty(request()->location_id)) {
                $query->where('location_id', request()->location_id);
            }

            if (! auth()->user()->hasPermissionTo('crud_all_bookings') && ! $this->commonUtil->is_admin(auth()->user(), $business_id)) {
                $query->where(function ($query) use ($user_id) {
                    $query->where('created_by', $user_id)
                        ->orWhere('correspondent_id', $user_id)
                        ->orWhere('waiter_id', $user_id);
                });
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
                ->addColumn('services', function ($row) {
                    if ($row->services && $row->services->count() > 0) {
                        return $row->services->map(function($s) {
                            return $s->product ? $s->product->name : 'Service';
                        })->implode(', ');
                    }
                    if (!empty($row->booking_note)) {
                        return e($row->booking_note);
                    }
                    return '--';
                })
                ->addColumn('total_amount', function ($row) {
                    $amt = $row->total_services_amount;
                    return '<span class="badge bg-primary" style="font-size: 12px; font-weight: bold;">KD ' . number_format($amt, 3) . '</span>';
                })
                ->addColumn('tips_amount', function ($row) {
                    $tips = $row->total_tips_amount;
                    if ($tips > 0) {
                        return '<span class="badge bg-green" style="font-size: 12px; font-weight: bold;">KD ' . number_format($tips, 3) . '</span>';
                    }
                    return '<span class="text-muted">--</span>';
                })
                ->addColumn('action', function ($row) {
                    return '<button type="button" data-href="' . action([\App\Http\Controllers\Restaurant\BookingController::class, 'show'], [$row->id]) . '" class="btn btn-xs btn-info btn-modal" data-container=".view_modal"><i class="fa fa-eye"></i> ' . __('messages.view') . '</button>';
                })
                ->rawColumns(['total_amount', 'tips_amount', 'action', 'services'])
                ->make(true);
        }
    }

    /**
     * Add a service to an existing booking (Scenario 1 on Main Website).
     */
    public function addService(Request $request, $id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $booking = Booking::where('business_id', $business_id)->findOrFail($id);

            $productId = $request->input('product_id');
            $quantity = (float) $request->input('quantity', 1);
            $unitPrice = (float) $request->input('unit_price', 0);
            $staffId = $request->input('service_staff_id') ?: $booking->waiter_id;
            $lineTotal = round($quantity * $unitPrice, 3);

            $service = \App\BookingService::create([
                'booking_id' => $booking->id,
                'product_id' => $productId,
                'service_staff_id' => $staffId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'notes' => $request->input('notes'),
            ]);

            $product = \App\Product::find($productId);
            $serviceName = $product ? $product->name : 'Service';

            // Append to booking_note
            $addition = $serviceName . ' (KD ' . number_format($lineTotal, 3) . ')';
            $booking->booking_note = !empty($booking->booking_note) ? ($booking->booking_note . ', ' . $addition) : $addition;
            $booking->save();

            // Also update pos_appointments if exists
            $posAppt = \DB::table('pos_appointments')
                ->where('business_id', $business_id)
                ->where(function($q) use ($booking) {
                    $q->where('contact_id', $booking->contact_id)
                      ->where('appointment_start', $booking->booking_start);
                })
                ->first();

            if ($posAppt) {
                $newSvcName = !empty($posAppt->service_name) ? ($posAppt->service_name . ', ' . $serviceName) : $serviceName;
                $newPrice = (float)$posAppt->service_price + $lineTotal;
                \DB::table('pos_appointments')->where('id', $posAppt->id)->update([
                    'service_name' => $newSvcName,
                    'service_price' => $newPrice,
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'msg' => __('Service added successfully'),
                'service' => $service,
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ], 500);
        }
    }

    /**
     * Record a tip for staff member on a booking (Scenario 2 on Main Website).
     */
    public function recordTip(Request $request, $id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $booking = Booking::where('business_id', $business_id)->findOrFail($id);

            $staffId = $request->input('staff_id');
            $amount = (float) $request->input('amount', 0);
            $paymentMethod = $request->input('payment_method', 'cash');

            if ($amount <= 0) {
                return response()->json(['success' => false, 'msg' => 'Please enter a valid tip amount.'], 422);
            }

            $tip = \App\StaffTip::create([
                'business_id' => $business_id,
                'staff_id' => $staffId,
                'appointment_id' => $booking->id,
                'location_id' => $booking->location_id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'status' => 'pending',
                'notes' => $request->input('notes'),
            ]);

            return response()->json([
                'success' => true,
                'msg' => __('Tip recorded successfully'),
                'tip' => $tip,
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ], 500);
        }
    }

    /**
     * Staff tips report & payout overview on main website (Scenario 2 & 3).
     */
    public function staffTipsReport(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $staff_members = \App\User::forDropdown($business_id, false);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $staffId = $request->input('staff_id');
        $status = $request->input('status');

        $query = \App\StaffTip::where('business_id', $business_id)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with(['staff', 'booking.customer', 'transaction', 'paidOutBy']);

        if (!empty($staffId)) {
            $query->where('staff_id', $staffId);
        }
        if (!empty($status) && in_array($status, ['pending', 'paid_out'])) {
            $query->where('status', $status);
        }

        $tips = $query->orderBy('created_at', 'desc')->get();

        $totalTips = (float) $tips->sum('amount');
        $pendingTips = (float) $tips->where('status', 'pending')->sum('amount');
        $paidTips = (float) $tips->where('status', 'paid_out')->sum('amount');

        // Staff breakdown summary
        $staffSummary = [];
        $groupedByStaff = $tips->groupBy('staff_id');
        foreach ($groupedByStaff as $sId => $staffTips) {
            $staffUser = $staffTips->first()->staff;
            $staffSummary[] = [
                'staff_id' => $sId,
                'staff_name' => $staffUser ? $staffUser->user_full_name : 'Staff #' . $sId,
                'total_tips' => (float) $staffTips->sum('amount'),
                'pending_tips' => (float) $staffTips->where('status', 'pending')->sum('amount'),
                'paid_tips' => (float) $staffTips->where('status', 'paid_out')->sum('amount'),
                'count' => $staffTips->count(),
            ];
        }

        return view('restaurant.booking.staff_tips', compact(
            'tips',
            'staff_members',
            'startDate',
            'endDate',
            'staffId',
            'status',
            'totalTips',
            'pendingTips',
            'paidTips',
            'staffSummary'
        ));
    }

    /**
     * Mark pending tips as paid out.
     */
    public function payoutStaffTips(Request $request)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $userId = request()->session()->get('user.id');
            $staffId = $request->input('staff_id');
            $tipIds = $request->input('tip_ids');

            $query = \App\StaffTip::where('business_id', $business_id)
                ->where('status', 'pending');

            if (!empty($staffId)) {
                $query->where('staff_id', $staffId);
            }
            if (!empty($tipIds) && is_array($tipIds)) {
                $query->whereIn('id', $tipIds);
            }

            $count = $query->update([
                'status' => 'paid_out',
                'paid_out_at' => now(),
                'paid_out_by' => $userId,
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'msg' => __(':count tips successfully paid out.', ['count' => $count]),
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ], 500);
        }
    }
}
