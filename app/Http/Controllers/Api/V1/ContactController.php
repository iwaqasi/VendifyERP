<?php

namespace App\Http\Controllers\Api\V1;

use App\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends BaseApiController
{
    /**
     * List contacts (customers/suppliers) with search and pagination
     *
     * @queryParam search string Search by name, phone, email, contact_id. Example: john
     * @queryParam type string Filter: customer, supplier, both. Example: customer
     * @queryParam per_page int Items per page. Example: 20
     */
    public function index(Request $request)
    {
        $business_id = $this->getBusinessId($request);
        $per_page = $request->input('per_page', 20);

        $query = Contact::where('business_id', $business_id);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('contact_id', 'LIKE', "%{$search}%")
                  ->orWhere('mobile', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('business_name', 'LIKE', "%{$search}%");
            });
        }

        // Filter by type
        if ($type = $request->input('type')) {
            if ($type === 'customer') {
                $query->onlyCustomers();
            } elseif ($type === 'supplier') {
                $query->onlySuppliers();
            }
        }

        $contacts = $query->orderBy('name', 'asc')
            ->paginate($per_page);

        // Transform contacts for API response
        $transformed = $contacts->getCollection()->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'contact_id' => $contact->contact_id,
                'type' => $contact->type,
                'mobile' => $contact->mobile,
                'email' => $contact->email,
                'tax_number' => $contact->tax_number,
                'balance' => (float) ($contact->balance ?? 0),
                'sell_due' => 0,
                'purchase_due' => 0,
                'customer_group_id' => $contact->customer_group_id,
                'credit_limit' => (float) ($contact->credit_limit ?? 0),
                'total_rp' => (int) ($contact->total_rp ?? 0),
            ];
        });

        $contacts->setCollection($transformed);

        return $this->paginatedResponse($contacts);
    }

    /**
     * Get single contact with due amounts
     */
    public function show(Request $request, int $id)
    {
        $business_id = $this->getBusinessId($request);

        $contact = Contact::where('business_id', $business_id)->find($id);

        if (!$contact) {
            return $this->errorResponse('Contact not found.', 404);
        }

        // Calculate due amounts: final_total - sum(payments)
        $sell_due = \App\Transaction::where('contact_id', $id)
            ->where('type', 'sell')
            ->whereIn('payment_status', ['due', 'partial'])
            ->get()
            ->sum(function ($t) {
                $paid = $t->payment_lines->sum('amount');
                return $t->final_total - $paid;
            });

        $purchase_due = \App\Transaction::where('contact_id', $id)
            ->where('type', 'purchase')
            ->whereIn('payment_status', ['due', 'partial'])
            ->get()
            ->sum(function ($t) {
                $paid = $t->payment_lines->sum('amount');
                return $t->final_total - $paid;
            });

        $data = [
            'id' => $contact->id,
            'name' => $contact->name,
            'business_name' => $contact->business_name,
            'contact_id' => $contact->contact_id,
            'type' => $contact->type,
            'mobile' => $contact->mobile,
            'email' => $contact->email,
            'tax_number' => $contact->tax_number,
            'shipping_address' => $contact->shipping_address,
            'billing_address' => $contact->billing_address,
            'customer_group_id' => $contact->customer_group_id,
            'credit_limit' => $contact->credit_limit,
            'sell_due' => (float) $sell_due,
            'purchase_due' => (float) $purchase_due,
            'total_balance' => (float) ($contact->balance ?? 0),
            'total_rp' => (int) ($contact->total_rp ?? 0),
            'total_rp_used' => (int) ($contact->total_rp_used ?? 0),
            'custom_field_1' => $contact->custom_field_1,
            'custom_field_2' => $contact->custom_field_2,
            'custom_field_3' => $contact->custom_field_3,
            'custom_field_4' => $contact->custom_field_4,
        ];

        return $this->successResponse($data);
    }

    /**
     * Get reward points details for a customer
     *
     * @param int $id Contact ID
     */
    public function rewardPoints(Request $request, int $id)
    {
        $business_id = $this->getBusinessId($request);
        $contact = Contact::where('business_id', $business_id)->find($id);

        if (!$contact) {
            return $this->errorResponse('Contact not found.', 404);
        }

        $business = \App\Business::find($business_id);
        $rpEnabled = !empty($business->enable_rp);

        // Calculate equivalent redeemable amount
        $availablePoints = (int) ($contact->total_rp ?? 0);
        $usedPoints = (int) ($contact->total_rp_used ?? 0);
        $netPoints = max(0, $availablePoints - $usedPoints);

        $redeemAmountPerUnit = (float) ($business->redeem_amount_per_unit_rp ?? 1);
        $equivalentAmount = $netPoints * $redeemAmountPerUnit;

        return $this->successResponse([
            'enabled' => $rpEnabled,
            'rp_name' => $business->rp_name ?? 'Reward Points',
            'total_earned' => $availablePoints,
            'total_used' => $usedPoints,
            'available_points' => $netPoints,
            'equivalent_amount' => $equivalentAmount,
            'redeem_rate' => $redeemAmountPerUnit,
            'min_redeem_point' => (int) ($business->min_redeem_point ?? 0),
            'max_redeem_point' => $business->max_redeem_point ? (int) $business->max_redeem_point : null,
        ]);
    }

    /**
     * Create a new contact (customer)
     *
     * @bodyParam name string required Contact name. Example: John Smith
     * @bodyParam mobile string Phone number. Example: +1234567890
     * @bodyParam email string Email address. Example: john@example.com
     * @bodyParam type string Type: customer, supplier, both. Example: customer
     */
    public function store(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:customer,supplier,both',
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'customer_group_id' => 'nullable|integer',
            'credit_limit' => 'nullable|numeric|min:0',
            'pay_term_number' => 'nullable|integer|min:0',
            'pay_term_type' => 'nullable|in:days,months',
        ]);

        // Check for duplicate mobile number
        if (!empty($request->mobile)) {
            $existing = Contact::where('business_id', $business_id)
                ->where('mobile', $request->mobile)
                ->exists();

            if ($existing) {
                return $this->errorResponse('A customer with this phone number already exists.', 422);
            }
        }

        // Generate contact_id (CO0001, CO0002, etc.)
        $contact_count = \App\Contact::where('business_id', $business_id)->count();
        $contact_id_counter = 'CO' . str_pad($contact_count + 1, 4, '0', STR_PAD_LEFT);

        // Split name into parts for the Laravel form
        $nameParts = explode(' ', trim($request->name), 2);
        $first_name = $nameParts[0] ?? '';
        $last_name = $nameParts[1] ?? '';

        $contact = Contact::create([
            'business_id' => $business_id,
            'name' => $request->name,
            'contact_id' => $contact_id_counter,
            'type' => $request->type,
            'contact_type' => 'individual',
            'first_name' => $first_name,
            'last_name' => $last_name,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'tax_number' => $request->tax_number,
            'shipping_address' => $request->shipping_address,
            'billing_address' => $request->billing_address,
            'customer_group_id' => $request->customer_group_id,
            'credit_limit' => $request->credit_limit ?? 0,
            'pay_term_number' => $request->pay_term_number ?? 0,
            'pay_term_type' => $request->pay_term_type ?? 'days',
            'is_active' => 1,
            'created_by' => $request->user()->id,
        ]);

        return $this->successResponse([
            'id' => $contact->id,
            'name' => $contact->name,
            'contact_id' => $contact->contact_id,
            'type' => $contact->type,
            'mobile' => $contact->mobile,
            'email' => $contact->email,
            'tax_number' => $contact->tax_number,
            'balance' => 0,
            'sell_due' => 0,
            'purchase_due' => 0,
            'customer_group_id' => $contact->customer_group_id,
            'credit_limit' => (float) ($contact->credit_limit ?? 0),
        ], 'Contact created successfully', 201);
    }

    /**
     * Update a contact
     */
    public function update(Request $request, int $id)
    {
        $business_id = $this->getBusinessId($request);

        $contact = Contact::where('business_id', $business_id)->find($id);

        if (!$contact) {
            return $this->errorResponse('Contact not found.', 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'customer_group_id' => 'nullable|integer',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $contact->update($request->only([
            'name', 'mobile', 'email', 'tax_number',
            'shipping_address', 'billing_address',
            'customer_group_id', 'credit_limit',
        ]));

        return $this->successResponse($contact, 'Contact updated successfully');
    }

    /**
     * List customer groups
     */
    public function customerGroups(Request $request)
    {
        $business_id = $this->getBusinessId($request);

        $groups = \App\CustomerGroup::where('business_id', $business_id)
            ->get(['id', 'name']);

        return $this->successResponse($groups);
    }
}
