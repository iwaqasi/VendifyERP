@extends('layouts.app')
@php
    $heading = !empty($module_category_data['heading']) ? $module_category_data['heading'] : __('category.categories');
    $category_type = request()->get('type', 'product');
@endphp
@section('title', __('lang_v1.import') . ' ' . $heading)

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('lang_v1.import') {{ $heading }}
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">
            @lang('category.manage_your_categories')
        </small>
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
                {!! Form::open(['action' => [\App\Http\Controllers\TaxonomyController::class, 'postImportTaxonomies'], 'method' => 'post', 'enctype' => 'multipart/form-data' ]) !!}
                    <input type="hidden" name="type" value="{{ $category_type }}">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="col-sm-8">
                                <div class="form-group">
                                    {!! Form::label('name', __( 'product.file_to_import' ) . ':') !!}
                                    {!! Form::file('taxonomy_csv', ['accept'=> '.xlsx, .xls, .csv', 'required' => 'required']); !!}
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
                        <a href="{{ action([\App\Http\Controllers\TaxonomyController::class, 'downloadTemplate']) . '?type=' . $category_type }}" class="tw-dw-btn tw-dw-btn-success tw-text-white" download>
                            <i class="fa fa-download"></i> @lang('lang_v1.download_template_file')
                        </a>
                    </div>
                    <div class="col-sm-4">
                        <a href="{{ action([\App\Http\Controllers\TaxonomyController::class, 'downloadExcel']) . '?type=' . $category_type }}" class="tw-dw-btn tw-dw-btn-info tw-text-white" download>
                            <i class="fa fa-file-excel-o"></i> @lang('lang_v1.download_excel') (Current Categories)
                        </a>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.instructions')])
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
                            <td>Category Name <small class="text-danger">(@lang('lang_v1.required'))</small></td>
                            <td>Name of the category (e.g., Electronics, Beverages)</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Category Code <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Short code for the category (e.g., ELEC, BEV). If empty, can be auto-generated.</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>Description <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>Brief description of the category</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>Parent Category Name <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>If this is a sub-category, specify the name of the main parent category (e.g., Electronics).</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td>Parent Category Code <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                            <td>If this is a sub-category, you can also specify the parent category's code for matching.</td>
                        </tr>
                    </tbody>
                </table>
            @endcomponent
        </div>
    </div>
</section>
@endsection
