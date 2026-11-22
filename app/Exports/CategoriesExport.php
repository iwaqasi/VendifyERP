<?php

namespace App\Exports;

use App\Category;
use Maatwebsite\Excel\Concerns\FromArray;

class CategoriesExport implements FromArray
{
    protected $category_type;
    protected $business_id;

    public function __construct($category_type = 'product', $business_id = null)
    {
        $this->category_type = $category_type ?: 'product';
        $this->business_id = $business_id ?: request()->session()->get('user.business_id');
    }

    public function array(): array
    {
        $categories = Category::where('business_id', $this->business_id)
            ->where('category_type', $this->category_type)
            ->with('parent_category')
            ->orderBy('parent_id', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $data = [
            ['Category Name', 'Category Code', 'Description', 'Parent Category Name', 'Parent Category Code']
        ];

        foreach ($categories as $cat) {
            $parent_name = $cat->parent_category ? $cat->parent_category->name : '';
            $parent_code = $cat->parent_category ? $cat->parent_category->short_code : '';

            $data[] = [
                $cat->name,
                $cat->short_code ?? '',
                $cat->description ?? '',
                $parent_name,
                $parent_code,
            ];
        }

        return $data;
    }
}
