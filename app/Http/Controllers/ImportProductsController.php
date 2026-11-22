<?php

namespace App\Http\Controllers;

use App\Brands;
use App\BusinessLocation;
use App\Category;
use App\Product;
use App\TaxRate;
use App\Transaction;
use App\Unit;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Variation;
use App\VariationValueTemplate;
use DB;
use Excel;
use Illuminate\Http\Request;

class ImportProductsController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $moduleUtil;

    private $barcode_types;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;

        //barcode types
        $this->barcode_types = $this->productUtil->barcode_types();
    }

    /**
     * Display import product screen.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $zip_loaded = extension_loaded('zip') ? true : false;

        //Check if zip extension it loaded or not.
        if ($zip_loaded === false) {
            $output = ['success' => 0,
                'msg' => 'Please install/enable PHP Zip archive for import',
            ];

            return view('import_products.index')
                ->with('notification', $output);
        } else {
            return view('import_products.index');
        }
    }

    /**
     * Download Excel template for product import with Arabic fields
     */
    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\ProductTemplateExport, 'import_products_template.xlsx');
    }

    /**
     * Imports the uploaded file to database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->productUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            //Set maximum php execution time
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            if ($request->hasFile('products_csv')) {
                $file = $request->file('products_csv');

                $parsed_array = Excel::toArray([], $file);

                if (empty($parsed_array) || empty($parsed_array[0])) {
                    throw new \Exception(__('messages.something_went_wrong'));
                }

                $raw_rows = $parsed_array[0];
                $header_row = $raw_rows[0] ?? [];
                //Remove header row
                $imported_data = array_splice($raw_rows, 1);

                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');
                $default_profit_percent = $request->session()->get('business.default_profit_percent');

                $formated_data = [];

                $is_valid = true;
                $error_msg = '';

                $total_rows = count($imported_data);

                //Check if subscribed or not, then check for products quota
                if (! $this->moduleUtil->isSubscribed($business_id)) {
                    return $this->moduleUtil->expiredResponse();
                } elseif (! $this->moduleUtil->isQuotaAvailable('products', $business_id, $total_rows)) {
                    return $this->moduleUtil->quotaExpiredResponse('products', $business_id, action([\App\Http\Controllers\ImportProductsController::class, 'index']));
                }

                // Determine layout: modern 41/40 col or legacy 37 col
                $is_modern = (count($header_row) >= 40);
                if (! $is_modern && isset($header_row[1])) {
                    $second_col = strtolower(trim((string)$header_row[1]));
                    if (strpos($second_col, 'ar') !== false || strpos($second_col, 'arabic') !== false) {
                        $is_modern = true;
                    }
                }

                if ($is_modern) {
                    $col_map = [
                        'name' => 0,
                        'name_ar' => 1,
                        'brand' => 2,
                        'unit' => 3,
                        'category' => 4,
                        'sub_category' => 5,
                        'sku' => 6,
                        'barcode' => 7,
                        'barcode_type' => 8,
                        'enable_stock' => 9,
                        'alert_quantity' => 10,
                        'expiry_period' => 11,
                        'expiry_period_type' => 12,
                        'tax' => 13,
                        'tax_type' => 14,
                        'type' => 15,
                        'variation_name' => 16,
                        'variation_values' => 17,
                        'variation_skus' => 18,
                        'dpp_inc_tax' => 19,
                        'dpp_exc_tax' => 20,
                        'profit_margin' => 21,
                        'selling_price' => 22,
                        'opening_stock' => 23,
                        'opening_stock_location' => 24,
                        'expiry_date' => 25,
                        'enable_sr_no' => 26,
                        'weight' => 27,
                        'rack' => 28,
                        'row' => 29,
                        'position' => 30,
                        'image' => 31,
                        'product_description' => 32,
                        'description_ar' => 33,
                        'product_custom_field1' => 34,
                        'product_custom_field2' => 35,
                        'product_custom_field3' => 36,
                        'product_custom_field4' => 37,
                        'not_for_selling' => 38,
                        'product_locations' => 39,
                        'is_flexible_price' => 40,
                    ];
                } else {
                    $col_map = [
                        'name' => 0,
                        'name_ar' => null,
                        'brand' => 1,
                        'unit' => 2,
                        'category' => 3,
                        'sub_category' => 4,
                        'sku' => 5,
                        'barcode' => null,
                        'barcode_type' => 6,
                        'enable_stock' => 7,
                        'alert_quantity' => 8,
                        'expiry_period' => 9,
                        'expiry_period_type' => 10,
                        'tax' => 11,
                        'tax_type' => 12,
                        'type' => 13,
                        'variation_name' => 14,
                        'variation_values' => 15,
                        'variation_skus' => 16,
                        'dpp_inc_tax' => 17,
                        'dpp_exc_tax' => 18,
                        'profit_margin' => 19,
                        'selling_price' => 20,
                        'opening_stock' => 21,
                        'opening_stock_location' => 22,
                        'expiry_date' => 23,
                        'enable_sr_no' => 24,
                        'weight' => 25,
                        'rack' => 26,
                        'row' => 27,
                        'position' => 28,
                        'image' => 29,
                        'product_description' => 30,
                        'description_ar' => null,
                        'product_custom_field1' => 31,
                        'product_custom_field2' => 32,
                        'product_custom_field3' => 33,
                        'product_custom_field4' => 34,
                        'not_for_selling' => 35,
                        'product_locations' => 36,
                        'is_flexible_price' => null,
                    ];
                }

                // Dynamic header matching override
                foreach ($header_row as $col_idx => $header_raw) {
                    $h = strtolower(trim((string)$header_raw));
                    if ($h === '') continue;

                    if (strpos($h, 'name ar') !== false || strpos($h, 'arabic name') !== false || $h === 'name_ar' || $h === 'اسم المنتج بالعربي' || $h === 'الاسم بالعربي') {
                        $col_map['name_ar'] = $col_idx;
                    } elseif (strpos($h, 'description ar') !== false || strpos($h, 'arabic description') !== false || $h === 'description_ar' || $h === 'الوصف بالعربي' || $h === 'وصف المنتج بالعربي') {
                        $col_map['description_ar'] = $col_idx;
                    } elseif ($h === 'barcode' || $h === 'الباركود') {
                        $col_map['barcode'] = $col_idx;
                    } elseif (strpos($h, 'barcode type') !== false || $h === 'barcode_type') {
                        $col_map['barcode_type'] = $col_idx;
                    } elseif (strpos($h, 'variation name') !== false) {
                        $col_map['variation_name'] = $col_idx;
                    } elseif (strpos($h, 'variation values') !== false) {
                        $col_map['variation_values'] = $col_idx;
                    } elseif (strpos($h, 'variation sku') !== false) {
                        $col_map['variation_skus'] = $col_idx;
                    } elseif (strpos($h, 'sub-category') !== false || strpos($h, 'sub category') !== false || strpos($h, 'subcategory') !== false) {
                        $col_map['sub_category'] = $col_idx;
                    } elseif (strpos($h, 'category') !== false) {
                        $col_map['category'] = $col_idx;
                    } elseif (strpos($h, 'brand') !== false) {
                        $col_map['brand'] = $col_idx;
                    } elseif (strpos($h, 'expiry period unit') !== false) {
                        $col_map['expiry_period_type'] = $col_idx;
                    } elseif (strpos($h, 'expires in') !== false || strpos($h, 'expiry period') !== false) {
                        $col_map['expiry_period'] = $col_idx;
                    } elseif ($h === 'unit' || strpos($h, 'unit name') !== false) {
                        $col_map['unit'] = $col_idx;
                    } elseif (strpos($h, 'sku') === 0 || $h === 'sku') {
                        $col_map['sku'] = $col_idx;
                    } elseif (strpos($h, 'manage stock') !== false || strpos($h, 'enable stock') !== false) {
                        $col_map['enable_stock'] = $col_idx;
                    } elseif (strpos($h, 'alert quantity') !== false) {
                        $col_map['alert_quantity'] = $col_idx;
                    } elseif (strpos($h, 'applicable tax') !== false) {
                        $col_map['tax'] = $col_idx;
                    } elseif (strpos($h, 'selling price tax type') !== false || $h === 'tax type') {
                        $col_map['tax_type'] = $col_idx;
                    } elseif (strpos($h, 'product type') !== false || $h === 'type') {
                        $col_map['type'] = $col_idx;
                    } elseif (strpos($h, 'purchase price') !== false && strpos($h, 'inc') !== false) {
                        $col_map['dpp_inc_tax'] = $col_idx;
                    } elseif (strpos($h, 'purchase price') !== false && (strpos($h, 'exc') !== false || strpos($h, 'ex') !== false)) {
                        $col_map['dpp_exc_tax'] = $col_idx;
                    } elseif (strpos($h, 'profit margin') !== false) {
                        $col_map['profit_margin'] = $col_idx;
                    } elseif (strpos($h, 'selling price') !== false && strpos($h, 'tax') === false) {
                        $col_map['selling_price'] = $col_idx;
                    } elseif (strpos($h, 'opening stock location') !== false) {
                        $col_map['opening_stock_location'] = $col_idx;
                    } elseif (strpos($h, 'opening stock') !== false) {
                        $col_map['opening_stock'] = $col_idx;
                    } elseif (strpos($h, 'expiry date') !== false) {
                        $col_map['expiry_date'] = $col_idx;
                    } elseif (strpos($h, 'enable imei') !== false || strpos($h, 'serial number') !== false) {
                        $col_map['enable_sr_no'] = $col_idx;
                    } elseif (strpos($h, 'weight') !== false) {
                        $col_map['weight'] = $col_idx;
                    } elseif (strpos($h, 'rack') !== false) {
                        $col_map['rack'] = $col_idx;
                    } elseif (strpos($h, 'row') !== false) {
                        $col_map['row'] = $col_idx;
                    } elseif (strpos($h, 'position') !== false) {
                        $col_map['position'] = $col_idx;
                    } elseif ($h === 'image') {
                        $col_map['image'] = $col_idx;
                    } elseif (strpos($h, 'description') !== false && strpos($h, 'ar') === false) {
                        $col_map['product_description'] = $col_idx;
                    } elseif (strpos($h, 'custom field 1') !== false || $h === 'custom_field_1') {
                        $col_map['product_custom_field1'] = $col_idx;
                    } elseif (strpos($h, 'custom field 2') !== false || $h === 'custom_field_2') {
                        $col_map['product_custom_field2'] = $col_idx;
                    } elseif (strpos($h, 'custom field 3') !== false || $h === 'custom_field_3') {
                        $col_map['product_custom_field3'] = $col_idx;
                    } elseif (strpos($h, 'custom field 4') !== false || $h === 'custom_field_4') {
                        $col_map['product_custom_field4'] = $col_idx;
                    } elseif (strpos($h, 'not for selling') !== false) {
                        $col_map['not_for_selling'] = $col_idx;
                    } elseif (strpos($h, 'location') !== false) {
                        $col_map['product_locations'] = $col_idx;
                    } elseif (strpos($h, 'flexible price') !== false) {
                        $col_map['is_flexible_price'] = $col_idx;
                    } elseif ($h === 'name' || strpos($h, 'product name') !== false) {
                        $col_map['name'] = $col_idx;
                    }
                }

                $getVal = function ($row, $col_key) use ($col_map) {
                    if (!isset($col_map[$col_key]) || $col_map[$col_key] === null) {
                        return null;
                    }
                    $idx = $col_map[$col_key];
                    return isset($row[$idx]) ? trim((string) $row[$idx]) : null;
                };

                $business_locations = BusinessLocation::where('business_id', $business_id)->get();
                DB::beginTransaction();
                foreach ($imported_data as $key => $value) {

                    //Check if row is essentially empty or missing core columns
                    if (count($value) < 15 || empty(array_filter($value, fn($v) => $v !== null && $v !== ''))) {
                        continue;
                    }

                    $row_no = $key + 1;
                    $product_array = [];
                    $product_array['business_id'] = $business_id;
                    $product_array['created_by'] = $user_id;

                    //Add name
                    $product_name = $getVal($value, 'name');
                    if (! empty($product_name)) {
                        $product_array['name'] = $product_name;
                    } else {
                        $is_valid = false;
                        $error_msg = "Product name is required in row no. $row_no";
                        break;
                    }

                    //Add name_ar (Arabic product name)
                    $name_ar = $getVal($value, 'name_ar');
                    if (! empty($name_ar)) {
                        $product_array['name_ar'] = $name_ar;
                    }

                    //Add barcode
                    $barcode = $getVal($value, 'barcode');
                    if (! empty($barcode)) {
                        $product_array['barcode'] = $barcode;
                    }

                    //image name
                    $image_name = $getVal($value, 'image') ?? '';
                    if (! empty($image_name)) {
                        if (filter_var($image_name, FILTER_VALIDATE_URL)) {
                            $source_image = file_get_contents($image_name);

                            $path = parse_url($image_name, PHP_URL_PATH);
                            $new_name = time().'_'.basename($path);
                            // Per-business folder: uploads/img/{business_id}/{filename}
                            $bizImgDir = public_path().'/uploads/'.config('constants.product_img_path').'/'.$business_id;
                            if (!is_dir($bizImgDir)) {
                                mkdir($bizImgDir, 0755, true);
                            }
                            $dest_img = $bizImgDir.'/'.$new_name;
                            file_put_contents($dest_img, $source_image);
                            $product_array['image'] = $new_name;
                        } else {
                            $product_array['image'] = $image_name;
                        }
                    } else {
                        $product_array['image'] = '';
                    }

                    $product_array['product_description'] = $getVal($value, 'product_description');
                    $desc_ar = $getVal($value, 'description_ar');
                    if (! empty($desc_ar)) {
                        $product_array['description_ar'] = $desc_ar;
                    }

                    //Custom fields
                    $product_array['product_custom_field1'] = $getVal($value, 'product_custom_field1') ?? '';
                    $product_array['product_custom_field2'] = $getVal($value, 'product_custom_field2') ?? '';
                    $product_array['product_custom_field3'] = $getVal($value, 'product_custom_field3') ?? '';
                    $product_array['product_custom_field4'] = $getVal($value, 'product_custom_field4') ?? '';

                    //Add not for selling
                    $not_for_selling = $getVal($value, 'not_for_selling');
                    $product_array['not_for_selling'] = ! empty($not_for_selling) && $not_for_selling == 1 ? 1 : 0;

                    //Add flexible price
                    $flex_price = $getVal($value, 'is_flexible_price');
                    if ($flex_price !== null && in_array($flex_price, ['0', '1', 0, 1])) {
                        $product_array['is_flexible_price'] = (int) $flex_price;
                    }

                    //Add enable stock
                    $enable_stock = $getVal($value, 'enable_stock');
                    if (in_array($enable_stock, ['0', '1', 0, 1])) {
                        $product_array['enable_stock'] = (int) $enable_stock;
                    } else {
                        $is_valid = false;
                        $error_msg = "Invalid value for MANAGE STOCK in row no. $row_no";
                        break;
                    }

                    //Add product type
                    $product_type = strtolower($getVal($value, 'type') ?? '');
                    if (in_array($product_type, ['single', 'variable'])) {
                        $product_array['type'] = $product_type;
                    } elseif ($product_type == 'combo') {
                        continue;
                    } else {
                        $is_valid = false;
                        $error_msg = "Invalid value for PRODUCT TYPE in row no. $row_no";
                        break;
                    }

                    //Add unit
                    $unit_name = $getVal($value, 'unit');
                    if (! empty($unit_name)) {
                        $unit = Unit::where('business_id', $business_id)
                                    ->where(function ($query) use ($unit_name) {
                                        $query->where('short_name', $unit_name)
                                              ->orWhere('actual_name', $unit_name);
                                    })->first();
                        if (! empty($unit)) {
                            $product_array['unit_id'] = $unit->id;
                        } else {
                            $is_valid = false;
                            $error_msg = "Unit with name $unit_name not found in row no. $row_no. You can add unit from Products > Units";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "UNIT is required in row no. $row_no";
                        break;
                    }

                    //Add barcode type
                    $barcode_type = strtoupper($getVal($value, 'barcode_type') ?? '');
                    if (empty($barcode_type)) {
                        $product_array['barcode_type'] = 'C128';
                    } elseif (array_key_exists($barcode_type, $this->barcode_types)) {
                        $product_array['barcode_type'] = $barcode_type;
                    } else {
                        $is_valid = false;
                        $error_msg = "$barcode_type barcode type is not valid in row no. $row_no. Please, check for allowed barcode types in the instructions";
                        break;
                    }

                    //Add Tax
                    $tax_name = $getVal($value, 'tax');
                    $tax_amount = 0;
                    if (! empty($tax_name)) {
                        $tax = TaxRate::where('business_id', $business_id)
                                        ->where('name', $tax_name)
                                        ->first();
                        if (! empty($tax)) {
                            $product_array['tax'] = $tax->id;
                            $tax_amount = $tax->amount;
                        } else {
                            $is_valid = false;
                            $error_msg = "Tax with name $tax_name in row no. $row_no not found. You can add tax from Settings > Tax Rates";
                            break;
                        }
                    }

                    //Add tax type
                    $tax_type = strtolower($getVal($value, 'tax_type') ?? '');
                    if (in_array($tax_type, ['inclusive', 'exclusive'])) {
                        $product_array['tax_type'] = $tax_type;
                    } else {
                        $is_valid = false;
                        $error_msg = "Invalid value for Selling Price Tax Type in row no. $row_no";
                        break;
                    }

                    //Add alert quantity
                    if ($product_array['enable_stock'] == 1) {
                        $product_array['alert_quantity'] = $getVal($value, 'alert_quantity');
                    }

                    //Add brand
                    $brand_name = $getVal($value, 'brand');
                    if (! empty($brand_name)) {
                        $brand = Brands::firstOrCreate(
                            ['business_id' => $business_id, 'name' => $brand_name],
                            ['created_by' => $user_id]
                        );
                        $product_array['brand_id'] = $brand->id;
                    }

                    //Add Category
                    $category_name = $getVal($value, 'category');
                    if (! empty($category_name)) {
                        $category = Category::firstOrCreate(
                            ['business_id' => $business_id, 'name' => $category_name, 'category_type' => 'product'],
                            ['created_by' => $user_id, 'parent_id' => 0]
                        );
                        $product_array['category_id'] = $category->id;
                    }

                    //Add Sub-Category
                    $sub_category_name = $getVal($value, 'sub_category');
                    if (! empty($sub_category_name)) {
                        $sub_category = Category::firstOrCreate(
                            ['business_id' => $business_id, 'name' => $sub_category_name, 'category_type' => 'product'],
                            ['created_by' => $user_id, 'parent_id' => $category->id]
                        );
                        $product_array['sub_category_id'] = $sub_category->id;
                    }

                    //Add SKU
                    $sku = $getVal($value, 'sku');
                    if (! empty($sku)) {
                        $product_array['sku'] = $sku;
                        //Check if product with same SKU already exist
                        $is_exist = Product::where('sku', $product_array['sku'])
                                        ->where('business_id', $business_id)
                                        ->exists();
                        if ($is_exist) {
                            $is_valid = false;
                            $error_msg = "$sku SKU already exist in row no. $row_no";
                            break;
                        }
                    } else {
                        $product_array['sku'] = ' ';
                    }

                    //Add product expiry
                    $expiry_period = $getVal($value, 'expiry_period');
                    $expiry_period_type = strtolower($getVal($value, 'expiry_period_type') ?? '');
                    $expiry_date_val = $getVal($value, 'expiry_date');
                    if (! empty($expiry_period) && in_array($expiry_period_type, ['months', 'days'])) {
                        $product_array['expiry_period'] = $expiry_period;
                        $product_array['expiry_period_type'] = $expiry_period_type;
                    } else {
                        if (! empty($expiry_date_val)) {
                            $product_array['expiry_period'] = 12;
                            $product_array['expiry_period_type'] = 'months';
                        }
                    }

                    //Enable IMEI or Serial Number
                    $enable_sr_no = $getVal($value, 'enable_sr_no');
                    if (in_array($enable_sr_no, ['0', '1', 0, 1])) {
                        $product_array['enable_sr_no'] = (int) $enable_sr_no;
                    } elseif (empty($enable_sr_no)) {
                        $product_array['enable_sr_no'] = 0;
                    } else {
                        $is_valid = false;
                        $error_msg = "Invalid value for ENABLE IMEI OR SERIAL NUMBER in row no. $row_no";
                        break;
                    }

                    //Weight
                    $product_array['weight'] = $getVal($value, 'weight') ?? '';

                    if ($product_array['type'] == 'single') {
                        //Calculate profit margin
                        $profit_margin = $getVal($value, 'profit_margin');
                        if (empty($profit_margin)) {
                            $profit_margin = $default_profit_percent;
                        }
                        $product_array['variation']['profit_percent'] = $profit_margin;

                        //Calculate purchase price
                        $dpp_inc_tax = $getVal($value, 'dpp_inc_tax');
                        $dpp_exc_tax = $getVal($value, 'dpp_exc_tax');
                        if (($dpp_inc_tax === null || $dpp_inc_tax === '') && ($dpp_exc_tax === null || $dpp_exc_tax === '')) {
                            $is_valid = false;
                            $error_msg = "PURCHASE PRICE is required in row no. $row_no";
                            break;
                        } else {
                            $dpp_inc_tax = ($dpp_inc_tax != '') ? $dpp_inc_tax : 0;
                            $dpp_exc_tax = ($dpp_exc_tax != '') ? $dpp_exc_tax : 0;
                        }

                        //Calculate Selling price
                        $selling_price = ! empty($getVal($value, 'selling_price')) ? $getVal($value, 'selling_price') : 0;

                        //Calculate product prices
                        $product_prices = $this->calculateVariationPrices($dpp_exc_tax, $dpp_inc_tax, $selling_price, $tax_amount, $tax_type, $profit_margin);

                        //Assign Values
                        $product_array['variation']['dpp_inc_tax'] = $product_prices['dpp_inc_tax'];
                        $product_array['variation']['dpp_exc_tax'] = $product_prices['dpp_exc_tax'];
                        $product_array['variation']['dsp_inc_tax'] = $product_prices['dsp_inc_tax'];
                        $product_array['variation']['dsp_exc_tax'] = $product_prices['dsp_exc_tax'];

                        //Opening stock
                        $opening_stock_val = $getVal($value, 'opening_stock');
                        if (! empty($opening_stock_val) && $enable_stock == 1) {
                            $product_array['opening_stock_details']['quantity'] = $opening_stock_val;

                            $location_name = $getVal($value, 'opening_stock_location');
                            if (! empty($location_name)) {
                                $location = BusinessLocation::where('name', $location_name)
                                                            ->where('business_id', $business_id)
                                                            ->first();
                                if (! empty($location)) {
                                    $product_array['opening_stock_details']['location_id'] = $location->id;
                                } else {
                                    $is_valid = false;
                                    $error_msg = "No location with name '$location_name' found in row no. $row_no";
                                    break;
                                }
                            } else {
                                $location = BusinessLocation::where('business_id', $business_id)->first();
                                $product_array['opening_stock_details']['location_id'] = $location->id;
                            }

                            $product_array['opening_stock_details']['expiry_date'] = null;

                            //Stock expiry date
                            if (! empty($expiry_date_val)) {
                                $product_array['opening_stock_details']['exp_date'] = \Carbon::createFromFormat('m-d-Y', $expiry_date_val)->format('Y-m-d');
                            } else {
                                $product_array['opening_stock_details']['exp_date'] = null;
                            }
                        }
                    } elseif ($product_array['type'] == 'variable') {
                        $variation_name = $getVal($value, 'variation_name');
                        if (empty($variation_name)) {
                            $is_valid = false;
                            $error_msg = "VARIATION NAME is required in row no. $row_no";
                            break;
                        }
                        $variation_values_string = $getVal($value, 'variation_values');
                        if (empty($variation_values_string)) {
                            $is_valid = false;
                            $error_msg = "VARIATION VALUES are required in row no. $row_no";
                            break;
                        }

                        $variation_sku_string = $getVal($value, 'variation_skus') ?? '';
                        $dpp_inc_tax_string = $getVal($value, 'dpp_inc_tax') ?? '';
                        $dpp_exc_tax_string = $getVal($value, 'dpp_exc_tax') ?? '';
                        $selling_price_string = $getVal($value, 'selling_price') ?? '';
                        $profit_margin_string = $getVal($value, 'profit_margin') ?? '';

                        if (empty($dpp_inc_tax_string) && empty($dpp_exc_tax_string)) {
                            $is_valid = false;
                            $error_msg = "PURCHASE PRICE is required in row no. $row_no";
                            break;
                        }

                        //Variation values
                        $variation_values = array_map('trim', explode(
                            '|',
                            $variation_values_string
                        ));

                        $variation_skus = [];
                        if (! empty($variation_sku_string)) {
                            $variation_skus = array_map('trim', explode(
                            '|',
                                $variation_sku_string
                            ));
                        }

                        //Map Purchase price with variation values
                        $dpp_inc_tax = [];
                        if (! empty($dpp_inc_tax_string)) {
                            $dpp_inc_tax = array_map('trim', explode(
                                '|',
                                $dpp_inc_tax_string
                            ));
                        } else {
                            foreach ($variation_values as $k => $v) {
                                $dpp_inc_tax[$k] = 0;
                            }
                        }

                        $dpp_exc_tax = [];
                        if (! empty($dpp_exc_tax_string)) {
                            $dpp_exc_tax = array_map('trim', explode(
                                '|',
                                $dpp_exc_tax_string
                            ));
                        } else {
                            foreach ($variation_values as $k => $v) {
                                $dpp_exc_tax[$k] = 0;
                            }
                        }

                        //Map Selling price with variation values
                        $selling_price = [];
                        if (! empty($selling_price_string)) {
                            $selling_price = array_map('trim', explode(
                                '|',
                                $selling_price_string
                                ));
                        } else {
                            foreach ($variation_values as $k => $v) {
                                $selling_price[$k] = 0;
                            }
                        }

                        //Map profit margin with variation values
                        $profit_margin = [];
                        if (! empty($profit_margin_string)) {
                            $profit_margin = array_map('trim', explode(
                                '|',
                                $profit_margin_string
                                ));
                        } else {
                            foreach ($variation_values as $k => $v) {
                                $profit_margin[$k] = $default_profit_percent;
                            }
                        }

                        //Check if length of prices array is equal to variation values array length
                        $array_lengths_count = [count($variation_values), count($dpp_inc_tax), count($dpp_exc_tax), count($selling_price), count($profit_margin)];

                        if (! empty($variation_skus)) {
                            $array_lengths_count[] = count($variation_skus);
                        }
                        $same = array_count_values($array_lengths_count);

                        if (count($same) != 1) {
                            $is_valid = false;
                            $error_msg = "Prices mismatched with VARIATION VALUES in row no. $row_no";
                            break;
                        }
                        $product_array['variation']['name'] = $variation_name;

                        //Check if variation exists or create new
                        $variation = $this->productUtil->createOrNewVariation($business_id, $variation_name);
                        $product_array['variation']['variation_template_id'] = $variation->id;

                        foreach ($variation_values as $k => $v) {
                            $variation_prices = $this->calculateVariationPrices($dpp_exc_tax[$k], $dpp_inc_tax[$k], $selling_price[$k], $tax_amount, $tax_type, $profit_margin[$k]);

                            //get variation value
                            $variation_value = $variation->values->filter(function ($item) use ($v) {
                                return strtolower($item->name) == strtolower($v);
                            })->first();

                            if (empty($variation_value)) {
                                $variation_value = VariationValueTemplate::create([
                                    'name' => $v,
                                    'variation_template_id' => $variation->id,
                                ]);
                            }

                            //Assign Values
                            $product_array['variation']['variations'][] = [
                                'value' => $v,
                                'variation_value_id' => $variation_value->id,
                                'default_purchase_price' => $variation_prices['dpp_exc_tax'],
                                'dpp_inc_tax' => $variation_prices['dpp_inc_tax'],
                                'profit_percent' => $this->productUtil->num_f($profit_margin[$k]),
                                'default_sell_price' => $variation_prices['dsp_exc_tax'],
                                'sell_price_inc_tax' => $variation_prices['dsp_inc_tax'],
                                'sub_sku' => ! empty($variation_skus[$k]) ? $variation_skus[$k] : '',
                            ];
                        }

                        //Opening stock
                        if (! empty($opening_stock_val) && $enable_stock == 1) {
                            $variation_os = array_map('trim', explode('|', $opening_stock_val));

                            //Check if count of variation and opening stock is matching or not.
                            if (count($product_array['variation']['variations']) != count($variation_os)) {
                                $is_valid = false;
                                $error_msg = "Opening Stock mismatched with VARIATION VALUES in row no. $row_no";
                                break;
                            }

                            $location_name = $getVal($value, 'opening_stock_location');
                            if (! empty($location_name)) {
                                $location = BusinessLocation::where('name', $location_name)
                                                            ->where('business_id', $business_id)
                                                            ->first();
                                if (empty($location)) {
                                    $is_valid = false;
                                    $error_msg = "No location with name '$location_name' found in row no. $row_no";
                                    break;
                                }
                            } else {
                                $location = BusinessLocation::where('business_id', $business_id)->first();
                            }
                            $product_array['variation']['opening_stock_location'] = $location->id;

                            foreach ($variation_os as $k => $v) {
                                $product_array['variation']['variations'][$k]['opening_stock'] = $v;
                                $product_array['variation']['variations'][$k]['opening_stock_exp_date'] = null;

                                if (! empty($expiry_date_val)) {
                                    $product_array['variation']['variations'][$k]['opening_stock_exp_date'] = \Carbon::createFromFormat('m-d-Y', $expiry_date_val)->format('Y-m-d');
                                } else {
                                    $product_array['variation']['variations'][$k]['opening_stock_exp_date'] = null;
                                }
                            }
                        }
                    }

                    // Store internal details for rack, row, position, and locations
                    $product_array['__rack'] = $getVal($value, 'rack');
                    $product_array['__row'] = $getVal($value, 'row');
                    $product_array['__position'] = $getVal($value, 'position');
                    $product_array['__locations'] = $getVal($value, 'product_locations');

                    //Assign to formated array
                    $formated_data[] = $product_array;
                }

                if (! $is_valid) {
                    throw new \Exception($error_msg);
                }

                if (! empty($formated_data)) {
                    foreach ($formated_data as $index => $product_data) {
                        $variation_data = $product_data['variation'];
                        unset($product_data['variation']);

                        $opening_stock = null;
                        if (! empty($product_data['opening_stock_details'])) {
                            $opening_stock = $product_data['opening_stock_details'];
                        }
                        if (isset($product_data['opening_stock_details'])) {
                            unset($product_data['opening_stock_details']);
                        }

                        $rack_val = $product_data['__rack'] ?? null;
                        $row_val = $product_data['__row'] ?? null;
                        $position_val = $product_data['__position'] ?? null;
                        $locations_val = $product_data['__locations'] ?? null;
                        unset($product_data['__rack'], $product_data['__row'], $product_data['__position'], $product_data['__locations']);

                        //Create new product
                        $product = Product::create($product_data);
                        //If auto generate sku generate new sku
                        if ($product->sku == ' ') {
                            $sku = $this->productUtil->generateProductSku($product->id);
                            $product->sku = $sku;
                            $product->save();
                        }

                        //Rack, Row & Position.
                        $this->rackDetails(
                            $rack_val,
                            $row_val,
                            $position_val,
                            $business_id,
                            $product->id,
                            $index + 1
                        );

                        //Product locations
                        if (! empty($locations_val)) {
                            $locations_array = explode(',', $locations_val);
                            $location_ids = [];
                            foreach ($locations_array as $business_location) {
                                foreach ($business_locations as $loc) {
                                    if (strtolower($loc->name) == strtolower(trim($business_location))) {
                                        $location_ids[] = $loc->id;
                                    }
                                }
                            }
                            if (! empty($location_ids)) {
                                $product->product_locations()->sync($location_ids);
                            }
                        }

                        //Create single product variation
                        if ($product->type == 'single') {
                            $this->productUtil->createSingleProductVariation(
                                $product,
                                $product->sku,
                                $variation_data['dpp_exc_tax'],
                                $variation_data['dpp_inc_tax'],
                                $variation_data['profit_percent'],
                                $variation_data['dsp_exc_tax'],
                                $variation_data['dsp_inc_tax']
                            );
                            if (! empty($opening_stock)) {
                                $this->addOpeningStock($opening_stock, $product, $business_id);
                            }
                        } elseif ($product->type == 'variable') {
                            //Create variable product variations and with_out_variation is sku type of variation

                            $this->productUtil->createVariableProductVariations(
                                $product,
                                [$variation_data],
                                "with_out_variation",
                                $business_id
                            );

                            if (! empty($variation_data['opening_stock_location']) && $enable_stock == 1) {
                                $this->addOpeningStockForVariable($variation_data, $product, $business_id);
                            }
                        }
                    }
                }
            }

            $output = ['success' => 1,
                'msg' => __('product.file_imported_successfully'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect('import-products')->with('notification', $output);
        }

        return redirect('import-products')->with('status', $output);
    }

    private function calculateVariationPrices($dpp_exc_tax, $dpp_inc_tax, $selling_price, $tax_amount, $tax_type, $margin)
    {

        //Calculate purchase prices
        if ($dpp_inc_tax == 0) {
            $dpp_inc_tax = $this->productUtil->calc_percentage(
                $dpp_exc_tax,
                $tax_amount,
                $dpp_exc_tax
            );
        }

        if ($dpp_exc_tax == 0) {
            $dpp_exc_tax = $this->productUtil->calc_percentage_base($dpp_inc_tax, $tax_amount);
        }

        if ($selling_price != 0) {
            if ($tax_type == 'inclusive') {
                $dsp_inc_tax = $selling_price;
                $dsp_exc_tax = $this->productUtil->calc_percentage_base(
                    $dsp_inc_tax,
                    $tax_amount
                );
            } elseif ($tax_type == 'exclusive') {
                $dsp_exc_tax = $selling_price;
                $dsp_inc_tax = $this->productUtil->calc_percentage(
                    $selling_price,
                    $tax_amount,
                    $selling_price
                );
            }
        } else {
            $dsp_exc_tax = $this->productUtil->calc_percentage(
                $dpp_exc_tax,
                $margin,
                $dpp_exc_tax
            );
            $dsp_inc_tax = $this->productUtil->calc_percentage(
                $dsp_exc_tax,
                $tax_amount,
                $dsp_exc_tax
            );
        }

        return [
            'dpp_exc_tax' => $this->productUtil->num_f($dpp_exc_tax),
            'dpp_inc_tax' => $this->productUtil->num_f($dpp_inc_tax),
            'dsp_exc_tax' => $this->productUtil->num_f($dsp_exc_tax),
            'dsp_inc_tax' => $this->productUtil->num_f($dsp_inc_tax),
        ];
    }

    /**
     * Adds opening stock of a single product
     *
     * @param  array  $opening_stock
     * @param  obj  $product
     * @param  int  $business_id
     * @return void
     */
    private function addOpeningStock($opening_stock, $product, $business_id)
    {
        $user_id = request()->session()->get('user.id');

        $variation = Variation::where('product_id', $product->id)
            ->first();

        $total_before_tax = $opening_stock['quantity'] * $variation->dpp_inc_tax;

        $transaction_date = request()->session()->get('financial_year.start');
        $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();
        //Add opening stock transaction
        $transaction = Transaction::create(
            [
                'type' => 'opening_stock',
                'opening_stock_product_id' => $product->id,
                'status' => 'received',
                'business_id' => $business_id,
                'transaction_date' => $transaction_date,
                'total_before_tax' => $total_before_tax,
                'location_id' => $opening_stock['location_id'],
                'final_total' => $total_before_tax,
                'payment_status' => 'paid',
                'created_by' => $user_id,
            ]
        );
        //Get product tax
        $tax_percent = ! empty($product->product_tax->amount) ? $product->product_tax->amount : 0;
        $tax_id = ! empty($product->product_tax->id) ? $product->product_tax->id : null;

        $item_tax = $this->productUtil->calc_percentage($variation->default_purchase_price, $tax_percent);

        //Create purchase line
        $transaction->purchase_lines()->create([
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'quantity' => $opening_stock['quantity'],
            'item_tax' => $item_tax,
            'tax_id' => $tax_id,
            'pp_without_discount' => $variation->default_purchase_price,
            'purchase_price' => $variation->default_purchase_price,
            'purchase_price_inc_tax' => $variation->dpp_inc_tax,
            'exp_date' => ! empty($opening_stock['exp_date']) ? $opening_stock['exp_date'] : null,
        ]);
        //Update variation location details
        $this->productUtil->updateProductQuantity($opening_stock['location_id'], $product->id, $variation->id, $opening_stock['quantity']);

        //Add product location
        $this->__addProductLocation($product, $opening_stock['location_id']);
    }

    private function __addProductLocation($product, $location_id)
    {
        $count = DB::table('product_locations')->where('product_id', $product->id)
                                            ->where('location_id', $location_id)
                                            ->count();
        if ($count == 0) {
            DB::table('product_locations')->insert(['product_id' => $product->id,
                'location_id' => $location_id, ]);
        }
    }

    private function addOpeningStockForVariable($variations, $product, $business_id)
    {
        $user_id = request()->session()->get('user.id');

        $transaction_date = request()->session()->get('financial_year.start');
        $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();

        $total_before_tax = 0;
        $location_id = $variations['opening_stock_location'];
        if (isset($variations['variations'][0]['opening_stock'])) {
            //Add opening stock transaction
            $transaction = Transaction::create(
                [
                    'type' => 'opening_stock',
                    'opening_stock_product_id' => $product->id,
                    'status' => 'received',
                    'business_id' => $business_id,
                    'transaction_date' => $transaction_date,
                    'total_before_tax' => $total_before_tax,
                    'location_id' => $location_id,
                    'final_total' => $total_before_tax,
                    'payment_status' => 'paid',
                    'created_by' => $user_id,
                ]
            );

            //Add product location
            $this->__addProductLocation($product, $location_id);

            foreach ($variations['variations'] as $variation_os) {
                if (! empty($variation_os['opening_stock'])) {
                    $variation = Variation::where('product_id', $product->id)
                                    ->where('name', $variation_os['value'])
                                    ->first();
                    if (! empty($variation)) {
                        $opening_stock = [
                            'quantity' => $variation_os['opening_stock'],
                            'exp_date' => $variation_os['opening_stock_exp_date'],
                        ];

                        $total_before_tax = $total_before_tax + ($variation_os['opening_stock'] * $variation->dpp_inc_tax);
                    }

                    //Get product tax
                    $tax_percent = ! empty($product->product_tax->amount) ? $product->product_tax->amount : 0;
                    $tax_id = ! empty($product->product_tax->id) ? $product->product_tax->id : null;

                    $item_tax = $this->productUtil->calc_percentage($variation->default_purchase_price, $tax_percent);

                    //Create purchase line
                    $transaction->purchase_lines()->create([
                        'product_id' => $product->id,
                        'variation_id' => $variation->id,
                        'quantity' => $opening_stock['quantity'],
                        'item_tax' => $item_tax,
                        'tax_id' => $tax_id,
                        'purchase_price' => $variation->default_purchase_price,
                        'purchase_price_inc_tax' => $variation->dpp_inc_tax,
                        'exp_date' => ! empty($opening_stock['exp_date']) ? $opening_stock['exp_date'] : null,
                    ]);
                    //Update variation location details
                    $this->productUtil->updateProductQuantity($location_id, $product->id, $variation->id, $opening_stock['quantity']);
                }
            }

            $transaction->total_before_tax = $total_before_tax;
            $transaction->final_total = $total_before_tax;
            $transaction->save();
        }
    }

    private function rackDetails($rack_value, $row_value, $position_value, $business_id, $product_id, $row_no)
    {
        if (! empty($rack_value) || ! empty($row_value) || ! empty($position_value)) {
            $locations = BusinessLocation::forDropdown($business_id);
            $loc_count = count($locations);

            $racks = explode('|', $rack_value);
            $rows = explode('|', $row_value);
            $position = explode('|', $position_value);

            if (count($racks) > $loc_count) {
                $error_msg = "Invalid value for RACK in row no. $row_no";
                throw new \Exception($error_msg);
            }

            if (count($rows) > $loc_count) {
                $error_msg = "Invalid value for ROW in row no. $row_no";
                throw new \Exception($error_msg);
            }

            if (count($position) > $loc_count) {
                $error_msg = "Invalid value for POSITION in row no. $row_no";
                throw new \Exception($error_msg);
            }

            $rack_details = [];
            $counter = 0;
            foreach ($locations as $key => $value) {
                $rack_details[$key]['rack'] = isset($racks[$counter]) ? $racks[$counter] : '';
                $rack_details[$key]['row'] = isset($rows[$counter]) ? $rows[$counter] : '';
                $rack_details[$key]['position'] = isset($position[$counter]) ? $position[$counter] : '';
                $counter += 1;
            }

            if (! empty($rack_details)) {
                $this->productUtil->addRackDetails($business_id, $product_id, $rack_details);
            }
        }
    }
}
