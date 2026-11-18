<?php

namespace App\Http\Controllers;

use App\Business;
use App\Services\KuwaitXbrlService;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KuwaitComplianceController extends Controller
{
    protected KuwaitXbrlService $xbrlService;
    protected ModuleUtil $moduleUtil;

    public function __construct(KuwaitXbrlService $xbrlService, ModuleUtil $moduleUtil)
    {
        $this->xbrlService = $xbrlService;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display Kuwait MOCI XBRL & VAT 2027 Financial Audit Dashboard
     */
    public function index(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = Business::find($business_id);

        $start_date = $request->input('start_date', now()->startOfYear()->toDateString());
        $end_date = $request->input('end_date', now()->toDateString());

        $reportData = $this->xbrlService->computeMetrics($business_id, $start_date, $end_date);

        return view('report.kuwait_xbrl', compact('reportData', 'business', 'start_date', 'end_date'));
    }

    /**
     * Export Kuwait MOCI IFRS JSON Instance
     */
    public function exportJson(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $reportData = $this->xbrlService->computeMetrics($business_id, $start_date, $end_date);

        $filename = 'moci_kuwait_xbrl_' . ($reportData['business']['cr_number'] ?? 'business') . '_' . now()->format('Ymd_His') . '.json';

        return response()->json($reportData, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export Kuwait MOCI IFRS CSV Instance
     */
    public function exportCsv(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $reportData = $this->xbrlService->computeMetrics($business_id, $start_date, $end_date);
        $csv = $this->xbrlService->generateCsv($reportData);

        $filename = 'moci_kuwait_xbrl_' . ($reportData['business']['cr_number'] ?? 'business') . '_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
