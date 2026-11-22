<?php

namespace App\Http\Controllers;

use App\Category;
use App\Exports\CategoriesExport;
use App\Exports\CategoryTemplateExport;
use App\Utils\ModuleUtil;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class TaxonomyController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $category_type = request()->get('type');
        if ($category_type == 'product' && ! auth()->user()->can('category.view') && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $can_edit = true;
            if ($category_type == 'product' && ! auth()->user()->can('category.update')) {
                $can_edit = false;
            }

            $can_delete = true;
            if ($category_type == 'product' && ! auth()->user()->can('category.delete')) {
                $can_delete = false;
            }

            $business_id = request()->session()->get('user.business_id');

            $all_categories = Category::where('business_id', $business_id)
                ->where('category_type', $category_type)
                ->get()
                ->keyBy('id');

            $grouped = $all_categories->groupBy('parent_id');

            $category = collect();

            // Get parents (those with parent_id = null or 0)
            $parents = $grouped[null] ?? $grouped[0] ?? [];

            foreach ($parents as $parent) {
                // Push parent
                $category->push($parent);

                // Get and push children with prefixed parent name
                foreach ($grouped[$parent->id] ?? [] as $child) {
                    // Attach parent name for use in editColumn
                    $child->parent_name = $parent->name;
                    $category->push($child);
                }
            }

            return Datatables::of($category)
                ->addColumn('action', function ($row) use ($can_edit, $can_delete, $category_type) {
                    $html = '';
                    if ($can_edit) {
                        $html .= '<button data-href="' . action([\App\Http\Controllers\TaxonomyController::class, 'edit'], [$row->id]) . '?type=' . $category_type . '" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_category_button"><i class="glyphicon glyphicon-edit"></i> ' . __('messages.edit') . '</button>';
                    }

                    if ($can_delete) {
                        $html .= '&nbsp;<button data-href="' . action([\App\Http\Controllers\TaxonomyController::class, 'destroy'], [$row->id]) . '" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_category_button"><i class="glyphicon glyphicon-trash"></i> ' . __('messages.delete') . '</button>';
                    }

                    return $html;
                })
                ->addColumn('image', function ($row) {
                    if ($row->image) {
                        return $row->image_url;
                    }
                    return '';
                })
                ->editColumn('name', function ($row) {
                    // If parent_name is set (means it's a child)
                    if (!empty($row->parent_name)) {
                        return $row->parent_name . ' -> ' . $row->name;
                    }
                    return $row->name;
                })
                ->removeColumn('id')
                ->removeColumn('parent_id')
                ->rawColumns(['action', 'image'])
                ->make(true);

            }

        $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

        return view('taxonomy.index')->with(compact('module_category_data', 'module_category_data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $category_type = request()->get('type');
        if ($category_type == 'product' && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');

        $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

        $categories = Category::where('business_id', $business_id)
                        ->where('parent_id', 0)
                        ->where('category_type', $category_type)
                        ->select(['name', 'short_code', 'id'])
                        ->get();

        $parent_categories = [];
        if (! empty($categories)) {
            foreach ($categories as $category) {
                $parent_categories[$category->id] = $category->name;
            }
        }

        $quick_add = request()->get('quick_add');

        return view('taxonomy.create')
                    ->with(compact('parent_categories', 'module_category_data', 'category_type', 'quick_add'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $category_type = request()->input('category_type');
        if ($category_type == 'product' && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'short_code', 'category_type', 'description']);
            if (! empty($request->input('add_as_sub_cat')) && $request->input('add_as_sub_cat') == 1 && ! empty($request->input('parent_id'))) {
                $input['parent_id'] = $request->input('parent_id');
            } else {
                $input['parent_id'] = 0;
            }
            $input['business_id'] = $request->session()->get('user.business_id');
            $input['created_by'] = $request->session()->get('user.id');

            // Handle image upload
            if ($request->hasFile('image')) {
                $input['image'] = Category::uploadImage($request->file('image'));
            }

            $category = Category::create($input);
            $output = ['success' => true,
                'data' => $category,
                'msg' => __('category.added_success'),
            ];
        } catch (\Exception $e) {
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
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $category_type = request()->get('type');
        if ($category_type == 'product' && ! auth()->user()->can('category.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $category = Category::where('business_id', $business_id)->find($id);

            $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

            $parent_categories = Category::where('business_id', $business_id)
                                        ->where('parent_id', 0)
                                        ->where('category_type', $category_type)
                                        ->where('id', '!=', $id)
                                        ->pluck('name', 'id');
            $is_parent = false;

            if ($category->parent_id == 0) {
                $is_parent = true;
                $selected_parent = null;
            } else {
                $selected_parent = $category->parent_id;
            }

            return view('taxonomy.edit')
                ->with(compact('category', 'parent_categories', 'is_parent', 'selected_parent', 'module_category_data'));
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
        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_id = $request->session()->get('user.business_id');

                $category = Category::where('business_id', $business_id)->findOrFail($id);

                if ($category->category_type == 'product' && ! auth()->user()->can('category.update')) {
                    abort(403, 'Unauthorized action.');
                }

                $category->name = $input['name'];
                $category->description = $input['description'];
                $category->short_code = $request->input('short_code');

                if (! empty($request->input('add_as_sub_cat')) && $request->input('add_as_sub_cat') == 1 && ! empty($request->input('parent_id'))) {
                    $category->parent_id = $request->input('parent_id');
                } else {
                    $category->parent_id = 0;
                }

                // Handle image upload
                if ($request->hasFile('image')) {
                    $category->deleteImage();
                    $category->image = Category::uploadImage($request->file('image'));
                }

                $category->save();

                $output = ['success' => true,
                    'msg' => __('category.updated_success'),
                ];
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
        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $category = Category::where('business_id', $business_id)->findOrFail($id);

                if ($category->category_type == 'product' && ! auth()->user()->can('category.delete')) {
                    abort(403, 'Unauthorized action.');
                }

                $category->delete();

                $output = ['success' => true,
                    'msg' => __('category.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function getCategoriesApi()
    {
        try {
            $api_token = request()->header('API-TOKEN');

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $categories = Category::catAndSubCategories($api_settings->business_id);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($categories);
    }

    /**
     * get taxonomy index page
     * through ajax
     *
     * @return \Illuminate\Http\Response
     */
    public function getTaxonomyIndexPage(Request $request)
    {
        if (request()->ajax()) {
            $category_type = $request->get('category_type');
            $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

            return view('taxonomy.ajax_index')
                ->with(compact('module_category_data', 'category_type'));
        }
    }

    /**
     * Download Excel of Categories
     */
    public function downloadExcel(Request $request)
    {
        $category_type = $request->get('type', 'product');
        if ($category_type == 'product' && ! auth()->user()->can('category.view') && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $filename = 'categories-' . $category_type . '-' . date('Y-m-d') . '.xlsx';

        return Excel::download(new CategoriesExport($category_type, $business_id), $filename);
    }

    /**
     * Download Categories Import Template
     */
    public function downloadTemplate(Request $request)
    {
        $filename = 'categories_import_template.xlsx';
        return Excel::download(new CategoryTemplateExport, $filename);
    }

    /**
     * Show category import page
     */
    public function getImportTaxonomies(Request $request)
    {
        $category_type = $request->get('type', 'product');
        if ($category_type == 'product' && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }

        $module_category_data = $this->moduleUtil->getTaxonomyData($category_type);

        return view('taxonomy.import')->with(compact('module_category_data', 'category_type'));
    }

    /**
     * Process category import
     */
    public function postImportTaxonomies(Request $request)
    {
        $category_type = $request->get('type', 'product');
        if ($category_type == 'product' && ! auth()->user()->can('category.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            ini_set('max_execution_time', 0);

            if ($request->hasFile('taxonomy_csv')) {
                $file = $request->file('taxonomy_csv');
                $parsed_array = Excel::toArray([], $file);

                if (empty($parsed_array) || empty($parsed_array[0])) {
                    throw new \Exception(__('lang_v1.file_empty'));
                }

                $sheet = $parsed_array[0];
                $header = array_shift($sheet);

                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');

                // Map column indices (case-insensitive detection)
                $name_idx = 0;
                $code_idx = 1;
                $desc_idx = 2;
                $parent_name_idx = 3;
                $parent_code_idx = 4;

                if (!empty($header)) {
                    foreach ($header as $idx => $colName) {
                        $col = strtolower(trim((string)$colName));
                        if (str_contains($col, 'parent') && (str_contains($col, 'code') || str_contains($col, 'sku'))) {
                            $parent_code_idx = $idx;
                        } elseif (str_contains($col, 'parent')) {
                            $parent_name_idx = $idx;
                        } elseif (str_contains($col, 'code') || str_contains($col, 'short')) {
                            $code_idx = $idx;
                        } elseif (str_contains($col, 'desc')) {
                            $desc_idx = $idx;
                        } elseif (str_contains($col, 'name') || str_contains($col, 'category')) {
                            $name_idx = $idx;
                        }
                    }
                }

                $imported_count = 0;
                $updated_count = 0;

                DB::beginTransaction();

                // Pass 1: Import main categories (rows with empty parent)
                $sub_category_rows = [];
                foreach ($sheet as $row) {
                    $name = trim((string)($row[$name_idx] ?? ''));
                    if (empty($name)) {
                        continue;
                    }

                    $code = trim((string)($row[$code_idx] ?? ''));
                    $desc = trim((string)($row[$desc_idx] ?? ''));
                    $parent_name = trim((string)($row[$parent_name_idx] ?? ''));
                    $parent_code = trim((string)($row[$parent_code_idx] ?? ''));

                    if (!empty($parent_name) || !empty($parent_code)) {
                        $sub_category_rows[] = [
                            'name' => $name,
                            'code' => $code,
                            'desc' => $desc,
                            'parent_name' => $parent_name,
                            'parent_code' => $parent_code,
                        ];
                        continue;
                    }

                    // Check if existing main category exists
                    $existing = Category::where('business_id', $business_id)
                        ->where('category_type', $category_type)
                        ->where('parent_id', 0)
                        ->where(function ($q) use ($name, $code) {
                            $q->where('name', $name);
                            if (!empty($code)) {
                                $q->orWhere('short_code', $code);
                            }
                        })
                        ->first();

                    if ($existing) {
                        if (!empty($code)) $existing->short_code = $code;
                        if (!empty($desc)) $existing->description = $desc;
                        $existing->save();
                        $updated_count++;
                    } else {
                        Category::create([
                            'name' => $name,
                            'business_id' => $business_id,
                            'short_code' => $code ?: null,
                            'parent_id' => 0,
                            'category_type' => $category_type,
                            'description' => $desc ?: null,
                            'created_by' => $user_id,
                        ]);
                        $imported_count++;
                    }
                }

                // Pass 2: Import sub-categories (linking to parents)
                foreach ($sub_category_rows as $sub) {
                    $name = $sub['name'];
                    $code = $sub['code'];
                    $desc = $sub['desc'];
                    $p_name = $sub['parent_name'];
                    $p_code = $sub['parent_code'];

                    // Find parent
                    $parent = Category::where('business_id', $business_id)
                        ->where('category_type', $category_type)
                        ->where('parent_id', 0)
                        ->where(function ($q) use ($p_name, $p_code) {
                            if (!empty($p_name)) $q->where('name', $p_name);
                            if (!empty($p_code)) $q->orWhere('short_code', $p_code);
                        })
                        ->first();

                    $parent_id = $parent ? $parent->id : 0;

                    $existingSub = Category::where('business_id', $business_id)
                        ->where('category_type', $category_type)
                        ->where('parent_id', $parent_id)
                        ->where(function ($q) use ($name, $code) {
                            $q->where('name', $name);
                            if (!empty($code)) {
                                $q->orWhere('short_code', $code);
                            }
                        })
                        ->first();

                    if ($existingSub) {
                        if (!empty($code)) $existingSub->short_code = $code;
                        if (!empty($desc)) $existingSub->description = $desc;
                        $existingSub->save();
                        $updated_count++;
                    } else {
                        Category::create([
                            'name' => $name,
                            'business_id' => $business_id,
                            'short_code' => $code ?: null,
                            'parent_id' => $parent_id,
                            'category_type' => $category_type,
                            'description' => $desc ?: null,
                            'created_by' => $user_id,
                        ]);
                        $imported_count++;
                    }
                }

                DB::commit();

                $output = [
                    'success' => 1,
                    'msg' => "Import successful! Created: $imported_count, Updated: $updated_count.",
                ];

                return redirect()->action([\App\Http\Controllers\TaxonomyController::class, 'index'], ['type' => $category_type])
                    ->with('status', $output);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect()->back()->with('notification', $output);
        }
    }
}
