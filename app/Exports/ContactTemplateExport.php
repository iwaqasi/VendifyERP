<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ContactTemplateExport implements FromArray
{
    protected $type;

    public function __construct($type = 'customer')
    {
        $this->type = $type;
    }

    public function array(): array
    {
        $headers = [
            'Contact Type',
            'Contact ID',
            'Business Name',
            'Prefix',
            'First Name',
            'Middle Name',
            'Last Name',
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
        ];

        if ($this->type == 'supplier') {
            $sample1 = [
                'supplier',
                'SUP-001',
                'Global Wholesale Co',
                'Mr',
                'Ahmed',
                '',
                'Al-Salem',
                '96590000001',
                '96590000002',
                '22440001',
                'ahmed@globalwholesale.com',
                'TAX123456',
                '0.00',
                '30',
                'days',
                '',
                'Salem Al Mubarak St',
                'Building 4',
                'Kuwait City',
                'Capital',
                'Kuwait',
                '13000',
            ];
            $sample2 = [
                'supplier',
                'SUP-002',
                'Tech Importers Ltd',
                'Mr',
                'John',
                '',
                'Doe',
                '96590000003',
                '',
                '',
                'sales@techimporters.com',
                '',
                '250.00',
                '15',
                'days',
                '',
                'Industrial Area Block 2',
                '',
                'Shuwaikh',
                'Capital',
                'Kuwait',
                '70001',
            ];
        } else {
            $sample1 = [
                'customer',
                'CUST-001',
                'Al-Noor Trading',
                'Mr',
                'Tariq',
                '',
                'Al-Mutawa',
                '96598765432',
                '',
                '24881122',
                'tariq@alnoor.com',
                'TAX98765',
                '150.00',
                '',
                '',
                '1000',
                'Tunis Street',
                'Hawally Block 1',
                'Hawally',
                'Hawally',
                'Kuwait',
                '32001',
            ];
            $sample2 = [
                'customer',
                'CUST-002',
                '',
                'Mrs',
                'Fatima',
                '',
                'Al-Ali',
                '96566554433',
                '',
                '',
                'fatima@gmail.com',
                '',
                '0.00',
                '',
                '',
                '500',
                'Street 15',
                'House 22',
                'Salmiya',
                'Hawally',
                'Kuwait',
                '22001',
            ];
        }

        return [$headers, $sample1, $sample2];
    }
}
