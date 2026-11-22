@extends('layouts.app')
@section('title', __('product.import_products'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('product.import_products')
    </h1>
</section>

<!-- Main content -->
<section class="content">
    
    @if (session('notification') || !empty($notification))
        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-{{ session('notification.success') || (!empty($notification) && $notification['success']) ? 'success' : 'danger' }} alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    @if(!empty($notification['msg']))
                        {{$notification['msg']}}
                    @elseif(session('notification.msg'))
                        {{ session('notification.msg') }}
                    @endif
                </div>
            </div>  
        </div>     
    @endif
    
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary'])
                {!! Form::open(['url' => action([\App\Http\Controllers\ImportProductsController::class, 'store']), 'method' => 'post', 'enctype' => 'multipart/form-data' ]) !!}
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="col-sm-8">
                                <div class="form-group">
                                    {!! Form::label('name', __( 'product.file_to_import' ) . ':') !!}
                                    {!! Form::file('products_csv', ['accept'=> '.xls, .xlsx, .csv', 'required' => 'required']); !!}
                                    <small class="text-muted">Accepts .xlsx, .xls, or .csv files</small>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <br>
                                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.submit')</button>
                            </div>
                        </div>
                    </div>

                {!! Form::close() !!}
                <br><br>
                <div class="row">
                    <div class="col-sm-4">
                        <a href="{{ action([\App\Http\Controllers\ImportProductsController::class, 'downloadTemplate']) }}" class="tw-dw-btn tw-dw-btn-success tw-text-white" download>
                            <i class="fa fa-download"></i> @lang('lang_v1.download_template_file') (Excel with Arabic Fields)
                        </a>
                    </div>
                    <div class="col-sm-4">
                        <a href="{{ asset('files/import_products_csv_template.csv') }}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-primary" download>
                            <i class="fa fa-file-text-o"></i> @lang('lang_v1.download_template_file') (CSV)
                        </a>
                    </div>
                    <div class="col-sm-4">
                        <a href="{{ action([\App\Http\Controllers\ProductController::class, 'downloadExcel']) }}" class="tw-dw-btn tw-dw-btn-info tw-text-white" download>
                            <i class="fa fa-file-excel-o"></i> @lang('lang_v1.download_excel') (Current Products)
                        </a>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.instructions')])
                <div class="callout callout-info">
                    <h4><i class="fa fa-info-circle"></i> Arabic Language & Column Mapping Support</h4>
                    <p>Both modern 41-column spreadsheets (containing <strong>NAME AR</strong>, <strong>BARCODE</strong>, and <strong>PRODUCT DESCRIPTION AR</strong>) and legacy 37-column spreadsheets are automatically recognized. Column headers are matched dynamically by name.</p>
                </div>

                <strong>@lang('lang_v1.instruction_line1')</strong><br>
                @lang('lang_v1.instruction_line2')
                <br><br>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>@lang('lang_v1.col_no')</th>
                            <th>@lang('lang_v1.col_name')</th>
                            <th>@lang('lang_v1.instruction')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>@lang('product.product_name') (English) <small class="text-danger">(@lang('lang_v1.required'))</small></td>
                            <td>@lang('lang_v1.name_ins')</td>
                        </tr>
                        <tr class="bg-info" style="background-color: #f0f7fd;">
                            <td>2</td>
                            <td><strong>NAME AR (Product Name in Arabic)</strong> <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Product name in Arabic (e.g. تيشيرت بولو رجالي)</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>@lang('product.brand') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.brand_ins') <br><small class="text-muted">(@lang('lang_v1.brand_ins2'))</small></td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>@lang('product.unit') <small class="text-danger">(@lang('lang_v1.required'))</small></td>
                            <td>@lang('lang_v1.unit_ins')</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td>@lang('product.category') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.category_ins') <br><small class="text-muted">(@lang('lang_v1.category_ins2'))</small></td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td>@lang('product.sub_category') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.sub_category_ins') <br><small class="text-muted">({!! __('lang_v1.sub_category_ins2') !!})</small></td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td>@lang('product.sku') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.sku_ins')</td>
                        </tr>
                        <tr class="bg-info" style="background-color: #f0f7fd;">
                            <td>8</td>
                            <td><strong>BARCODE</strong> <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Product Barcode string or GTIN/EAN (e.g. 628100000001)</td>
                        </tr>
                        <tr>
                            <td>9</td>
                            <td>@lang('product.barcode_type') <small class="text-muted">(@lang('lang_v1.optional'), @lang('lang_v1.default'): C128)</small></td>
                            <td>@lang('lang_v1.barcode_type_ins') <br>
                                <strong>@lang('lang_v1.barcode_type_ins2'): C128, C39, EAN-13, EAN-8, UPC-A, UPC-E, ITF-14</strong>
                            </td>
                        </tr>
                        <tr>
                            <td>10</td>
                            <td>@lang('product.manage_stock') <small class="text-danger">(@lang('lang_v1.required'))</small></td>
                            <td>@lang('lang_v1.manage_stock_ins')<br>
                                <strong>1 = @lang('messages.yes')<br>
                                0 = @lang('messages.no')</strong>
                            </td>
                        </tr>
                        <tr>
                            <td>11</td>
                            <td>@lang('product.alert_quantity') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('product.alert_quantity')</td>
                        </tr>
                        <tr>
                            <td>12</td>
                            <td>@lang('product.expires_in') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.expires_in_ins')</td>
                        </tr>
                        <tr>
                            <td>13</td>
                            <td>@lang('lang_v1.expire_period_unit') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.expire_period_unit_ins')<br>
                                <strong>@lang('lang_v1.available_options'): days, months</strong>
                            </td>
                        </tr>
                        <tr>
                            <td>14</td>
                            <td>@lang('product.applicable_tax') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.applicable_tax_ins') {!! __('lang_v1.applicable_tax_help') !!}</td>
                        </tr>
                        <tr>
                            <td>15</td>
                            <td>@lang('product.selling_price_tax_type') <small class="text-danger">(@lang('lang_v1.required'))</small></td>
                            <td>@lang('product.selling_price_tax_type') <br>
                                <strong>@lang('lang_v1.available_options'): inclusive, exclusive</strong>
                            </td>
                        </tr>
                        <tr>
                            <td>16</td>
                            <td>@lang('product.product_type') <small class="text-danger">(@lang('lang_v1.required'))</small></td>
                            <td>@lang('product.product_type') <br>
                                <strong>@lang('lang_v1.available_options'): single, variable</strong></td>
                        </tr>
                        <tr>
                            <td>17</td>
                            <td>@lang('product.variation_name') <small class="text-muted">(@lang('lang_v1.variation_name_ins'))</small></td>
                            <td>@lang('lang_v1.variation_name_ins2')</td>
                        </tr>
                        <tr>
                            <td>18</td>
                            <td>@lang('product.variation_values') <small class="text-muted">(@lang('lang_v1.variation_values_ins'))</small></td>
                            <td>{!! __('lang_v1.variation_values_ins2') !!}</td>
                        </tr>
                        <tr>
                            <td>19</td>
                            <td>@lang('lang_v1.variation_sku') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>{!! __('lang_v1.variation_sku_ins') !!}</td>
                        </tr>
                        <tr>
                            <td>20</td>
                            <td>@lang('lang_v1.purchase_price_inc_tax') <br><small class="text-muted">(@lang('lang_v1.purchase_price_inc_tax_ins1'))</small></td>
                            <td>{!! __('lang_v1.purchase_price_inc_tax_ins2') !!}</td>
                        </tr>
                        <tr>
                            <td>21</td>
                            <td>@lang('lang_v1.purchase_price_exc_tax') <br><small class="text-muted">(@lang('lang_v1.purchase_price_exc_tax_ins1'))</small></td>
                            <td>{!! __('lang_v1.purchase_price_exc_tax_ins2') !!}</td>
                        </tr>
                        <tr>
                            <td>22</td>
                            <td>@lang('lang_v1.profit_margin') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.profit_margin_ins')<br>
                                <small class="text-muted">{!! __('lang_v1.profit_margin_ins1') !!}</small></td>
                        </tr>
                        <tr>
                            <td>23</td>
                            <td>@lang('lang_v1.selling_price') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.selling_price_ins')<br>
                                <small class="text-muted">{!! __('lang_v1.selling_price_ins1') !!}</small></td>
                        </tr>
                        <tr>
                            <td>24</td>
                            <td>@lang('lang_v1.opening_stock') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.opening_stock_ins') {!! __('lang_v1.opening_stock_help_text') !!}</td>
                        </tr>
                        <tr>
                            <td>25</td>
                            <td>@lang('lang_v1.opening_stock_location') <small class="text-muted">(@lang('lang_v1.optional')) <br>@lang('lang_v1.location_ins')</small></td>
                            <td>@lang('lang_v1.location_ins1')</td>
                        </tr>
                        <tr>
                            <td>26</td>
                            <td>@lang('lang_v1.expiry_date') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>{!! __('lang_v1.expiry_date_ins') !!}</td>
                        </tr>
                        <tr>
                            <td>27</td>
                            <td>@lang('lang_v1.enable_imei_or_sr_no') <small class="text-muted">(@lang('lang_v1.optional'), @lang('lang_v1.default'): 0)</small></td>
                            <td><strong>1 = @lang('messages.yes')<br>
                                0 = @lang('messages.no')</strong></td>
                        </tr>
                        <tr>
                            <td>28</td>
                            <td>@lang('lang_v1.weight') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.optional')</td>
                        </tr>
                        <tr>
                            <td>29</td>
                            <td>@lang('lang_v1.rack') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>{!! __('lang_v1.rack_help_text') !!}</td>
                        </tr>
                        <tr>
                            <td>30</td>
                            <td>@lang('lang_v1.row') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>{!! __('lang_v1.row_help_text') !!}</td>
                        </tr>
                        <tr>
                            <td>31</td>
                            <td>@lang('lang_v1.position') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>{!! __('lang_v1.position_help_text') !!}</td>
                        </tr>
                        <tr>
                            <td>32</td>
                            <td>@lang('lang_v1.image') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>{!! __('lang_v1.image_help_text', ['path' => 'public/uploads/'.config('constants.product_img_path')]) !!} <br><br>
                                {{__('lang_v1.img_url_help_text')}}</td>
                        </tr>
                        <tr>
                            <td>33</td>
                            <td>@lang('lang_v1.product_description') (English) <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Product description in English</td>
                        </tr>
                        <tr class="bg-info" style="background-color: #f0f7fd;">
                            <td>34</td>
                            <td><strong>PRODUCT DESCRIPTION AR (Arabic Description)</strong> <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Detailed product description in Arabic</td>
                        </tr>
                        <tr>
                            <td>35</td>
                            <td>@lang('lang_v1.product_custom_field1') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Optional custom field 1</td>
                        </tr>
                        <tr>
                            <td>36</td>
                            <td>@lang('lang_v1.product_custom_field2') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Optional custom field 2</td>
                        </tr>
                        <tr>
                            <td>37</td>
                            <td>@lang('lang_v1.product_custom_field3') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Optional custom field 3</td>
                        </tr>
                        <tr>
                            <td>38</td>
                            <td>@lang('lang_v1.product_custom_field4') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Optional custom field 4</td>
                        </tr>
                        <tr>
                            <td>39</td>
                            <td>@lang('lang_v1.not_for_selling') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td><strong>1 = @lang('messages.yes')<br>
                                0 = @lang('messages.no')</strong></td>
                        </tr>
                        <tr>
                            <td>40</td>
                            <td>@lang('lang_v1.product_locations') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>@lang('lang_v1.product_locations_ins')</td>
                        </tr>
                        <tr class="bg-info" style="background-color: #f0f7fd;">
                            <td>41</td>
                            <td><strong>FLEXIBLE PRICE</strong> <small class="text-muted">(@lang('lang_v1.optional'), default: 1)</small></td>
                            <td><strong>1 = Yes, allow flexible pricing at POS<br>0 = No</strong></td>
                        </tr>
                    </tbody>
                </table>
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->

@endsection
