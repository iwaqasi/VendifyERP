<?php

namespace App;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

class Category extends Model
{
    use SoftDeletes;

    protected $appends = ['image_url'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the category image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return asset('/uploads/img/' . rawurlencode($this->image));
        }
        return null;
    }

    /**
     * Upload category image.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return string  The stored filename
     */
    public static function uploadImage(UploadedFile $file): string
    {
        $filename = 'category_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('uploads/img'), $filename);
        return $filename;
    }

    /**
     * Delete category image file.
     */
    public function deleteImage(): void
    {
        if ($this->image) {
            $path = public_path('uploads/img/' . $this->image);
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Combines Category and sub-category
     *
     * @param  int  $business_id
     * @return array
     */
    public static function catAndSubCategories($business_id)
    {
        $all_categories = Category::where('business_id', $business_id)
                                ->where('category_type', 'product')
                                ->orderBy('name', 'asc')
                                ->get()
                                ->toArray();

        if (empty($all_categories)) {
            return [];
        }
        $categories = [];
        $sub_categories = [];

        foreach ($all_categories as $category) {
            if ($category['parent_id'] == 0) {
                $categories[] = $category;
            } else {
                $sub_categories[] = $category;
            }
        }

        $sub_cat_by_parent = [];
        if (! empty($sub_categories)) {
            foreach ($sub_categories as $sub_category) {
                if (empty($sub_cat_by_parent[$sub_category['parent_id']])) {
                    $sub_cat_by_parent[$sub_category['parent_id']] = [];
                }

                $sub_cat_by_parent[$sub_category['parent_id']][] = $sub_category;
            }
        }

        foreach ($categories as $key => $value) {
            if (! empty($sub_cat_by_parent[$value['id']])) {
                $categories[$key]['sub_categories'] = $sub_cat_by_parent[$value['id']];
            }
        }

        return $categories;
    }

    /**
     * Category Dropdown
     *
     * @param  int  $business_id
     * @param  string  $type category type
     * @return array
     */
    public static function forDropdown($business_id, $type)
    {
        $categories = Category::where('business_id', $business_id)
                            ->where('parent_id', 0)
                            ->where('category_type', $type)
                            ->select(DB::raw('IF(short_code IS NOT NULL, CONCAT(name, "-", short_code), name) as name'), 'id')
                            ->orderBy('name', 'asc')
                            ->get();

        $dropdown = $categories->pluck('name', 'id');

        return $dropdown;
    }

    public function sub_categories()
    {
        return $this->hasMany(\App\Category::class, 'parent_id');
    }

    /**
     * Scope a query to only include main categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyParent($query)
    {
        return $query->where('parent_id', 0);
    }
}
