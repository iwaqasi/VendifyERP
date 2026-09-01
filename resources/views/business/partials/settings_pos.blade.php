<div class="pos-tab-content">
    <h4>@lang('business.add_keyboard_shortcuts'):</h4>
    <p class="help-block">@lang('lang_v1.shortcut_help'); @lang('lang_v1.example'): <b>ctrl+shift+b</b>, <b>ctrl+h</b></p>
    <p class="help-block">
        <b>@lang('lang_v1.available_key_names_are'):</b>
        <br> shift, ctrl, alt, backspace, tab, enter, return, capslock, esc, escape, space, pageup, pagedown, end, home, <br>left, up, right, down, ins, del, and plus
    </p>
    <div class="row">
        <div class="col-sm-6">
            <table class="table table-striped">
                <tr>
                    <th>@lang('business.operations')</th>
                    <th>@lang('business.keyboard_shortcut')</th>
                </tr>
                <tr>
                    <td>{!! __('sale.express_finalize') !!}:</td>
                    <td>
                        {!! Form::text('shortcuts[pos][express_checkout]', 
                        !empty($shortcuts["pos"]["express_checkout"]) ? $shortcuts["pos"]["express_checkout"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('sale.finalize'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][pay_n_ckeckout]', !empty($shortcuts["pos"]["pay_n_ckeckout"]) ? $shortcuts["pos"]["pay_n_ckeckout"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('sale.draft'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][draft]', !empty($shortcuts["pos"]["draft"]) ? $shortcuts["pos"]["draft"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('messages.cancel'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][cancel]', !empty($shortcuts["pos"]["cancel"]) ? $shortcuts["pos"]["cancel"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('lang_v1.recent_product_quantity'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][recent_product_quantity]', !empty($shortcuts["pos"]["recent_product_quantity"]) ? $shortcuts["pos"]["recent_product_quantity"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('lang_v1.weighing_scale'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][weighing_scale]', !empty($shortcuts["pos"]["weighing_scale"]) ? $shortcuts["pos"]["weighing_scale"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
            </table>
        </div>
        <div class="col-sm-6">
            <table class="table table-striped">
                <tr>
                    <th>@lang('business.operations')</th>
                    <th>@lang('business.keyboard_shortcut')</th>
                </tr>
                <tr>
                    <td>@lang('sale.edit_discount'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][edit_discount]', !empty($shortcuts["pos"]["edit_discount"]) ? $shortcuts["pos"]["edit_discount"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('sale.edit_order_tax'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][edit_order_tax]', !empty($shortcuts["pos"]["edit_order_tax"]) ? $shortcuts["pos"]["edit_order_tax"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('sale.add_payment_row'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][add_payment_row]', !empty($shortcuts["pos"]["add_payment_row"]) ? $shortcuts["pos"]["add_payment_row"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('sale.finalize_payment'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][finalize_payment]', !empty($shortcuts["pos"]["finalize_payment"]) ? $shortcuts["pos"]["finalize_payment"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
                <tr>
                    <td>@lang('lang_v1.add_new_product'):</td>
                    <td>
                        {!! Form::text('shortcuts[pos][add_new_product]', !empty($shortcuts["pos"]["add_new_product"]) ? $shortcuts["pos"]["add_new_product"] : null, ['class' => 'form-control']); !!}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <h4>@lang('lang_v1.pos_settings'):</h4>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[disable_pay_checkout]', 1,  
                        $pos_settings['disable_pay_checkout'] , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.disable_pay_checkout' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[disable_draft]', 1,  
                        $pos_settings['disable_draft'] , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.disable_draft' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[disable_express_checkout]', 1,  
                        $pos_settings['disable_express_checkout'] , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.disable_express_checkout' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[hide_product_suggestion]', 1,  $pos_settings['hide_product_suggestion'] , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.hide_product_suggestion' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[hide_recent_trans]', 1,  $pos_settings['hide_recent_trans'] , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.hide_recent_trans' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[disable_discount]', 1,  $pos_settings['disable_discount'] , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.disable_discount' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[disable_order_tax]', 1,  $pos_settings['disable_order_tax'] , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.disable_order_tax' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[is_pos_subtotal_editable]', 1,  
                    empty($pos_settings['is_pos_subtotal_editable']) ? 0 : 1 , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.subtotal_editable' ) }}
                  </label>
                  @show_tooltip(__('lang_v1.subtotal_editable_help_text'))
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[disable_suspend]', 1,  
                    empty($pos_settings['disable_suspend']) ? 0 : 1 , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.disable_suspend_sale' ) }}
                  </label>
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[enable_transaction_date]', 1,  
                    empty($pos_settings['enable_transaction_date']) ? 0 : 1 , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.enable_pos_transaction_date' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[inline_service_staff]', 1,  
                    !empty($pos_settings['inline_service_staff']) ? true : false , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.enable_service_staff_in_product_line' ) }}
                  </label>
                  @show_tooltip(__('lang_v1.inline_service_staff_tooltip'))
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[is_service_staff_required]', 1,  
                    empty($pos_settings['is_service_staff_required']) ? 0 : 1 , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.is_service_staff_required' ) }}
                  </label>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[disable_credit_sale_button]', 1,  
                    empty($pos_settings['disable_credit_sale_button']) ? 0 : 1 , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.disable_credit_sale_button' ) }}
                  </label>
                  @show_tooltip(__('lang_v1.show_credit_sale_btn_help'))
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[enable_weighing_scale]', 1,  
                    empty($pos_settings['enable_weighing_scale']) ? 0 : 1 , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.enable_weighing_scale' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[show_invoice_scheme]', 1,  
                       empty($pos_settings['show_invoice_scheme']) ? 0 : 1 , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.show_invoice_scheme' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[show_invoice_layout]', 1,  
                        !empty($pos_settings['show_invoice_layout']) , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.show_invoice_layout' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[print_on_suspend]', 1,  
                        !empty($pos_settings['print_on_suspend']) , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.print_on_suspend' ) }}
                  </label>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                <br>
                  <label>
                    {!! Form::checkbox('pos_settings[show_pricing_on_product_sugesstion]', 1,  
                        !empty($pos_settings['show_pricing_on_product_sugesstion']) , 
                    [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.show_pricing_on_product_sugesstion' ) }}
                  </label>
                </div>
            </div>
        </div>
    </div>

    <hr>

    {{-- VendifyPOS Settings (Flutter App Configuration) --}}
    <div class="row">
        <div class="col-sm-12">
            <h4><i class="fa fa-mobile"></i> VendifyPOS Settings <small class="text-muted">(Flutter POS App Configuration)</small></h4>
            <p class="text-muted">Configure settings for the VendifyPOS Flutter application. These settings are used by the mobile and desktop POS app.</p>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <label>Receipt Prefix:</label>
                <input type="text" name="pos_settings[pos_receipt_prefix]" class="form-control" 
                       value="{{ $pos_settings['pos_receipt_prefix'] ?? 'INV-POS-' }}" 
                       placeholder="e.g. INV-POS-">
                <p class="help-block">Prefix for invoice numbers in POS (e.g., INV-POS-)</p>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <label>Default Payment Method:</label>
                <select name="pos_settings[pos_default_payment_method]" class="form-control">
                    <option value="cash" {{ ($pos_settings['pos_default_payment_method'] ?? 'cash') == 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="card" {{ ($pos_settings['pos_default_payment_method'] ?? '') == 'card' ? 'selected' : '' }}>Card (Debit/Credit)</option>
                    <option value="bank_transfer" {{ ($pos_settings['pos_default_payment_method'] ?? '') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="cheque" {{ ($pos_settings['pos_default_payment_method'] ?? '') == 'cheque' ? 'selected' : '' }}>Cheque</option>
                    <option value="other" {{ ($pos_settings['pos_default_payment_method'] ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                </select>
                <p class="help-block">Default payment method when checkout opens</p>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <label>Tax Behavior:</label>
                <select name="pos_settings[pos_tax_behavior]" class="form-control">
                    <option value="exclusive" {{ ($pos_settings['pos_tax_behavior'] ?? 'exclusive') == 'exclusive' ? 'selected' : '' }}>Tax Exclusive (add tax on top)</option>
                    <option value="inclusive" {{ ($pos_settings['pos_tax_behavior'] ?? '') == 'inclusive' ? 'selected' : '' }}>Tax Inclusive (tax included in price)</option>
                </select>
                <p class="help-block">How tax is calculated in POS</p>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <label>Currency Symbol:</label>
                <input type="text" name="pos_settings[pos_currency_symbol]" class="form-control" 
                       value="{{ $pos_settings['pos_currency_symbol'] ?? 'KD' }}" 
                       placeholder="e.g. KD, $, EUR">
                <p class="help-block">Currency symbol displayed in POS app</p>
            </div>
        </div>

        <div class="col-sm-8">
            <div class="form-group">
                <label>Receipt Footer Text:</label>
                <textarea name="pos_settings[pos_receipt_footer]" class="form-control" rows="2" 
                          placeholder="Thank you for your purchase!">{{ $pos_settings['pos_receipt_footer'] ?? 'Thank you for your purchase!' }}</textarea>
                <p class="help-block">Text displayed at the bottom of the receipt</p>
            </div>
        </div>

        <div class="col-sm-12"><hr><h5>POS Features</h5></div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="hidden" name="pos_settings[pos_enable_hold_recall]" value="0">
                        <input type="checkbox" name="pos_settings[pos_enable_hold_recall]" value="1" 
                            class="input-icheck" {{ ($pos_settings['pos_enable_hold_recall'] ?? 1) ? 'checked' : '' }}>
                        Enable Hold &amp; Recall Cart
                    </label>
                </div>
                <p class="help-block">Allow cashiers to hold current cart and recall later</p>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="hidden" name="pos_settings[pos_enable_split_payment]" value="0">
                        <input type="checkbox" name="pos_settings[pos_enable_split_payment]" value="1" 
                            class="input-icheck" {{ ($pos_settings['pos_enable_split_payment'] ?? 1) ? 'checked' : '' }}>
                        Enable Split Payment
                    </label>
                </div>
                <p class="help-block">Allow splitting payment across multiple methods</p>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="hidden" name="pos_settings[pos_enable_auth_code]" value="0">
                        <input type="checkbox" name="pos_settings[pos_enable_auth_code]" value="1" 
                            class="input-icheck" {{ ($pos_settings['pos_enable_auth_code'] ?? 1) ? 'checked' : '' }}>
                        Enable Auth/Approval Code for Cards
                    </label>
                </div>
                <p class="help-block">Require auth code when processing card payments</p>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="hidden" name="pos_settings[pos_enable_customer_display]" value="0">
                        <input type="checkbox" name="pos_settings[pos_enable_customer_display]" value="1" 
                            class="input-icheck" {{ ($pos_settings['pos_enable_customer_display'] ?? 0) ? 'checked' : '' }}>
                        Enable Customer Display Screen
                    </label>
                </div>
                <p class="help-block">Show a secondary display for customers</p>
            </div>
        </div>
    </div>

    <hr>
    @include('business.partials.settings_weighing_scale')
</div>
