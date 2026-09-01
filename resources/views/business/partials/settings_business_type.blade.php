{{-- Business Type Settings Section --}}
<div class="pos-tab-content">
    <h3 class="tw-font-bold tw-text-lg tw-mb-4">
        <i class="fa fa-building tw-mr-2"></i> Business Type
        <small class="tw-text-gray-500">Configure your business type to customize the POS experience</small>
    </h3>

    <div class="row">
        {{-- Current Business Type Display --}}
        <div class="col-sm-12">
            <div class="tw-bg-cyan-50 tw-border tw-border-cyan-200 tw-rounded-lg tw-p-4 tw-mb-6">
                <div class="tw-flex tw-items-center">
                    <i class="fa fa-info-circle tw-text-cyan-600 tw-mr-3 tw-text-lg"></i>
                    <div>
                        <strong>Current Business Type:</strong> 
                        <span class="tw-text-cyan-700 tw-font-bold" id="current_business_type_label">
                            {{ ucfirst($business->business_type ?? 'Not Set') }}
                        </span>
                        <br>
                        <small class="tw-text-gray-600">
                            This determines which POS layout, modules, and features are available. 
                            Changing this will update your POS app automatically.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Business Type Cards --}}
        <div class="col-sm-12">
            <div class="row" id="business_type_selector">
                @php
                    $businessTypes = [
                        'saloon' => ['label' => 'Saloon & Spa', 'description' => 'Hair salon, beauty spa, wellness center with appointment booking', 'icon' => 'fa-spa', 'color' => '#E91E63'],
                        'repair' => ['label' => 'Repair Shop', 'description' => 'Electronics, mobile, appliance repair with job tracking', 'icon' => 'fa-tools', 'color' => '#FF9800'],
                        'restaurant' => ['label' => 'Restaurant & Kitchen', 'description' => 'Restaurant, café, cloud kitchen with table management and KDS', 'icon' => 'fa-utensils', 'color' => '#4CAF50'],
                        'retail' => ['label' => 'Retail Store', 'description' => 'General retail, fashion, accessories, grocery with barcode scanning', 'icon' => 'fa-store', 'color' => '#2196F3'],
                        'wholesale' => ['label' => 'Wholesale & Distribution', 'description' => 'Bulk selling, multi-location, tiered pricing, warehouse management', 'icon' => 'fa-warehouse', 'color' => '#795548'],
                        'clinic' => ['label' => 'Clinic & Professional', 'description' => 'Medical clinic, dental, consulting with appointment management', 'icon' => 'fa-user-md', 'color' => '#00BCD4'],
                        'other' => ['label' => 'Other Business', 'description' => 'Custom setup - configure modules manually', 'icon' => 'fa-cog', 'color' => '#607D8B'],
                    ];
                @endphp

                @foreach($businessTypes as $key => $type)
                    <div class="col-sm-4 col-md-3">
                        <div class="business-type-card {{ ($business->business_type ?? 'retail') == $key ? 'active' : '' }}" 
                             data-type="{{ $key }}"
                             style="border-color: {{ $type['color'] }}20; {{ ($business->business_type ?? 'retail') == $key ? 'border-color: ' . $type['color'] . '; background-color: ' . $type['color'] . '10;' : '' }}">
                            
                            @if(($business->business_type ?? 'retail') == $key)
                                <div class="tw-absolute tw-top-2 tw-right-2">
                                    <span class="badge" style="background-color: {{ $type['color'] }}; color: white;">
                                        <i class="fa fa-check"></i> Active
                                    </span>
                                </div>
                            @endif
                            
                            <div class="text-center">
                                <i class="fa {{ $type['icon'] }} tw-text-3xl tw-mb-3" style="color: {{ $type['color'] }}"></i>
                                <h4 class="tw-font-bold tw-text-sm tw-mb-2" style="color: {{ $type['color'] }}">{{ $type['label'] }}</h4>
                                <p class="tw-text-xs tw-text-gray-500 tw-mb-3">{{ $type['description'] }}</p>
                            </div>
                        </div>
                        <input type="radio" name="business_type" value="{{ $key }}" 
                               {{ ($business->business_type ?? 'retail') == $key ? 'checked' : '' }}
                               class="hidden">
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Modules enabled for selected type --}}
        <div class="col-sm-12 tw-mt-6">
            <div class="tw-bg-gray-50 tw-rounded-lg tw-p-4">
                <h4 class="tw-font-bold tw-mb-3">
                    <i class="fa fa-puzzle-piece tw-mr-2"></i> Enabled Modules
                </h4>
                <div class="row" id="modules_display">
                    @php
                        $enabledModules = is_string($business->enabled_modules ?? '') 
                        ? json_decode($business->enabled_modules ?? '{}', true) 
                        : ($business->enabled_modules ?? []);
                    @endphp
                    
                    @php
                        // Handle both formats: indexed array ["booking", ...] or assoc ["booking" => true, ...]
                        $hasBooking = in_array('booking', $enabledModules) || ($enabledModules['booking'] ?? false);
                        $hasRepair = in_array('repair', $enabledModules) || ($enabledModules['repair'] ?? false);
                        $hasKitchen = in_array('kitchen', $enabledModules) || ($enabledModules['kitchen'] ?? false);
                        $hasWholesale = in_array('wholesale', $enabledModules) || ($enabledModules['wholesale'] ?? false);
                    @endphp
                    <div class="col-sm-3">
                        <div class="tw-flex tw-items-center tw-mb-2">
                            <span class="badge {{ $hasBooking ? 'badge-success' : 'badge-default' }} tw-mr-2">
                                {{ $hasBooking ? 'ON' : 'OFF' }}
                            </span>
                            <span class="tw-text-sm">Bookings / Appointments</span>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="tw-flex tw-items-center tw-mb-2">
                            <span class="badge {{ $hasRepair ? 'badge-success' : 'badge-default' }} tw-mr-2">
                                {{ $hasRepair ? 'ON' : 'OFF' }}
                            </span>
                            <span class="tw-text-sm">Repair Tracking</span>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="tw-flex tw-items-center tw-mb-2">
                            <span class="badge {{ $hasKitchen ? 'badge-success' : 'badge-default' }} tw-mr-2">
                                {{ $hasKitchen ? 'ON' : 'OFF' }}
                            </span>
                            <span class="tw-text-sm">Kitchen Display</span>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="tw-flex tw-items-center tw-mb-2">
                            <span class="badge {{ $hasWholesale ? 'badge-success' : 'badge-default' }} tw-mr-2">
                                {{ $hasWholesale ? 'ON' : 'OFF' }}
                            </span>
                            <span class="tw-text-sm">Wholesale Mode</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .business-type-card {
        position: relative;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        cursor: pointer;
        transition: all 0.2s ease;
        background: white;
    }
    .business-type-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .business-type-card.active {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
</style>

<script type="text/javascript">
    function initBusinessTypeCards() {
        if (typeof $ === 'undefined') {
            setTimeout(initBusinessTypeCards, 100);
            return;
        }
        // Clean up server-rendered badges on non-active cards
        $('.business-type-card:not(.active) .tw-absolute.tw-top-2.tw-right-2').remove();
        var typeModules = {
            'saloon': {'Bookings': true, 'Repair': false, 'Kitchen': false, 'Wholesale': false},
            'repair': {'Bookings': false, 'Repair': true, 'Kitchen': false, 'Wholesale': false},
            'restaurant': {'Bookings': false, 'Repair': false, 'Kitchen': true, 'Wholesale': false},
            'retail': {'Bookings': false, 'Repair': false, 'Kitchen': false, 'Wholesale': false},
            'wholesale': {'Bookings': false, 'Repair': false, 'Kitchen': false, 'Wholesale': true},
            'clinic': {'Bookings': true, 'Repair': false, 'Kitchen': false, 'Wholesale': false},
            'other': {'Bookings': false, 'Repair': false, 'Kitchen': false, 'Wholesale': false}
        };
        var typeLabels = {
            'saloon': 'Saloon & Spa', 'repair': 'Repair Shop', 'restaurant': 'Restaurant & Kitchen',
            'retail': 'Retail Store', 'wholesale': 'Wholesale & Distribution',
            'clinic': 'Clinic & Professional', 'other': 'Other Business'
        };
        var typeColors = {
            'saloon': '#E91E63', 'repair': '#FF9800', 'restaurant': '#4CAF50',
            'retail': '#2196F3', 'wholesale': '#795548', 'clinic': '#00BCD4', 'other': '#607D8B'
        };

        $(document).off('click.businessType').on('click.businessType', '.business-type-card', function() {
            var type = $(this).data('type');
            if (!type) return;

            $('.business-type-card').removeClass('active').css({
                'border-color': '#e5e7eb',
                'background-color': 'white'
            });
            $('.business-type-card .tw-active-badge').remove();
            $('.business-type-card .tw-absolute.tw-top-2.tw-right-2').remove();

            $(this).addClass('active').css({
                'border-color': typeColors[type] || '#2196F3',
                'background-color': (typeColors[type] || '#2196F3') + '10'
            });
            $(this).prepend('<div class="tw-active-badge" style="position:absolute;top:8px;right:8px;"><span class="badge" style="background-color:' + (typeColors[type] || '#2196F3') + ';color:white;"><i class="fa fa-check"></i> Active</span></div>');

            $('input[name="business_type"]').prop('checked', false);
            $('input[name="business_type"][value="' + type + '"]').prop('checked', true);

            $('#current_business_type_label').text(typeLabels[type] || type);

            var mods = typeModules[type] || {};
            $('#modules_display .badge').each(function() {
                var moduleName = $(this).closest('.col-sm-3').find('.tw-text-sm').text().trim();
                var isOn = false;
                if (moduleName.indexOf('Bookings') >= 0) isOn = !!mods['Bookings'];
                else if (moduleName.indexOf('Repair') >= 0) isOn = !!mods['Repair'];
                else if (moduleName.indexOf('Kitchen') >= 0) isOn = !!mods['Kitchen'];
                else if (moduleName.indexOf('Wholesale') >= 0) isOn = !!mods['Wholesale'];

                if (isOn) {
                    $(this).removeClass('badge-default').addClass('badge-success').text('ON');
                } else {
                    $(this).removeClass('badge-success').addClass('badge-default').text('OFF');
                }
            });

            if (typeof toastr !== 'undefined') {
                toastr.info('Business type set to ' + (typeLabels[type] || type) + '. Click "Update Settings" to save.');
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBusinessTypeCards);
    } else {
        initBusinessTypeCards();
    }
</script>
