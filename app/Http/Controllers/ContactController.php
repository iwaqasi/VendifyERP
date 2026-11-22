<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Exports\ContactsExport;
use App\Exports\ContactTemplateExport;
use App\Notifications\CustomerNotification;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use DB;
use Excel;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;
use App\Events\ContactCreatedOrModified;

class ContactController extends Controller
{
    protected $commonUtil;

    protected $contactUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    protected $notificationUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(
        Util $commonUtil,
        ModuleUtil $moduleUtil,
        TransactionUtil $transactionUtil,
        NotificationUtil $notificationUtil,
        ContactUtil $contactUtil
    ) {
        $this->commonUtil = $commonUtil;
        $this->contactUtil = $contactUtil;
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
        $this->notificationUtil = $notificationUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $type = request()->get('type');

        $types = ['supplier', 'customer'];

        if (empty($type) || ! in_array($type, $types)) {
            return redirect()->back();
        }

        if (request()->ajax()) {
            if ($type == 'supplier') {
                return $this->indexSupplier();
            } elseif ($type == 'customer') {
                return $this->indexCustomer();
            } else {
                exit('Not Found');
            }
        }

        $reward_enabled = (request()->session()->get('business.enable_rp') == 1 && in_array($type, ['customer'])) ? true : false;

        $users = User::forDropdown($business_id);

        $customer_groups = [];
        if ($type == 'customer') {
            $customer_groups = CustomerGroup::forDropdown($business_id);
        }

        return view('contact.index')
            ->with(compact('type', 'reward_enabled', 'customer_groups', 'users'));
    }

    /**
     * Returns the database object for supplier
     *
     * @return \Illuminate\Http\Response
     */
    private function indexSupplier()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $contact = $this->contactUtil->getContactQuery($business_id, 'supplier');

        if (request()->has('has_purchase_due')) {
			$contact->havingRaw('(IFNULL(total_purchase, 0) - IFNULL(purchase_paid, 0) - IFNULL(total_ledger_discount, 0)) > 0');
		}

        if (request()->has('has_purchase_return')) {
            $contact->havingRaw('total_purchase_return > 0');
        }

        if (request()->has('has_advance_balance')) {
            $contact->where('balance', '>', 0);
        }

        if (request()->has('has_opening_balance')) {
            $contact->havingRaw('opening_balance > 0');
        }

        if (! empty(request()->input('contact_status'))) {
            $contact->where('contacts.contact_status', request()->input('contact_status'));
        }

        if (! empty(request()->input('assigned_to'))) {
            $contact->join('user_contact_access AS uc', 'contacts.id', 'uc.contact_id')
                ->where('uc.user_id', request()->input('assigned_to'));
        }

        return Datatables::of($contact)
            ->addColumn('address', '{{implode(", ", array_filter([$address_line_1, $address_line_2, $city, $state, $country, $zip_code]))}}')
            ->addColumn(
                'due',
                '<span class="contact_due" data-orig-value="{{$total_purchase - $purchase_paid - $total_ledger_discount}}" data-highlight=false>@format_currency($total_purchase - $purchase_paid - $total_ledger_discount)</span>'
            )
            ->addColumn(
                'return_due',
                '<span class="return_due" data-orig-value="{{$total_purchase_return - $purchase_return_paid}}" data-highlight=false>@format_currency($total_purchase_return - $purchase_return_paid)'
            )
            ->addColumn(
                'action',
                function ($row) {
                    $html = '<div class="btn-group">
                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max  dropdown-toggle" 
                        data-toggle="dropdown" aria-expanded="false">'.
                        __('messages.actions').
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=purchase" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay').'</a></li>';

                    $return_due = $row->total_purchase_return - $row->purchase_return_paid;
                    if ($return_due > 0) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=purchase_return" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.receive_purchase_return_due').'</a></li>';
                    }

                    if (auth()->user()->can('supplier.view') || auth()->user()->can('supplier.view_own')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('supplier.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'edit'], [$row->id]).'" class="edit_contact_button"><i class="glyphicon glyphicon-edit"></i>'.__('messages.edit').'</a></li>';
                    }
                    if (auth()->user()->can('supplier.delete')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'destroy'], [$row->id]).'" class="delete_contact_button"><i class="glyphicon glyphicon-trash"></i>'.__('messages.delete').'</a></li>';
                    }

                    if (auth()->user()->can('customer.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'updateStatus'], [$row->id]).'"class="update_contact_status"><i class="fas fa-power-off"></i>';

                        if ($row->contact_status == 'active') {
                            $html .= __('messages.deactivate');
                        } else {
                            $html .= __('messages.activate');
                        }

                        $html .= '</a></li>';
                    }

                    $html .= '<li class="divider"></li>';
                    if (auth()->user()->can('supplier.view')) {
                        $html .= '
                                <li>
                                    <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=ledger">
                                        <i class="fas fa-scroll" aria-hidden="true"></i>
                                        '.__('lang_v1.ledger').'
                                    </a>
                                </li>';

                        if (in_array($row->type, ['both', 'supplier'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=purchase">
                                    <i class="fas fa-arrow-circle-down" aria-hidden="true"></i>
                                    '.__('purchase.purchases').'
                                </a>
                            </li>
                            <li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=stock_report">
                                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                                    '.__('report.stock_report').'
                                </a>
                            </li>';
                        }

                        if (in_array($row->type, ['both', 'customer'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=sales">
                                    <i class="fas fa-arrow-circle-up" aria-hidden="true"></i>
                                    '.__('sale.sells').'
                                </a>
                            </li>';
                        }

                        $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=documents_and_notes">
                                    <i class="fas fa-paperclip" aria-hidden="true"></i>
                                     '.__('lang_v1.documents_and_notes').'
                                </a>
                            </li>';
                    }
                    $html .= '</ul></div>';

                    return $html;
                }
            )
            ->editColumn('opening_balance', function ($row) {
                $html = '<span data-orig-value="'.$row->opening_balance.'">'.$this->transactionUtil->num_f($row->opening_balance, true).'</span>';

                return $html;
            })
            ->editColumn('balance', function ($row) {
                $html = '<span data-orig-value="'.$row->balance.'">'.$this->transactionUtil->num_f($row->balance, true).'</span>';

                return $html;
            })
            ->editColumn('pay_term', '
                @if(!empty($pay_term_type) && !empty($pay_term_number))
                    {{$pay_term_number}}
                    @lang("lang_v1.".$pay_term_type)
                @endif
            ')
            ->editColumn('name', function ($row) {
                if ($row->contact_status == 'inactive') {
                    return e($row->name).' <small class="label pull-right bg-red no-print">'.__('lang_v1.inactive').'</small>';
                } else {
                    return e($row->name);
                }
            })
            ->editColumn('created_at', '{{@format_date($created_at)}}')
            ->removeColumn('opening_balance_paid')
            ->removeColumn('type')
            ->removeColumn('id')
            ->removeColumn('total_purchase')
            ->removeColumn('purchase_paid')
            ->removeColumn('total_purchase_return')
            ->removeColumn('purchase_return_paid')
            ->filterColumn('address', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('address_line_1', 'like', "%{$keyword}%")
                    ->orWhere('address_line_2', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhere('state', 'like', "%{$keyword}%")
                    ->orWhere('country', 'like', "%{$keyword}%")
                    ->orWhere('zip_code', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(COALESCE(address_line_1, ''), ', ', COALESCE(address_line_2, ''), ', ', COALESCE(city, ''), ', ', COALESCE(state, ''), ', ', COALESCE(country, '') ) like ?", ["%{$keyword}%"]);
                });
            })
            ->rawColumns(['action', 'opening_balance', 'pay_term', 'due', 'return_due', 'name', 'balance'])
            ->make(true);
    }

    /**
     * Returns the database object for customer
     *
     * @return \Illuminate\Http\Response
     */
    private function indexCustomer()
    {
        if (! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->contactUtil->is_admin(auth()->user());

        $query = $this->contactUtil->getContactQuery($business_id, 'customer');

        if (request()->has('has_sell_due')) {
            $query->havingRaw('(COALESCE(total_invoice, 0) - COALESCE(invoice_received, 0) - COALESCE(total_ledger_discount, 0) - COALESCE(total_sell_return, 0) + COALESCE(sell_return_paid, 0)) > 0');
        }

        if (request()->has('has_sell_return')) {
            $query->havingRaw('total_sell_return > 0');
        }

        if (request()->has('has_advance_balance')) {
            $query->where('balance', '>', 0);
        }

        if (request()->has('has_opening_balance')) {
            $query->havingRaw('opening_balance > 0');
        }

        if (! empty(request()->input('assigned_to'))) {
            $query->join('user_contact_access AS uc', 'contacts.id', 'uc.contact_id')
                ->where('uc.user_id', request()->input('assigned_to'));
        }

        $has_no_sell_from = request()->input('has_no_sell_from', null);

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_one_month')) ||
            ($has_no_sell_from == 'one_month' && (auth()->user()->can('customer_with_no_sell_one_month') || auth()->user()->can('customer_irrespective_of_sell')))
            ) {
            $from_transaction_date = \Carbon::now()->subDays(30)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_three_month')) ||
            ($has_no_sell_from == 'three_months' && (auth()->user()->can('customer_with_no_sell_three_month') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subMonths(3)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_six_month')) ||
            ($has_no_sell_from == 'six_months' && (auth()->user()->can('customer_with_no_sell_six_month') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subMonths(6)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if ((! $is_admin && auth()->user()->can('customer_with_no_sell_one_year')) ||
            ($has_no_sell_from == 'one_year' && (auth()->user()->can('customer_with_no_sell_one_year') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subYear()->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (! empty(request()->input('customer_group_id'))) {
            $query->where('contacts.customer_group_id', request()->input('customer_group_id'));
        }

        if (! empty(request()->input('contact_status'))) {
            $query->where('contacts.contact_status', request()->input('contact_status'));
        }

        $contacts = Datatables::of($query)
            ->addColumn('address', '{{implode(", ", array_filter([$address_line_1, $address_line_2, $city, $state, $country, $zip_code]))}}')
        //    + $sell_return_paid add this in due because after paymnet for sell return not calculated 
            ->addColumn(
                'due',
                '<span class="contact_due" data-orig-value="{{$total_invoice - $invoice_received - $total_ledger_discount - $total_sell_return  + $sell_return_paid}}" data-highlight=true>@format_currency($total_invoice - $invoice_received - $total_ledger_discount -  $total_sell_return + $sell_return_paid)  </span>'
            )
            ->addColumn(
                'return_due',
                '<span class="return_due" data-orig-value="{{$total_sell_return - $sell_return_paid}}" data-highlight=false>@format_currency($total_sell_return - $sell_return_paid)</span>'
            )
            ->addColumn(
                'action',
                function ($row) {
                    $html = '<div class="btn-group">
                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" 
                        data-toggle="dropdown" aria-expanded="false">'.
                        __('messages.actions').
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=sell" class="pay_sale_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay').'</a></li>';
                    $return_due = $row->total_sell_return - $row->sell_return_paid;
                    if ($return_due > 0) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=sell_return" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay_sell_return_due').'</a></li>';
                    }

                    if (auth()->user()->can('customer.view') || auth()->user()->can('customer.view_own')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('customer.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'edit'], [$row->id]).'" class="edit_contact_button"><i class="glyphicon glyphicon-edit"></i>'.__('messages.edit').'</a></li>';
                    }
                    if (! $row->is_default && auth()->user()->can('customer.delete')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'destroy'], [$row->id]).'" class="delete_contact_button"><i class="glyphicon glyphicon-trash"></i>'.__('messages.delete').'</a></li>';
                    }

                    if (auth()->user()->can('customer.update')) {
                    if(!$row->is_default){
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'updateStatus'], [$row->id]).'"class="update_contact_status"><i class="fas fa-power-off"></i>';

                        if ($row->contact_status == 'active') {
                            $html .= __('messages.deactivate');
                        } else {
                            $html .= __('messages.activate');
                        }
                        $html .= '</a></li>';
                    }
                       
                    }

                    $html .= '<li class="divider"></li>';
                    if (auth()->user()->can('customer.view')) {
                        $html .= '
                                <li>
                                    <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=ledger">
                                        <i class="fas fa-scroll" aria-hidden="true"></i>
                                        '.__('lang_v1.ledger').'
                                    </a>
                                </li>';

                        if (in_array($row->type, ['both', 'supplier'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=purchase">
                                    <i class="fas fa-arrow-circle-down" aria-hidden="true"></i>
                                    '.__('purchase.purchases').'
                                </a>
                            </li>
                            <li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=stock_report">
                                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                                    '.__('report.stock_report').'
                                </a>
                            </li>';
                        }

                        if (in_array($row->type, ['both', 'customer'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=sales">
                                    <i class="fas fa-arrow-circle-up" aria-hidden="true"></i>
                                    '.__('sale.sells').'
                                </a>
                            </li>';
                        }

                        $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=documents_and_notes">
                                    <i class="fas fa-paperclip" aria-hidden="true"></i>
                                     '.__('lang_v1.documents_and_notes').'
                                </a>
                            </li>';
                    }
                    $html .= '</ul></div>';

                    return $html;
                }
            )
            ->editColumn('opening_balance', function ($row) {
                $html = '<span data-orig-value="'.$row->opening_balance.'">'.$this->transactionUtil->num_f($row->opening_balance, true).'</span>';

                return $html;
            })
            ->editColumn('balance', function ($row) {
                $html = '<span data-orig-value="'.$row->balance.'">'.$this->transactionUtil->num_f($row->balance, true).'</span>';

                return $html;
            })
            ->editColumn('credit_limit', function ($row) {
                $html = __('lang_v1.no_limit');
                if (! is_null($row->credit_limit)) {
                    $html = '<span data-orig-value="'.$row->credit_limit.'">'.$this->transactionUtil->num_f($row->credit_limit, true).'</span>';
                }

                return $html;
            })
            ->editColumn('pay_term', '
                @if(!empty($pay_term_type) && !empty($pay_term_number))
                    {{$pay_term_number}}
                    @lang("lang_v1.".$pay_term_type)
                @endif
            ')
            ->editColumn('name', function ($row) {
                $name = e($row->name);
                if ($row->contact_status == 'inactive') {
                    $name = e($row->name).' <small class="label pull-right bg-red no-print">'.__('lang_v1.inactive').'</small>';
                }

                if (! empty($row->converted_by)) {
                    $name .= '<span class="label bg-info label-round no-print" data-toggle="tooltip" title="Converted from leads"><i class="fas fa-sync-alt"></i></span>';
                }

                return $name;
            })
            ->editColumn('total_rp', '{{$total_rp ?? 0}}')
            ->editColumn('created_at', '{{@format_date($created_at)}}')
            ->removeColumn('total_invoice')
            ->removeColumn('opening_balance_paid')
            ->removeColumn('invoice_received')
            ->removeColumn('state')
            ->removeColumn('country')
            ->removeColumn('city')
            ->removeColumn('type')
            ->removeColumn('id')
            ->removeColumn('is_default')
            ->removeColumn('total_sell_return')
            ->removeColumn('sell_return_paid')
            ->filterColumn('address', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('address_line_1', 'like', "%{$keyword}%")
                    ->orWhere('address_line_2', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhere('state', 'like', "%{$keyword}%")
                    ->orWhere('country', 'like', "%{$keyword}%")
                    ->orWhere('zip_code', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(COALESCE(address_line_1, ''), ', ', COALESCE(address_line_2, ''), ', ', COALESCE(city, ''), ', ', COALESCE(state, ''), ', ', COALESCE(country, '') ) like ?", ["%{$keyword}%"]);
                });
            });
        $reward_enabled = (request()->session()->get('business.enable_rp') == 1) ? true : false;
        if (! $reward_enabled) {
            $contacts->removeColumn('total_rp');
        }

        return $contacts->rawColumns(['action', 'opening_balance', 'credit_limit', 'pay_term', 'due', 'return_due', 'name', 'balance'])
                        ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        $types = [];
        if (auth()->user()->can('supplier.create') || auth()->user()->can('supplier.view_own')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create') || auth()->user()->can('customer.view_own')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create') || auth()->user()->can('supplier.view_own') || auth()->user()->can('customer.view_own')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        $customer_groups = CustomerGroup::forDropdown($business_id);
        $selected_type = request()->type;

        $module_form_parts = $this->moduleUtil->getModuleData('contact_form_part');

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        return view('contact.create')
            ->with(compact('types', 'customer_groups', 'selected_type', 'module_form_parts', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }

            $input = $request->only(['type', 'supplier_business_name',
                'prefix', 'first_name', 'middle_name', 'last_name', 'tax_number', 'pay_term_number', 'pay_term_type', 'mobile', 'landline', 'alternate_number', 'city', 'state', 'country', 'address_line_1', 'address_line_2', 'customer_group_id', 'zip_code', 'contact_id', 'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4', 'custom_field5', 'custom_field6', 'custom_field7', 'custom_field8', 'custom_field9', 'custom_field10', 'email', 'shipping_address', 'position', 'dob', 'shipping_custom_field_details', 'assigned_to_users', 'land_mark', 'street_name', 'building_number', 'additional_number']);

            $name_array = [];

            if (! empty($input['prefix'])) {
                $name_array[] = $input['prefix'];
            }
            if (! empty($input['first_name'])) {
                $name_array[] = $input['first_name'];
            }
            if (! empty($input['middle_name'])) {
                $name_array[] = $input['middle_name'];
            }
            if (! empty($input['last_name'])) {
                $name_array[] = $input['last_name'];
            }

            $input['contact_type'] = $request->input('contact_type_radio');

            $input['name'] = trim(implode(' ', $name_array));

            if (! empty($request->input('is_export'))) {
                $input['is_export'] = true;
                $input['export_custom_field_1'] = $request->input('export_custom_field_1');
                $input['export_custom_field_2'] = $request->input('export_custom_field_2');
                $input['export_custom_field_3'] = $request->input('export_custom_field_3');
                $input['export_custom_field_4'] = $request->input('export_custom_field_4');
                $input['export_custom_field_5'] = $request->input('export_custom_field_5');
                $input['export_custom_field_6'] = $request->input('export_custom_field_6');
            }

            if (! empty($input['dob'])) {
                $input['dob'] = $this->commonUtil->uf_date($input['dob']);
            }

            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            $input['credit_limit'] = $request->input('credit_limit') != '' ? $this->commonUtil->num_uf($request->input('credit_limit')) : null;
            $input['opening_balance'] = $this->commonUtil->num_uf($request->input('opening_balance'));

            DB::beginTransaction();
            $output = $this->contactUtil->createNewContact($input);

            event(new ContactCreatedOrModified($input, 'added'));

            $this->moduleUtil->getModuleData('after_contact_saved', ['contact' => $output['data'], 'input' => $request->input()]);

            $this->contactUtil->activityLog($output['data'], 'added');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $contact = $this->contactUtil->getContactInfo($business_id, $id);

        $is_selected_contacts = User::isSelectedContacts(auth()->user()->id);
        $user_contacts = [];
        if ($is_selected_contacts) {
            $user_contacts = auth()->user()->contactAccess->pluck('id')->toArray();
        }

        if (! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }
        if (! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }

        $reward_enabled = (request()->session()->get('business.enable_rp') == 1 && in_array($contact->type, ['customer', 'both'])) ? true : false;

        $contact_dropdown = Contact::contactDropdown($business_id, false, false);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        //get contact view type : ledger, notes etc.
        $view_type = request()->get('view');
        if (is_null($view_type)) {
            $view_type = 'ledger';
        }

        $contact_view_tabs = $this->moduleUtil->getModuleData('get_contact_view_tabs');

        $activities = Activity::forSubject($contact)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        return view('contact.show')
             ->with(compact('contact', 'reward_enabled', 'contact_dropdown', 'business_locations', 'view_type', 'contact_view_tabs', 'activities'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('supplier.update') && ! auth()->user()->can('customer.update') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $contact = Contact::where('business_id', $business_id)->find($id);

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }

            $types = [];
            if (auth()->user()->can('supplier.create')) {
                $types['supplier'] = __('report.supplier');
            }
            if (auth()->user()->can('customer.create')) {
                $types['customer'] = __('report.customer');
            }
            if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
                $types['both'] = __('lang_v1.both_supplier_customer');
            }

            $customer_groups = CustomerGroup::forDropdown($business_id);

            $ob_transaction = Transaction::where('contact_id', $id)
                                            ->where('type', 'opening_balance')
                                            ->first();
            $opening_balance = ! empty($ob_transaction->final_total) ? $ob_transaction->final_total : 0;

            //Deduct paid amount from opening balance.
            if (! empty($opening_balance)) {
                $opening_balance_paid = $this->transactionUtil->getTotalAmountPaid($ob_transaction->id);
                if (! empty($opening_balance_paid)) {
                    $opening_balance = $opening_balance - $opening_balance_paid;
                }

                $opening_balance = $this->commonUtil->num_f($opening_balance);
            }

            //Added check because $users is of no use if enable_contact_assign if false
            $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

            return view('contact.edit')
                ->with(compact('contact', 'types', 'customer_groups', 'opening_balance', 'users'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('supplier.update') && ! auth()->user()->can('customer.update') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['type', 'supplier_business_name', 'prefix', 'first_name', 'middle_name', 'last_name', 'tax_number', 'pay_term_number', 'pay_term_type', 'mobile', 'address_line_1', 'address_line_2', 'zip_code', 'dob', 'alternate_number', 'city', 'state', 'country', 'landline', 'customer_group_id', 'contact_id', 'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4', 'custom_field5', 'custom_field6', 'custom_field7', 'custom_field8', 'custom_field9', 'custom_field10', 'email', 'shipping_address', 'position', 'shipping_custom_field_details', 'export_custom_field_1', 'export_custom_field_2', 'export_custom_field_3', 'export_custom_field_4', 'export_custom_field_5',
                    'export_custom_field_6', 'assigned_to_users', 'land_mark', 'street_name', 'building_number', 'additional_number']);

                $name_array = [];

                if (! empty($input['prefix'])) {
                    $name_array[] = $input['prefix'];
                }
                if (! empty($input['first_name'])) {
                    $name_array[] = $input['first_name'];
                }
                if (! empty($input['middle_name'])) {
                    $name_array[] = $input['middle_name'];
                }
                if (! empty($input['last_name'])) {
                    $name_array[] = $input['last_name'];
                }

                $input['contact_type'] = $request->input('contact_type_radio');



                $input['name'] = trim(implode(' ', $name_array));

                $input['is_export'] = ! empty($request->input('is_export')) ? 1 : 0;

                if (! $input['is_export']) {
                    unset($input['export_custom_field_1'], $input['export_custom_field_2'], $input['export_custom_field_3'], $input['export_custom_field_4'], $input['export_custom_field_5'], $input['export_custom_field_6']);
                }

                if (! empty($input['dob'])) {
                    $input['dob'] = $this->commonUtil->uf_date($input['dob']);
                }

                $input['credit_limit'] = $request->input('credit_limit') != '' ? $this->commonUtil->num_uf($request->input('credit_limit')) : null;

                $business_id = $request->session()->get('user.business_id');

                $input['opening_balance'] = $this->commonUtil->num_uf($request->input('opening_balance'));

                if (! $this->moduleUtil->isSubscribed($business_id)) {
                    return $this->moduleUtil->expiredResponse();
                }

                $output = $this->contactUtil->updateContact($input, $id, $business_id);

                event(new ContactCreatedOrModified($output['data'], 'updated'));

                $this->contactUtil->activityLog($output['data'], 'edited');
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('supplier.delete') && ! auth()->user()->can('customer.delete') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                //Check if any transaction related to this contact exists
                $count = Transaction::where('business_id', $business_id)
                                    ->where('contact_id', $id)
                                    ->count();
                if ($count == 0) {
                    $contact = Contact::where('business_id', $business_id)->findOrFail($id);
                    if (! $contact->is_default) {
                        $log_properities = [
                            'id' => $contact->id,
                            'name' => $contact->name,
                            'supplier_business_name' => $contact->supplier_business_name,
                        ];
                        $this->contactUtil->activityLog($contact, 'contact_deleted', $log_properities);

                        //Disable login for associated users
                        User::where('crm_contact_id', $contact->id)
                            ->update(['allow_login' => 0]);

                        $contact->delete();

                        event(new ContactCreatedOrModified($contact, 'deleted'));
                    }
                    $output = ['success' => true,
                        'msg' => __('contact.deleted_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => __('lang_v1.you_cannot_delete_this_contact'),
                    ];
                }
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Retrieves list of customers, if filter is passed then filter it accordingly.
     *
     * @param  string  $q
     * @return JSON
     */
    public function getCustomers()
    {
        if (request()->ajax()) {
            $term = request()->input('q', '');

            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $contacts = Contact::where('contacts.business_id', $business_id)
                            ->leftjoin('customer_groups as cg', 'cg.id', '=', 'contacts.customer_group_id')
                            ->active();

            if (! request()->has('all_contact')) {
                $contacts->onlyCustomers();
            }

            if (! empty($term)) {
                $contacts->where(function ($query) use ($term) {
                    $query->where('contacts.name', 'like', '%'.$term.'%')
                            ->orWhere('supplier_business_name', 'like', '%'.$term.'%')
                            ->orWhere('mobile', 'like', '%'.$term.'%')
                            ->orWhere('contacts.contact_id', 'like', '%'.$term.'%');
                });
            }

            $contacts->select(
                'contacts.id',
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', contacts.name, CONCAT(contacts.name, ' (', contacts.contact_id, ')')) AS text"),
                'mobile',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'country',
                'zip_code',
                'shipping_address',
                'pay_term_number',
                'pay_term_type',
                'balance',
                'supplier_business_name',
                'cg.amount as discount_percent',
                'cg.price_calculation_type',
                'cg.selling_price_group_id',
                'shipping_custom_field_details',
                'is_export',
                'export_custom_field_1',
                'export_custom_field_2',
                'export_custom_field_3',
                'export_custom_field_4',
                'export_custom_field_5',
                'export_custom_field_6'
            );

            if (request()->session()->get('business.enable_rp') == 1) {
                $contacts->addSelect('total_rp');
            }
            $contacts = $contacts->get();

            return json_encode($contacts);
        }
    }

    /**
     * Checks if the given contact id already exist for the current business.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkContactId(Request $request)
    {
        $contact_id = $request->input('contact_id');

        $valid = 'true';
        if (! empty($contact_id)) {
            $business_id = $request->session()->get('user.business_id');
            $hidden_id = $request->input('hidden_id');

            $query = Contact::where('business_id', $business_id)
                            ->where('contact_id', $contact_id);
            if (! empty($hidden_id)) {
                $query->where('id', '!=', $hidden_id);
            }
            $count = $query->count();
            if ($count > 0) {
                $valid = 'false';
            }
        }
        echo $valid;
        exit;
    }

    /**
     * Shows import option for contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function getImportContacts()
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $zip_loaded = extension_loaded('zip') ? true : false;

        //Check if zip extension it loaded or not.
        if ($zip_loaded === false) {
            $output = ['success' => 0,
                'msg' => 'Please install/enable PHP Zip archive for import',
            ];

            return view('contact.import')
                ->with('notification', $output);
        } else {
            return view('contact.import');
        }
    }

    /**
     * Download Excel of Contacts (Suppliers / Customers)
     */
    public function downloadExcel(Request $request)
    {
        $type = $request->get('type');
        if ($type == 'supplier' && ! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        } elseif ($type == 'customer' && ! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $label = $type ? ucfirst($type) . 's' : 'Contacts';
        $filename = $label . '-' . date('Y-m-d') . '.xlsx';

        return Excel::download(new ContactsExport($type, $business_id), $filename);
    }

    /**
     * Download Template for Contacts Import
     */
    public function downloadTemplate(Request $request)
    {
        $type = $request->get('type', 'customer');
        $filename = ($type ? ucfirst($type) . '_' : '') . 'import_template.xlsx';

        return Excel::download(new ContactTemplateExport($type), $filename);
    }

    /**
     * Imports contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function postImportContacts(Request $request)
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->commonUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            //Set maximum php execution time
            ini_set('max_execution_time', 0);

            if ($request->hasFile('contacts_csv')) {
                $file = $request->file('contacts_csv');
                $parsed_array = Excel::toArray([], $file);

                if (empty($parsed_array) || empty($parsed_array[0])) {
                    throw new \Exception(__('lang_v1.file_empty'));
                }

                $sheet = $parsed_array[0];
                $header = array_shift($sheet);

                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');

                $default_type = $request->get('type', 'customer');
                if (!in_array($default_type, ['customer', 'supplier', 'both'])) {
                    $default_type = 'customer';
                }

                // Check if file has named header columns
                $has_named_headers = false;
                $col_map = [];
                if (!empty($header)) {
                    foreach ($header as $idx => $raw_h) {
                        $h = strtolower(trim((string)$raw_h));
                        if (str_contains($h, 'contact type') || str_contains($h, 'type')) $col_map['type'] = $idx;
                        elseif (str_contains($h, 'contact id') || str_contains($h, 'contact_id')) $col_map['contact_id'] = $idx;
                        elseif (str_contains($h, 'business') || str_contains($h, 'company')) $col_map['supplier_business_name'] = $idx;
                        elseif (str_contains($h, 'prefix')) $col_map['prefix'] = $idx;
                        elseif (str_contains($h, 'first') && str_contains($h, 'name')) $col_map['first_name'] = $idx;
                        elseif (str_contains($h, 'middle') && str_contains($h, 'name')) $col_map['middle_name'] = $idx;
                        elseif (str_contains($h, 'last') && str_contains($h, 'name')) $col_map['last_name'] = $idx;
                        elseif (str_contains($h, 'full') || (str_contains($h, 'name') && !isset($col_map['first_name']))) $col_map['name'] = $idx;
                        elseif (str_contains($h, 'alt') && (str_contains($h, 'phone') || str_contains($h, 'mobile') || str_contains($h, 'contact') || str_contains($h, 'number'))) $col_map['alternate_number'] = $idx;
                        elseif (str_contains($h, 'landline')) $col_map['landline'] = $idx;
                        elseif (str_contains($h, 'mobile') || str_contains($h, 'phone')) $col_map['mobile'] = $idx;
                        elseif (str_contains($h, 'email')) $col_map['email'] = $idx;
                        elseif (str_contains($h, 'tax')) $col_map['tax_number'] = $idx;
                        elseif (str_contains($h, 'opening') || str_contains($h, 'balance')) $col_map['opening_balance'] = $idx;
                        elseif (str_contains($h, 'term') && (str_contains($h, 'period') || str_contains($h, 'type'))) $col_map['pay_term_type'] = $idx;
                        elseif (str_contains($h, 'pay') || str_contains($h, 'term')) $col_map['pay_term_number'] = $idx;
                        elseif (str_contains($h, 'credit') || str_contains($h, 'limit')) $col_map['credit_limit'] = $idx;
                        elseif (str_contains($h, 'address 1') || str_contains($h, 'line 1') || str_contains($h, 'address_line_1')) $col_map['address_line_1'] = $idx;
                        elseif (str_contains($h, 'address 2') || str_contains($h, 'line 2') || str_contains($h, 'address_line_2')) $col_map['address_line_2'] = $idx;
                        elseif (str_contains($h, 'city')) $col_map['city'] = $idx;
                        elseif (str_contains($h, 'state')) $col_map['state'] = $idx;
                        elseif (str_contains($h, 'country')) $col_map['country'] = $idx;
                        elseif (str_contains($h, 'zip') || str_contains($h, 'postal')) $col_map['zip_code'] = $idx;
                        elseif (str_contains($h, 'dob') || str_contains($h, 'birth')) $col_map['dob'] = $idx;
                    }
                    if (isset($col_map['type']) || isset($col_map['first_name']) || isset($col_map['name']) || isset($col_map['supplier_business_name'])) {
                        $has_named_headers = true;
                    }
                }

                $formated_data = [];
                $contact_types_map = [
                    '1' => 'customer',
                    '2' => 'supplier',
                    '3' => 'both',
                    'customer' => 'customer',
                    'supplier' => 'supplier',
                    'both' => 'both',
                ];

                DB::beginTransaction();

                foreach ($sheet as $key => $value) {
                    $row_no = $key + 2;
                    $contact_array = [];

                    if ($has_named_headers) {
                        // Header-based extraction
                        $raw_type = isset($col_map['type']) ? strtolower(trim((string)($value[$col_map['type']] ?? ''))) : '';
                        $contact_type = $contact_types_map[$raw_type] ?? $default_type;
                        $contact_array['type'] = $contact_type;

                        $contact_array['prefix'] = isset($col_map['prefix']) ? trim((string)($value[$col_map['prefix']] ?? '')) : '';
                        $first_name = isset($col_map['first_name']) ? trim((string)($value[$col_map['first_name']] ?? '')) : '';
                        $middle_name = isset($col_map['middle_name']) ? trim((string)($value[$col_map['middle_name']] ?? '')) : '';
                        $last_name = isset($col_map['last_name']) ? trim((string)($value[$col_map['last_name']] ?? '')) : '';
                        $full_name = isset($col_map['name']) ? trim((string)($value[$col_map['name']] ?? '')) : '';
                        $biz_name = isset($col_map['supplier_business_name']) ? trim((string)($value[$col_map['supplier_business_name']] ?? '')) : '';

                        if (empty($first_name) && !empty($full_name)) {
                            $parts = explode(' ', $full_name, 2);
                            $first_name = $parts[0];
                            $last_name = $parts[1] ?? '';
                        } elseif (empty($first_name) && !empty($biz_name)) {
                            $first_name = $biz_name;
                        }

                        if (empty($first_name) && empty($biz_name)) {
                            continue; // Skip entirely blank rows
                        }

                        $contact_array['first_name'] = $first_name ?: $biz_name;
                        $contact_array['middle_name'] = $middle_name;
                        $contact_array['last_name'] = $last_name;
                        $contact_array['name'] = trim($full_name ?: implode(' ', array_filter([$contact_array['prefix'], $first_name, $middle_name, $last_name])));
                        if (!empty($biz_name)) {
                            $contact_array['supplier_business_name'] = $biz_name;
                        }

                        $c_id = isset($col_map['contact_id']) ? trim((string)($value[$col_map['contact_id']] ?? '')) : '';
                        if (!empty($c_id)) {
                            $contact_array['contact_id'] = $c_id;
                        }

                        $contact_array['mobile'] = isset($col_map['mobile']) ? trim((string)($value[$col_map['mobile']] ?? '')) : '';
                        $contact_array['alternate_number'] = isset($col_map['alternate_number']) ? trim((string)($value[$col_map['alternate_number']] ?? '')) : '';
                        $contact_array['landline'] = isset($col_map['landline']) ? trim((string)($value[$col_map['landline']] ?? '')) : '';
                        $contact_array['email'] = isset($col_map['email']) ? trim((string)($value[$col_map['email']] ?? '')) : '';
                        $contact_array['tax_number'] = isset($col_map['tax_number']) ? trim((string)($value[$col_map['tax_number']] ?? '')) : '';
                        $contact_array['opening_balance'] = isset($col_map['opening_balance']) ? trim((string)($value[$col_map['opening_balance']] ?? '')) : '';
                        $contact_array['pay_term_number'] = isset($col_map['pay_term_number']) ? trim((string)($value[$col_map['pay_term_number']] ?? '')) : '';
                        $contact_array['pay_term_type'] = isset($col_map['pay_term_type']) ? strtolower(trim((string)($value[$col_map['pay_term_type']] ?? ''))) : '';
                        $contact_array['credit_limit'] = isset($col_map['credit_limit']) ? trim((string)($value[$col_map['credit_limit']] ?? '')) : '';
                        $contact_array['address_line_1'] = isset($col_map['address_line_1']) ? trim((string)($value[$col_map['address_line_1']] ?? '')) : '';
                        $contact_array['address_line_2'] = isset($col_map['address_line_2']) ? trim((string)($value[$col_map['address_line_2']] ?? '')) : '';
                        $contact_array['city'] = isset($col_map['city']) ? trim((string)($value[$col_map['city']] ?? '')) : '';
                        $contact_array['state'] = isset($col_map['state']) ? trim((string)($value[$col_map['state']] ?? '')) : '';
                        $contact_array['country'] = isset($col_map['country']) ? trim((string)($value[$col_map['country']] ?? '')) : '';
                        $contact_array['zip_code'] = isset($col_map['zip_code']) ? trim((string)($value[$col_map['zip_code']] ?? '')) : '';
                    } else {
                        // Positional extraction (pad up to 27 columns for safe indexing)
                        while (count($value) < 27) {
                            $value[] = '';
                        }

                        $raw_type = strtolower(trim((string)$value[0]));
                        $contact_type = $contact_types_map[$raw_type] ?? $default_type;
                        $contact_array['type'] = $contact_type;

                        $contact_array['prefix'] = trim((string)$value[1]);
                        $first_name = trim((string)$value[2]);
                        $biz_name = trim((string)$value[5]);

                        if (empty($first_name) && empty($biz_name)) {
                            continue; // Skip empty row
                        }

                        $contact_array['first_name'] = $first_name ?: $biz_name;
                        $contact_array['middle_name'] = trim((string)$value[3]);
                        $contact_array['last_name'] = trim((string)$value[4]);
                        $contact_array['name'] = trim(implode(' ', array_filter([$contact_array['prefix'], $contact_array['first_name'], $contact_array['middle_name'], $contact_array['last_name']])));

                        if (!empty($biz_name)) {
                            $contact_array['supplier_business_name'] = $biz_name;
                        }

                        $contact_array['contact_id'] = trim((string)$value[6]);
                        $contact_array['tax_number'] = trim((string)$value[7]);
                        $contact_array['opening_balance'] = trim((string)$value[8]);
                        $contact_array['pay_term_number'] = trim((string)$value[9]);
                        $contact_array['pay_term_type'] = strtolower(trim((string)$value[10]));
                        $contact_array['credit_limit'] = trim((string)$value[11]);
                        $contact_array['email'] = trim((string)$value[12]);
                        $contact_array['mobile'] = trim((string)$value[13]);
                        $contact_array['alternate_number'] = trim((string)$value[14]);
                        $contact_array['landline'] = trim((string)$value[15]);
                        $contact_array['city'] = trim((string)$value[16]);
                        $contact_array['state'] = trim((string)$value[17]);
                        $contact_array['country'] = trim((string)$value[18]);
                        $contact_array['address_line_1'] = trim((string)$value[19]);
                        $contact_array['address_line_2'] = trim((string)$value[20]);
                        $contact_array['zip_code'] = trim((string)$value[21]);
                        $contact_array['dob'] = trim((string)$value[22]);
                        $contact_array['custom_field1'] = trim((string)$value[23]);
                        $contact_array['custom_field2'] = trim((string)$value[24]);
                        $contact_array['custom_field3'] = trim((string)$value[25]);
                        $contact_array['custom_field4'] = trim((string)$value[26]);
                    }

                    // Normalize and validate pay_term_type
                    if (!in_array($contact_array['pay_term_type'], ['days', 'months'])) {
                        $contact_array['pay_term_type'] = null;
                        $contact_array['pay_term_number'] = null;
                    }

                    // Normalize email
                    if (!empty($contact_array['email']) && !filter_var($contact_array['email'], FILTER_VALIDATE_EMAIL)) {
                        $contact_array['email'] = null;
                    }

                    $formated_data[] = $contact_array;
                }

                if (! empty($formated_data)) {
                    foreach ($formated_data as $contact_data) {
                        $ref_count = $this->transactionUtil->setAndGetReferenceCount('contacts');

                        // Check if contact_id is provided and if it already exists
                        if (!empty($contact_data['contact_id'])) {
                            $exists = Contact::where('business_id', $business_id)
                                ->where('contact_id', $contact_data['contact_id'])
                                ->exists();
                            if ($exists) {
                                // If already exists, update basic details or generate new ID
                                $contact = Contact::where('business_id', $business_id)
                                    ->where('contact_id', $contact_data['contact_id'])
                                    ->first();
                                $contact->update(array_filter([
                                    'name' => $contact_data['name'],
                                    'supplier_business_name' => $contact_data['supplier_business_name'] ?? null,
                                    'mobile' => $contact_data['mobile'] ?: null,
                                    'email' => $contact_data['email'] ?: null,
                                    'city' => $contact_data['city'] ?: null,
                                    'country' => $contact_data['country'] ?: null,
                                ]));
                                continue;
                            }
                        } else {
                            $contact_data['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count);
                        }

                        $opening_balance = 0;
                        if (isset($contact_data['opening_balance']) && is_numeric($contact_data['opening_balance'])) {
                            $opening_balance = (float)$contact_data['opening_balance'];
                        }
                        unset($contact_data['opening_balance']);

                        $contact_data['business_id'] = $business_id;
                        $contact_data['created_by'] = $user_id;
                        $contact_data['contact_status'] = 'active';

                        $contact = Contact::create($contact_data);

                        if ($opening_balance > 0) {
                            $this->transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $opening_balance, $user_id, false);
                        }

                        $this->transactionUtil->activityLog($contact, 'imported');
                    }
                }

                $output = ['success' => 1,
                    'msg' => __('product.file_imported_successfully') . ' (' . count($formated_data) . ' contacts processed)',
                ];

                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect()->route('contacts.import')->with('notification', $output);
        }
        $type = ! empty($contact->type) && $contact->type != 'both' ? $contact->type : ($request->get('type') ?: 'customer');

        return redirect()->action([\App\Http\Controllers\ContactController::class, 'index'], ['type' => $type])->with('status', $output);
    }

    /**
     * Shows ledger for contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function getLedger()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view') && ! auth()->user()->can('supplier.view_own') && ! auth()->user()->can('customer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $contact_id = request()->input('contact_id');

        $is_admin = $this->contactUtil->is_admin(auth()->user());

        $start_date = request()->start_date;
        $end_date = request()->end_date;
        $format = request()->format;
        $location_id = request()->location_id;

        $contact = Contact::find($contact_id);

        $is_selected_contacts = User::isSelectedContacts(auth()->user()->id);
        $user_contacts = [];
        if ($is_selected_contacts) {
            $user_contacts = auth()->user()->contactAccess->pluck('id')->toArray();
        }

        if (! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }
        if (! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }

        $line_details = ($format == 'format_3' || $format == 'format_4') ? true : false;

        $ledger_details = $this->transactionUtil->getLedgerDetails($contact_id, $start_date, $end_date, $format, $location_id, $line_details);

        $location = null;
        if (! empty($location_id)) {
            $location = BusinessLocation::where('business_id', $business_id)->find($location_id);
        }
        if (request()->input('action') == 'pdf') {
            $output_file_name = 'Ledger-'.str_replace(' ', '-', $contact->name).'-'.$start_date.'-'.$end_date.'.pdf';
            $for_pdf = true;
            if ($format == 'format_2') {
                $html = view('contact.ledger_format_2')
                        ->with(compact('ledger_details', 'contact', 'for_pdf', 'location'))->render();
            } elseif ($format == 'format_3') {
                $html = view('contact.ledger_format_3')
                    ->with(compact('ledger_details', 'contact', 'location', 'is_admin', 'for_pdf'))->render();
            } elseif ($format == 'format_4') {
                $html = view('contact.ledger_format_4')
                    ->with(compact('ledger_details', 'contact', 'location', 'is_admin', 'for_pdf'))->render();
            } else {
                $html = view('contact.ledger')
                    ->with(compact('ledger_details', 'contact', 'for_pdf', 'location'))->render();
            }

            // Use landscape orientation only for format_4
            $orientation = ($format == 'format_4') ? 'L' : 'P';
            $mpdf = $this->getMpdf($orientation);
            $mpdf->WriteHTML($html);
            $mpdf->Output($output_file_name, 'I');
            exit;
        }

        if ($format == 'format_2') {
            return view('contact.ledger_format_2')
             ->with(compact('ledger_details', 'contact', 'location'));
        } elseif ($format == 'format_3') {
            return view('contact.ledger_format_3')
             ->with(compact('ledger_details', 'contact', 'location', 'is_admin'));
        } elseif ($format == 'format_4') {
            return view('contact.ledger_format_4')
             ->with(compact('ledger_details', 'contact', 'location', 'is_admin'));
        } else {
            return view('contact.ledger')
             ->with(compact('ledger_details', 'contact', 'location', 'is_admin'));
        }
    }

    public function postCustomersApi(Request $request)
    {
        try {
            $api_token = $request->header('API-TOKEN');

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $business = Business::find($api_settings->business_id);

            $data = $request->only(['name', 'email']);

            $customer = Contact::where('business_id', $api_settings->business_id)
                                ->where('email', $data['email'])
                                ->whereIn('type', ['customer', 'both'])
                                ->first();

            if (empty($customer)) {
                $data['type'] = 'customer';
                $data['business_id'] = $api_settings->business_id;
                $data['created_by'] = $business->owner_id;
                $data['mobile'] = 0;

                $ref_count = $this->commonUtil->setAndGetReferenceCount('contacts', $business->id);

                $data['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count, $business->id);

                $customer = Contact::create($data);
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($customer);
    }

    /**
     * Function to send ledger notification
     */
    public function sendLedger(Request $request)
    {
        $notAllowed = $this->notificationUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $data = $request->only(['to_email', 'subject', 'email_body', 'cc', 'bcc', 'ledger_format']);
            $emails_array = array_map('trim', explode(',', $data['to_email']));

            $contact_id = $request->input('contact_id');
            $business_id = request()->session()->get('business.id');

            $start_date = request()->input('start_date');
            $end_date = request()->input('end_date');
            $location_id = request()->input('location_id');

            $contact = Contact::find($contact_id);

            $ledger_details = $this->transactionUtil->getLedgerDetails($contact_id, $start_date, $end_date, $data['ledger_format'], $location_id);

            $orig_data = [
                'email_body' => $data['email_body'],
                'subject' => $data['subject'],
            ];

            $tag_replaced_data = $this->notificationUtil->replaceTags($business_id, $orig_data, null, $contact);
            $data['email_body'] = $tag_replaced_data['email_body'];
            $data['subject'] = $tag_replaced_data['subject'];

            //replace balance_due
            $data['email_body'] = str_replace('{balance_due}', $this->notificationUtil->num_f($ledger_details['balance_due']), $data['email_body']);

            $data['email_settings'] = request()->session()->get('business.email_settings');

            $for_pdf = true;
            if ($data['ledger_format'] == 'format_2') {
                $html = view('contact.ledger_format_2')
                        ->with(compact('ledger_details', 'contact', 'for_pdf'))->render();
            } else {
                $html = view('contact.ledger')
                        ->with(compact('ledger_details', 'contact', 'for_pdf'))->render();
            }

            // Use landscape orientation only for format_4
            $orientation = ($data['ledger_format'] == 'format_4') ? 'L' : 'P';
            $mpdf = $this->getMpdf($orientation);
            $mpdf->WriteHTML($html);

            $path = config('constants.mpdf_temp_path');
            if (! file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $file = $path.'/'.time().'_ledger.pdf';
            $mpdf->Output($file, 'F');

            $data['attachment'] = $file;
            $data['attachment_name'] = 'ledger.pdf';
            \Notification::route('mail', $emails_array)
                    ->notify(new CustomerNotification($data));

            if (file_exists($file)) {
                unlink($file);
            }

            $output = ['success' => 1, 'msg' => __('lang_v1.notification_sent_successfully')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage(),
            ];
        }

        return $output;
    }

    /**
     * Function to get product stock details for a supplier
     */
    public function getSupplierStockReport($supplier_id)
    {
        //TODO: current stock not calculating stock transferred from other location
        $pl_query_string = $this->commonUtil->get_pl_quantity_sum_string();
        $query = PurchaseLine::join('transactions as t', 't.id', '=', 'purchase_lines.transaction_id')
                        ->join('products as p', 'p.id', '=', 'purchase_lines.product_id')
                        ->join('variations as v', 'v.id', '=', 'purchase_lines.variation_id')
                        ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                        ->join('units as u', 'p.unit_id', '=', 'u.id')
                        ->whereIn('t.type', ['purchase', 'purchase_return'])
                        ->where('t.contact_id', $supplier_id)
                        ->select(
                            'p.name as product_name',
                            'v.name as variation_name',
                            'pv.name as product_variation_name',
                            'p.type as product_type',
                            'u.short_name as product_unit',
                            'v.sub_sku',
                            DB::raw('SUM(quantity) as purchase_quantity'),
                            DB::raw('SUM(quantity_returned) as total_quantity_returned'),
                            DB::raw("SUM((SELECT SUM(TSL.quantity - TSL.quantity_returned) FROM transaction_sell_lines_purchase_lines as TSLPL 
                              JOIN transaction_sell_lines AS TSL ON TSLPL.sell_line_id=TSL.id
                              JOIN transactions AS sell ON sell.id=TSL.transaction_id
                              WHERE sell.status='final' AND sell.type='sell'
                              AND TSLPL.purchase_line_id=purchase_lines.id)) as total_quantity_sold"),
                            DB::raw("SUM((SELECT SUM(TSL.quantity - TSL.quantity_returned) FROM transaction_sell_lines_purchase_lines as TSLPL 
                              JOIN transaction_sell_lines AS TSL ON TSLPL.sell_line_id=TSL.id
                              JOIN transactions AS sell ON sell.id=TSL.transaction_id
                              WHERE sell.status='final' AND sell.type='sell_transfer'
                              AND TSLPL.purchase_line_id=purchase_lines.id)) as total_quantity_transfered"),
                            DB::raw("SUM( COALESCE(quantity - ($pl_query_string), 0) * purchase_price_inc_tax) as stock_price"),
                            DB::raw("SUM( COALESCE(quantity - ($pl_query_string), 0)) as current_stock")
                        )->groupBy('purchase_lines.variation_id');

        if (! empty(request()->location_id)) {
            $query->where('t.location_id', request()->location_id);
        }

        $product_stocks = Datatables::of($query)
                            ->editColumn('product_name', function ($row) {
                                $name = $row->product_name;
                                if ($row->product_type == 'variable') {
                                    $name .= ' - '.$row->product_variation_name.'-'.$row->variation_name;
                                }

                                return $name.' ('.$row->sub_sku.')';
                            })
                            ->editColumn('purchase_quantity', function ($row) {
                                $purchase_quantity = 0;
                                if ($row->purchase_quantity) {
                                    $purchase_quantity = (float) $row->purchase_quantity;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$purchase_quantity.'" data-unit="'.$row->product_unit.'" >'.$purchase_quantity.'</span> '.$row->product_unit;
                            })
                            ->editColumn('total_quantity_sold', function ($row) {
                                $total_quantity_sold = 0;
                                if ($row->total_quantity_sold) {
                                    $total_quantity_sold = (float) $row->total_quantity_sold;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$total_quantity_sold.'" data-unit="'.$row->product_unit.'" >'.$total_quantity_sold.'</span> '.$row->product_unit;
                            })
                            ->editColumn('total_quantity_transfered', function ($row) {
                                $total_quantity_transfered = 0;
                                if ($row->total_quantity_transfered) {
                                    $total_quantity_transfered = (float) $row->total_quantity_transfered;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$total_quantity_transfered.'" data-unit="'.$row->product_unit.'" >'.$total_quantity_transfered.'</span> '.$row->product_unit;
                            })
                            ->editColumn('stock_price', function ($row) {
                                $stock_price = 0;
                                if ($row->stock_price) {
                                    $stock_price = (float) $row->stock_price;
                                }

                                return '<span class="display_currency" data-currency_symbol=true >'.$stock_price.'</span> ';
                            })
                            ->editColumn('current_stock', function ($row) {
                                $current_stock = 0;
                                if ($row->current_stock) {
                                    $current_stock = (float) $row->current_stock;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$current_stock.'" data-unit="'.$row->product_unit.'" >'.$current_stock.'</span> '.$row->product_unit;
                            });

        return $product_stocks->rawColumns(['current_stock', 'stock_price', 'total_quantity_sold', 'purchase_quantity', 'total_quantity_transfered'])->make(true);
    }

    public function updateStatus($id)
    {
        if (! auth()->user()->can('supplier.update') && ! auth()->user()->can('customer.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $contact = Contact::where('business_id', $business_id)->find($id);
            $contact->contact_status = $contact->contact_status == 'active' ? 'inactive' : 'active';
            $contact->save();

            $output = ['success' => true,
                'msg' => __('contact.updated_success'),
            ];

            return $output;
        }
    }

    /**
     * Display contact locations on map
     */
    public function contactMap()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $query = Contact::where('business_id', $business_id)
                        ->active()
                        ->whereNotNull('position');

        if (! empty(request()->input('contacts'))) {
            $query->whereIn('id', request()->input('contacts'));
        }
        $contacts = $query->get();

        $all_contacts = Contact::where('business_id', $business_id)
                        ->active()
                        ->get();

        return view('contact.contact_map')
             ->with(compact('contacts', 'all_contacts'));
    }

    public function getContactPayments($contact_id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (request()->ajax()) {
            $payments = TransactionPayment::leftjoin('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->leftjoin('transaction_payments as parent_payment', 'transaction_payments.parent_id', '=', 'parent_payment.id')
            ->where('transaction_payments.business_id', $business_id)
            ->whereNull('transaction_payments.parent_id')
            ->with(['child_payments', 'child_payments.transaction'])
            ->where('transaction_payments.payment_for', $contact_id)
                ->select(
                    'transaction_payments.id',
                    'transaction_payments.amount',
                    'transaction_payments.is_return',
                    'transaction_payments.method',
                    'transaction_payments.paid_on',
                    'transaction_payments.payment_ref_no',
                    'transaction_payments.parent_id',
                    'transaction_payments.transaction_no',
                    't.invoice_no',
                    't.ref_no',
                    't.type as transaction_type',
                    't.return_parent_id',
                    't.id as transaction_id',
                    'transaction_payments.cheque_number',
                    'transaction_payments.card_transaction_number',
                    'transaction_payments.bank_account_number',
                    'transaction_payments.id as DT_RowId',
                    'parent_payment.payment_ref_no as parent_payment_ref_no'
                )
                ->groupBy('transaction_payments.id')
                ->orderByDesc('transaction_payments.paid_on')
                ->paginate();

            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

            return view('contact.partials.contact_payments_tab')
                    ->with(compact('payments', 'payment_types'));
        }
    }

    public function getContactDue($contact_id)
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $due = $this->transactionUtil->getContactDue($contact_id, $business_id);

            $output = $due != 0 ? $this->transactionUtil->num_f($due, true) : '';

            return $output;
        }
    }

    public function checkMobile(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        $mobile_number = $request->input('mobile_number');

        $query = Contact::where('business_id', $business_id)
                        ->where('mobile', 'like', "%{$mobile_number}");

        if (! empty($request->input('contact_id'))) {
            $query->where('id', '!=', $request->input('contact_id'));
        }

        $contacts = $query->pluck('name')->toArray();

        return [
            'is_mobile_exists' => ! empty($contacts),
            'msg' => __('lang_v1.mobile_already_registered', ['contacts' => implode(', ', $contacts), 'mobile' => $mobile_number]),
        ];
    }

     /**
     * Check if Tax Number already exists
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function checkTaxNumber(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $tax_number = $request->input('tax_number');


        $query = Contact::where('business_id', $business_id)
                        ->where('tax_number', $tax_number);

        if (! empty($request->input('contact_id'))) {
            $query->where('id', '!=', $request->input('contact_id'));
        }

        $contacts = $query->pluck('name')->toArray();

        return [
            'is_tax_number_exists' => ! empty($contacts),
            'msg' => ! empty($contacts) ? __('lang_v1.tax_number_already_registered', ['contacts' => implode(', ', $contacts), 'tax_number' => $tax_number]) : '',
        ];
    }
}
