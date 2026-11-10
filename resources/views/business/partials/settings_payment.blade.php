<!--payment related settings -->
<div class="pos-tab-content">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('cash_denominations', __('lang_v1.cash_denominations') . ':') !!}
                 {!! Form::text('pos_settings[cash_denominations]', isset($pos_settings['cash_denominations']) ? $pos_settings['cash_denominations'] : null, ['class' => 'form-control', 'id' => 'cash_denominations']); !!}
                 <p class="help-block">{{__('lang_v1.cash_denominations_help')}}</p>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('enable_cash_denomination_on', __('lang_v1.enable_cash_denomination_on') . ':') !!}
                {!! Form::select('pos_settings[enable_cash_denomination_on]', ['pos_screen' => __('lang_v1.pos_screen'), 'all_screens' => __('lang_v1.all_screen')], isset($pos_settings['enable_cash_denomination_on']) ? $pos_settings['enable_cash_denomination_on'] : 'pos_screen', ['class' => 'form-control', 'style' => 'width: 100%;' ]); !!}
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('enable_cash_denomination_for_payment_methods', __('lang_v1.enable_cash_denomination_for_payment_methods') . ':') !!}
                {!! Form::select('pos_settings[enable_cash_denomination_for_payment_methods][]', $payment_types, isset($pos_settings['enable_cash_denomination_for_payment_methods']) ? $pos_settings['enable_cash_denomination_for_payment_methods'] : null, ['class' => 'form-control select2', 'style' => 'width: 100%;', 'multiple' ]); !!}
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[cash_denomination_strict_check]', 1,  
                        !empty($pos_settings['cash_denomination_strict_check']) , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.strict_check' ) }}
                  </label>
                  @show_tooltip(__('lang_v1.strict_check_help'))
                </div>
            </div>
        </div>
    </div>

    <hr>
    <h4><i class="fas fa-credit-card text-primary"></i> GCC Payment Gateways (MyFatoorah / Tap / K-NET / Mada)</h4>
    <p class="text-muted">Configure your regional payment gateway credentials. If left empty, system-level credentials from <code>.env</code> will be used.</p>
    
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('myfatoorah_api_key', 'MyFatoorah API Token Key:') !!}
                {!! Form::text('pos_settings[myfatoorah_api_key]', $pos_settings['myfatoorah_api_key'] ?? null, ['class' => 'form-control', 'placeholder' => config('myfatoorah.api_key') ? '•••••••• (Using system .env key)' : 'Paste API Token here']) !!}
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('myfatoorah_country_iso', 'Gateway Country:') !!}
                {!! Form::select('pos_settings[myfatoorah_country_iso]', [
                    'KWT' => 'Kuwait (K-NET / Cards)',
                    'SAU' => 'Saudi Arabia (Mada / Cards)',
                    'ARE' => 'UAE (Cards / Apple Pay)',
                    'QAT' => 'Qatar (QPay)',
                    'BHR' => 'Bahrain (Benefit)',
                    'OMN' => 'Oman (OmanNet)',
                    'EGY' => 'Egypt (Fawry / Meeza)'
                ], $pos_settings['myfatoorah_country_iso'] ?? config('myfatoorah.country_iso', 'KWT'), ['class' => 'form-control select2', 'style' => 'width: 100%;']) !!}
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('myfatoorah_test_mode', 'Environment Mode:') !!}
                {!! Form::select('pos_settings[myfatoorah_test_mode]', [
                    '0' => 'Live / Production',
                    '1' => 'Test / Sandbox'
                ], isset($pos_settings['myfatoorah_test_mode']) ? $pos_settings['myfatoorah_test_mode'] : (config('myfatoorah.test_mode') ? '1' : '0'), ['class' => 'form-control', 'style' => 'width: 100%;']) !!}
            </div>
        </div>

        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('myfatoorah_webhook_secret', 'Webhook Secret Key (IPN):') !!}
                {!! Form::text('pos_settings[myfatoorah_webhook_secret]', $pos_settings['myfatoorah_webhook_secret'] ?? null, ['class' => 'form-control', 'placeholder' => 'Optional secret key for signature verification']) !!}
                <small class="text-muted">Webhook Endpoint URL: <code>{{ url('/myfatoorah/webhook') }}</code></small>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="form-group">
                <br>
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('pos_settings[myfatoorah_enable_apple_pay]', 1, !empty($pos_settings['myfatoorah_enable_apple_pay']), ['class' => 'input-icheck']) !!}
                        Enable Direct Apple Pay (Requires verified merchant domain)
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>