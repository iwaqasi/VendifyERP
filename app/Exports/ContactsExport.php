<?php

namespace App\Exports;

use App\Contact;
use Maatwebsite\Excel\Concerns\FromArray;

class ContactsExport implements FromArray
{
    protected $type;
    protected $business_id;

    public function __construct($type = null, $business_id = null)
    {
        $this->type = $type;
        $this->business_id = $business_id ?: request()->session()->get('user.business_id');
    }

    public function array(): array
    {
        $query = Contact::where('business_id', $this->business_id);

        if ($this->type == 'supplier') {
            $query->whereIn('type', ['supplier', 'both']);
        } elseif ($this->type == 'customer') {
            $query->whereIn('type', ['customer', 'both']);
        }

        $contacts = $query->orderBy('name', 'asc')->get();

        $data = [
            [
                'Contact Type',
                'Contact ID',
                'Business Name',
                'Prefix',
                'First Name',
                'Middle Name',
                'Last Name',
                'Full Name',
                'Mobile',
                'Alternate Number',
                'Landline',
                'Email',
                'Tax Number',
                'Opening Balance',
                'Pay Term Number',
                'Pay Term Type',
                'Credit Limit',
                'Address Line 1',
                'Address Line 2',
                'City',
                'State',
                'Country',
                'Zip Code',
                'Status'
            ]
        ];

        foreach ($contacts as $c) {
            $data[] = [
                $c->type,
                $c->contact_id,
                $c->supplier_business_name ?? '',
                $c->prefix ?? '',
                $c->first_name ?? '',
                $c->middle_name ?? '',
                $c->last_name ?? '',
                $c->name ?? '',
                $c->mobile ?? '',
                $c->alternate_number ?? '',
                $c->landline ?? '',
                $c->email ?? '',
                $c->tax_number ?? '',
                $c->opening_balance ?? '',
                $c->pay_term_number ?? '',
                $c->pay_term_type ?? '',
                $c->credit_limit ?? '',
                $c->address_line_1 ?? '',
                $c->address_line_2 ?? '',
                $c->city ?? '',
                $c->state ?? '',
                $c->country ?? '',
                $c->zip_code ?? '',
                $c->contact_status ?? 'active',
            ];
        }

        return $data;
    }
}
