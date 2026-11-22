<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class CategoryTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['Category Name', 'Category Code', 'Description', 'Parent Category Name', 'Parent Category Code'],
            ['Electronics', 'ELEC', 'Electronic devices and equipment', '', ''],
            ['Cables & Adapters', 'ELEC-CAB', 'HDMI, USB and power cables', 'Electronics', 'ELEC'],
            ['Audio & Video', 'ELEC-AV', 'Speakers, microphones, and displays', 'Electronics', 'ELEC'],
            ['Hardware & Tools', 'HARD', 'Tools and hardware accessories', '', ''],
        ];
    }
}
