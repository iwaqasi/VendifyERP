<div class="pos-tab-content">
    <div class="row">
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('tax_label_1', __('business.tax_1_name') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-info"></i>
                    </span>
                    {!! Form::text('tax_label_1', $business->tax_label_1, ['class' => 'form-control','placeholder' => __('business.tax_1_placeholder')]); !!}
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('tax_number_1', __('business.tax_1_no') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-info"></i>
                    </span>
                    {!! Form::text('tax_number_1', $business->tax_number_1, ['class' => 'form-control']); !!}
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('tax_label_2', __('business.tax_2_name') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-info"></i>
                    </span>
                    {!! Form::text('tax_label_2', $business->tax_label_2, ['class' => 'form-control','placeholder' => __('business.tax_1_placeholder')]); !!}
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('tax_number_2', __('business.tax_2_no') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-info"></i>
                    </span>
                    {!! Form::text('tax_number_2', $business->tax_number_2, ['class' => 'form-control']); !!}
                </div>
            </div>
        </div>
        <div class="col-sm-8">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('enable_inline_tax', 1, $business->enable_inline_tax , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.enable_inline_tax' ) }}
                  </label>
                </div>
            </div>
        </div>
    </div>
    
    <hr style="margin: 15px 0; border-color: #e2e8f0;">
    <h4 style="font-weight: 700; color: #1e293b; margin-bottom: 15px;">
        <i class="fa fa-university text-primary" style="margin-right: 6px;"></i> Kuwait Statutory Compliance & 2027 VAT Readiness
    </h4>
    <div class="row">
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('kuwait_cr_number', 'Kuwait Commercial Registration (CR):') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-id-card-o"></i></span>
                    {!! Form::text('kuwait_cr_number', $business->kuwait_cr_number, ['class' => 'form-control', 'placeholder' => 'e.g. 123456']); !!}
                </div>
                <small class="text-muted">Mandatory on all Kuwait receipts and MOCI XBRL audits.</small>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('kuwait_moci_license', 'MOCI Commercial License Number:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-certificate"></i></span>
                    {!! Form::text('kuwait_moci_license', $business->kuwait_moci_license, ['class' => 'form-control', 'placeholder' => 'e.g. 2021/10452']); !!}
                </div>
                <small class="text-muted">Ministry of Commerce & Industry license reference.</small>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('kuwait_paci_number', 'PACI Building / Civil ID Number:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-map-marker"></i></span>
                    {!! Form::text('kuwait_paci_number', $business->kuwait_paci_number, ['class' => 'form-control', 'placeholder' => 'e.g. 18239012']); !!}
                </div>
                <small class="text-muted">Public Authority for Civil Information (PACI) ID.</small>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                  <label>
                    {!! Form::checkbox('enable_xbrl_reporting', 1, $business->enable_xbrl_reporting ?? 1, 
                    [ 'class' => 'input-icheck']); !!} Enable MOCI Kuwait IFRS XBRL Financial Audit Module
                  </label>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                  <label>
                    {!! Form::checkbox('enable_kuwait_vat_ready', 1, $business->enable_kuwait_vat_ready ?? 1, 
                    [ 'class' => 'input-icheck']); !!} Kuwait 2027 5% VAT & GCC TLV QR Code Receipt Integration
                  </label>
                </div>
            </div>
        </div>
    </div>
</div>