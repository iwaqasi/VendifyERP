@extends('layouts.app')
@section('title', 'POS Dashboard')

@section('content')
<div style="padding: 20px;">
    <h1 style="font-size: 24px; font-weight: bold; margin-bottom: 20px;">
        <i class="fa fa-tachometer-alt"></i> POS Dashboard
    </h1>

    {{-- Business Info --}}
    <div style="background: linear-gradient(135deg, #00BCD4, #2196F3); padding: 20px; border-radius: 12px; color: white; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 22px; font-weight: bold; margin: 0;">{{ $business->name ?? 'Business' }}</h2>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">
                    Type: <strong>{{ ucfirst($business->business_type ?? 'Retail') }}</strong>
                    @if($currentShift)
                        | Shift Open Since: {{ \Carbon\Carbon::parse($currentShift->opened_at)->format('h:i A') }}
                    @endif
                </p>
            </div>
            <a href="{{ url('/pos') }}" style="background: white; color: #00BCD4; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold;">
                <i class="fa fa-desktop"></i> Open POS
            </a>
        </div>
    </div>

    {{-- Key Metrics --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #E5E7EB;">
            <div style="display: flex; align-items: center;">
                <div style="width: 48px; height: 48px; background: #E8F5E9; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa fa-dollar-sign" style="color: #4CAF50; font-size: 20px;"></i>
                </div>
                <div style="margin-left: 16px;">
                    <p style="margin: 0; color: #6B7280; font-size: 13px;">Today's Sales</p>
                    <p style="margin: 4px 0 0 0; font-size: 22px; font-weight: bold; color: #4CAF50;">{{ $currency_symbol }} {{ number_format($todaySales, 3) }}</p>
                    <p style="margin: 2px 0 0 0; color: #9CA3AF; font-size: 12px;">{{ $todayTransactions }} transactions</p>
                </div>
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #E5E7EB;">
            <div style="display: flex; align-items: center;">
                <div style="width: 48px; height: 48px; background: #E3F2FD; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa fa-chart-line" style="color: #2196F3; font-size: 20px;"></i>
                </div>
                <div style="margin-left: 16px;">
                    <p style="margin: 0; color: #6B7280; font-size: 13px;">This Month</p>
                    <p style="margin: 4px 0 0 0; font-size: 22px; font-weight: bold; color: #2196F3;">{{ $currency_symbol }} {{ number_format($monthSales, 3) }}</p>
                    <p style="margin: 2px 0 0 0; color: #9CA3AF; font-size: 12px;">{{ $monthTransactions }} transactions</p>
                </div>
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #E5E7EB;">
            <div style="display: flex; align-items: center;">
                <div style="width: 48px; height: 48px; background: #F3E5F5; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa fa-box" style="color: #9C27B0; font-size: 20px;"></i>
                </div>
                <div style="margin-left: 16px;">
                    <p style="margin: 0; color: #6B7280; font-size: 13px;">Products</p>
                    <p style="margin: 4px 0 0 0; font-size: 22px; font-weight: bold; color: #9C27B0;">{{ $totalProducts }}</p>
                    <p style="margin: 2px 0 0 0; color: #9CA3AF; font-size: 12px;">{{ $lowStockCount }} low stock</p>
                </div>
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #E5E7EB;">
            <div style="display: flex; align-items: center;">
                <div style="width: 48px; height: 48px; background: #FFF3E0; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa fa-users" style="color: #FF9800; font-size: 20px;"></i>
                </div>
                <div style="margin-left: 16px;">
                    <p style="margin: 0; color: #6B7280; font-size: 13px;">Customers</p>
                    <p style="margin: 4px 0 0 0; font-size: 22px; font-weight: bold; color: #FF9800;">{{ $totalCustomers }}</p>
                    <p style="margin: 2px 0 0 0; color: #9CA3AF; font-size: 12px;">{{ $newCustomersToday }} new today</p>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        {{-- Payment Breakdown --}}
        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #E5E7EB;">
            <h3 style="font-size: 16px; font-weight: bold; margin: 0 0 16px 0;">
                <i class="fa fa-credit-card"></i> Payment Breakdown
            </h3>
            @forelse($paymentBreakdown as $method => $data)
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #F3F4F6;">
                    <div style="display: flex; align-items: center;">
                        @if($method == 'cash')
                            <i class="fa fa-money-bill-wave" style="color: #4CAF50; margin-right: 12px;"></i>
                        @else
                            <i class="fa fa-credit-card" style="color: #2196F3; margin-right: 12px;"></i>
                        @endif
                        <span style="font-weight: 500;">{{ ucfirst(str_replace('_', ' ', $method)) }}</span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-weight: bold;">{{ $currency_symbol }} {{ number_format($data->total, 3) }}</span>
                        <span style="color: #9CA3AF; font-size: 12px; margin-left: 8px;">({{ $data->count }} txns)</span>
                    </div>
                </div>
            @empty
                <p style="color: #9CA3AF; text-align: center; padding: 20px;">No payments today</p>
            @endforelse
        </div>

        {{-- Recent Sales --}}
        <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #E5E7EB;">
            <h3 style="font-size: 16px; font-weight: bold; margin: 0 0 16px 0;">
                <i class="fa fa-receipt"></i> Recent Sales
            </h3>
            @forelse($recentSales as $sale)
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #F3F4F6;">
                    <div>
                        <span style="font-weight: 500;">{{ $sale->invoice_no ?? 'N/A' }}</span>
                        <br>
                        <small style="color: #9CA3AF;">{{ $sale->contact_name ?? 'Walk-in' }}</small>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-weight: bold; color: #4CAF50;">{{ $currency_symbol }} {{ number_format($sale->final_total, 3) }}</span>
                        <br>
                        <small style="color: #9CA3AF;">{{ \Carbon\Carbon::parse($sale->created_at)->format('h:i A') }}</small>
                    </div>
                </div>
            @empty
                <p style="color: #9CA3AF; text-align: center; padding: 20px;">No sales today</p>
            @endforelse
        </div>
    </div>

    {{-- Top Selling Products --}}
    <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #E5E7EB;">
        <h3 style="font-size: 16px; font-weight: bold; margin: 0 0 16px 0;">
            <i class="fa fa-trophy"></i> Top Selling Products (This Month)
        </h3>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #F9FAFB;">
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #E5E7EB;">#</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #E5E7EB;">Product</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #E5E7EB;">Qty Sold</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #E5E7EB;">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topProducts as $index => $product)
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #F3F4F6;">
                            <span style="background: {{ $index < 3 ? '#FFA726' : '#E5E7EB' }}; color: {{ $index < 3 ? 'white' : '#6B7280' }}; padding: 2px 8px; border-radius: 12px; font-size: 12px;">{{ $index + 1 }}</span>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #F3F4F6;">{{ $product->name }}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #F3F4F6;">{{ $product->total_qty }}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #F3F4F6; font-weight: bold;">{{ $currency_symbol }} {{ number_format($product->total_revenue, 3) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding: 20px; text-align: center; color: #9CA3AF;">No sales data</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
