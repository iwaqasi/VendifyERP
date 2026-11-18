@extends('layouts.app')
@section('title', 'Kuwait MOCI XBRL & VAT 2027 Financial Audit')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header" style="padding-bottom: 5px;">
    <div class="row">
        <div class="col-sm-8">
            <h1 style="margin: 0; font-weight: 700; color: #1e293b; font-size: 24px;">
                <i class="fa fa-university text-primary" style="margin-right: 8px;"></i>
                Kuwait MOCI XBRL & VAT 2027 Audit
                <small style="color: #64748b; font-size: 14px; font-weight: 500;">IFRS for SMEs & Qayd Taxonomy Reporting</small>
            </h1>
        </div>
        <div class="col-sm-4 text-right">
            <a href="{{ route('reports.kuwait_xbrl.json', ['start_date' => $start_date, 'end_date' => $end_date]) }}" class="btn btn-sm btn-primary" style="border-radius: 8px; font-weight: 600;">
                <i class="fa fa-code"></i> Download XBRL JSON
            </a>
            <a href="{{ route('reports.kuwait_xbrl.csv', ['start_date' => $start_date, 'end_date' => $end_date]) }}" class="btn btn-sm btn-success" style="border-radius: 8px; font-weight: 600; margin-left: 5px;">
                <i class="fa fa-file-excel-o"></i> Download MOCI CSV
            </a>
        </div>
    </div>
</section>

<!-- Main content -->
<section class="content">

    {{-- Regulatory Status Banner --}}
    <div class="row">
        <div class="col-sm-12">
            <div class="box box-solid" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div class="box-body" style="padding: 16px 20px;">
                    <div class="row" style="display: flex; align-items: center; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6" style="border-right: 1px solid #f1f5f9;">
                            <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Business Commercial Registration (CR)</span>
                            <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                @if(!empty($business->kuwait_cr_number))
                                    <i class="fa fa-check-circle text-success"></i> {{ $business->kuwait_cr_number }}
                                @else
                                    <span class="text-danger" style="font-size: 13px;"><i class="fa fa-warning"></i> Not Configured</span>
                                    <a href="{{ action([\App\Http\Controllers\BusinessController::class, 'getBusinessSettings']) }}#tax_settings" class="btn btn-xs btn-link" style="padding: 0;">Add</a>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6" style="border-right: 1px solid #f1f5f9;">
                            <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">MOCI Commercial License No.</span>
                            <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                @if(!empty($business->kuwait_moci_license))
                                    <i class="fa fa-certificate text-primary"></i> {{ $business->kuwait_moci_license }}
                                @else
                                    <span class="text-muted" style="font-size: 13px;">Optional / Not set</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6" style="border-right: 1px solid #f1f5f9;">
                            <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">PACI Building / Civil ID</span>
                            <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                @if(!empty($business->kuwait_paci_number))
                                    <i class="fa fa-map-marker text-danger"></i> {{ $business->kuwait_paci_number }}
                                @else
                                    <span class="text-muted" style="font-size: 13px;">Optional / Not set</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Kuwait VAT & GCC QR Readiness</span>
                            <div style="font-size: 14px; font-weight: 800; color: #10b981; margin-top: 2px;">
                                <i class="fa fa-shield"></i> 5% VAT Ready (2027 Mandate)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="row">
        <div class="col-sm-12">
            <div class="box box-primary" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
                <div class="box-body" style="padding: 14px 20px;">
                    <form method="GET" action="{{ route('reports.kuwait_xbrl') }}" class="form-inline">
                        <div class="form-group" style="margin-right: 15px;">
                            <label style="margin-right: 8px; font-weight: 600; color: #475569;">Audit Period Start:</label>
                            <input type="date" name="start_date" class="form-control input-sm" value="{{ $start_date }}" style="border-radius: 6px;">
                        </div>
                        <div class="form-group" style="margin-right: 15px;">
                            <label style="margin-right: 8px; font-weight: 600; color: #475569;">Audit Period End:</label>
                            <input type="date" name="end_date" class="form-control input-sm" value="{{ $end_date }}" style="border-radius: 6px;">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 6px; font-weight: 600;">
                            <i class="fa fa-filter"></i> Run Audit Filter
                        </button>
                        <span class="pull-right text-muted" style="margin-top: 5px; font-size: 12px;">
                            Standard: <strong style="color: #0f172a;">IFRS for SMEs (Kuwait MOCI Qayd 2027)</strong>
                        </span>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Executive Financial Metric Cards --}}
    <div class="row">
        {{-- Gross Revenue --}}
        <div class="col-md-3 col-sm-6">
            <div class="info-box" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #ffffff;">
                <span class="info-box-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; border-radius: 12px 0 0 12px;">
                    <i class="fa fa-shopping-bag"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b; font-size: 11px;">GROSS REVENUE</span>
                    <span class="info-box-number" style="font-size: 20px; font-weight: 800; color: #0f172a;">
                        KD {{ number_format($reportData['taxonomy']['RevenueFromSaleOfGoods']['amount'], 3) }}
                    </span>
                    <div class="progress" style="height: 3px; margin: 4px 0;">
                        <div class="progress-bar" style="width: 100%; background: #3b82f6;"></div>
                    </div>
                    <span class="progress-description" style="font-size: 11px; color: #94a3b8;">
                        Before discounts & returns
                    </span>
                </div>
            </div>
        </div>

        {{-- Net Revenue --}}
        <div class="col-md-3 col-sm-6">
            <div class="info-box" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #ffffff;">
                <span class="info-box-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border-radius: 12px 0 0 12px;">
                    <i class="fa fa-line-chart"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b; font-size: 11px;">NET REVENUE</span>
                    <span class="info-box-number" style="font-size: 20px; font-weight: 800; color: #0f172a;">
                        KD {{ number_format($reportData['taxonomy']['NetRevenue']['amount'], 3) }}
                    </span>
                    <div class="progress" style="height: 3px; margin: 4px 0;">
                        <div class="progress-bar" style="width: 100%; background: #10b981;"></div>
                    </div>
                    <span class="progress-description" style="font-size: 11px; color: #94a3b8;">
                        Net of discounts & refunds
                    </span>
                </div>
            </div>
        </div>

        {{-- Cost of Sales (COGS) --}}
        <div class="col-md-3 col-sm-6">
            <div class="info-box" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #ffffff;">
                <span class="info-box-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; border-radius: 12px 0 0 12px;">
                    <i class="fa fa-cubes"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b; font-size: 11px;">COST OF SALES (COGS)</span>
                    <span class="info-box-number" style="font-size: 20px; font-weight: 800; color: #0f172a;">
                        KD {{ number_format($reportData['taxonomy']['CostOfSales']['amount'], 3) }}
                    </span>
                    <div class="progress" style="height: 3px; margin: 4px 0;">
                        <div class="progress-bar" style="width: 100%; background: #f59e0b;"></div>
                    </div>
                    <span class="progress-description" style="font-size: 11px; color: #94a3b8;">
                        Total purchase acquisition cost
                    </span>
                </div>
            </div>
        </div>

        {{-- Gross Profit --}}
        <div class="col-md-3 col-sm-6">
            <div class="info-box" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #ffffff;">
                <span class="info-box-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: #fff; border-radius: 12px 0 0 12px;">
                    <i class="fa fa-dollar"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b; font-size: 11px;">GROSS PROFIT (MARGIN)</span>
                    <span class="info-box-number" style="font-size: 20px; font-weight: 800; color: #0f172a;">
                        KD {{ number_format($reportData['taxonomy']['GrossProfit']['amount'], 3) }}
                        <small style="font-size: 12px; color: #10b981; font-weight: bold;">({{ $reportData['summary']['gross_profit_margin_pct'] }}%)</small>
                    </span>
                    <div class="progress" style="height: 3px; margin: 4px 0;">
                        <div class="progress-bar" style="width: 100%; background: #8b5cf6;"></div>
                    </div>
                    <span class="progress-description" style="font-size: 11px; color: #94a3b8;">
                        Net Revenue minus COGS
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Second Metric Tier: Tax & Liquidity --}}
    <div class="row">
        {{-- VAT Collected --}}
        <div class="col-md-3 col-sm-6">
            <div class="info-box" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
                <span class="info-box-icon" style="background: #0ea5e9; color: #fff; border-radius: 12px 0 0 12px;">
                    <i class="fa fa-percent"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b; font-size: 11px;">OUTPUT VAT COLLECTED</span>
                    <span class="info-box-number" style="font-size: 18px; font-weight: 800; color: #0f172a;">
                        KD {{ number_format($reportData['taxonomy']['ValueAddedTaxPayable']['amount'], 3) }}
                    </span>
                    <span class="progress-description" style="font-size: 11px; color: #94a3b8;">
                        Output Tax Payable
                    </span>
                </div>
            </div>
        </div>

        {{-- KNET Settlements --}}
        <div class="col-md-3 col-sm-6">
            <div class="info-box" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
                <span class="info-box-icon" style="background: #ec4899; color: #fff; border-radius: 12px 0 0 12px;">
                    <i class="fa fa-credit-card"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b; font-size: 11px;">KNET & CARD SETTLEMENTS</span>
                    <span class="info-box-number" style="font-size: 18px; font-weight: 800; color: #0f172a;">
                        KD {{ number_format($reportData['taxonomy']['TradeAndOtherReceivables']['amount'], 3) }}
                    </span>
                    <span class="progress-description" style="font-size: 11px; color: #94a3b8;">
                        Digital Card Receivables
                    </span>
                </div>
            </div>
        </div>

        {{-- Cash on Hand --}}
        <div class="col-md-3 col-sm-6">
            <div class="info-box" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
                <span class="info-box-icon" style="background: #14b8a6; color: #fff; border-radius: 12px 0 0 12px;">
                    <i class="fa fa-money"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b; font-size: 11px;">CASH ON HAND</span>
                    <span class="info-box-number" style="font-size: 18px; font-weight: 800; color: #0f172a;">
                        KD {{ number_format($reportData['taxonomy']['CashOnHand']['amount'], 3) }}
                    </span>
                    <span class="progress-description" style="font-size: 11px; color: #94a3b8;">
                        Registers & Cash Equivalents
                    </span>
                </div>
            </div>
        </div>

        {{-- Stock Valuation --}}
        <div class="col-md-3 col-sm-6">
            <div class="info-box" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
                <span class="info-box-icon" style="background: #6366f1; color: #fff; border-radius: 12px 0 0 12px;">
                    <i class="fa fa-archive"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text" style="font-weight: 700; color: #64748b; font-size: 11px;">INVENTORIES AT COST</span>
                    <span class="info-box-number" style="font-size: 18px; font-weight: 800; color: #0f172a;">
                        KD {{ number_format($reportData['taxonomy']['Inventories']['amount'], 3) }}
                    </span>
                    <span class="progress-description" style="font-size: 11px; color: #94a3b8;">
                        Live Stock Valuation
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed Taxonomy Table --}}
    <div class="row">
        <div class="col-sm-12">
            <div class="box box-solid" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div class="box-header with-border" style="padding: 16px 20px;">
                    <h3 class="box-title" style="font-weight: 700; color: #0f172a; font-size: 16px;">
                        <i class="fa fa-list-alt text-primary" style="margin-right: 6px;"></i>
                        Kuwait MOCI Qayd IFRS Taxonomy Breakdown
                    </h3>
                    <div class="box-tools pull-right">
                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 600; padding: 5px 10px;">
                            Period: {{ $reportData['business']['period_start'] }} to {{ $reportData['business']['period_end'] }}
                        </span>
                    </div>
                </div>
                <div class="box-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" style="margin-bottom: 0;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                    <th style="padding: 12px 20px; font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase;">MOCI IFRS Taxonomy Concept</th>
                                    <th style="padding: 12px 20px; font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase;">Description & Mapping</th>
                                    <th style="padding: 12px 20px; font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; text-align: right;">Amount (KWD)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reportData['taxonomy'] as $key => $item)
                                <tr>
                                    <td style="padding: 12px 20px;">
                                        <code style="background: #f1f5f9; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">{{ $item['qayd_element'] }}</code>
                                    </td>
                                    <td style="padding: 12px 20px;">
                                        <strong style="color: #0f172a;">{{ $item['label'] }}</strong>
                                    </td>
                                    <td style="padding: 12px 20px; text-align: right; font-weight: 700; font-size: 14px; color: #0f172a;">
                                        KD {{ number_format($item['amount'], 3) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>

@endsection
