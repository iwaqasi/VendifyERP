<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\MyFatoorahController;
use App\Http\Requests\Api\V1\InitiatePaymentRequest;
use App\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlinePaymentController extends BaseApiController
{
    /**
     * Initiate an online payment for POS orders (KNET, Mada, Apple Pay, STC Pay)
     * POST /api/v1/payments/online/initiate
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $transaction = Transaction::where('business_id', $business_id)->findOrFail($request->transaction_id);

        if ($transaction->payment_status === 'paid') {
            return $this->errorResponse('Transaction is already fully paid.', 400);
        }

        // Delegate to MyFatoorah gateway
        $myFatoorah = app(MyFatoorahController::class);
        $initRequest = Request::create(
            route('myfatoorah_index'),
            'GET',
            [
                'transaction_id' => $transaction->id,
                'amount'         => $request->input('amount', $transaction->final_total),
                'language'       => $request->input('language', 'en'),
            ]
        );
        $initRequest->headers->set('Accept', 'application/json');

        $response = $myFatoorah->index($initRequest);
        if ($response instanceof JsonResponse) {
            return $response;
        }

        return $this->errorResponse('Unable to initialize online payment gateway.', 500);
    }

    /**
     * Check real-time payment status of a transaction
     * GET /api/v1/payments/online/status/{transaction_id}
     */
    public function status(Request $request, int $transaction_id): JsonResponse
    {
        $business_id = $this->getBusinessId($request);
        $transaction = Transaction::where('business_id', $business_id)
            ->with(['payment_lines'])
            ->findOrFail($transaction_id);

        $totalPaid = $transaction->payment_lines->where('is_return', 0)->sum('amount');
        $due = max(0, $transaction->final_total - $totalPaid);

        return $this->successResponse([
            'transaction_id' => $transaction->id,
            'invoice_no'     => $transaction->invoice_no,
            'payment_status' => $transaction->payment_status,
            'final_total'    => (float) $transaction->final_total,
            'total_paid'     => (float) $totalPaid,
            'total_due'      => (float) $due,
            'payment_lines'  => $transaction->payment_lines->map(function ($p) {
                return [
                    'id'          => $p->id,
                    'amount'      => (float) $p->amount,
                    'method'      => $p->method,
                    'paid_on'     => $p->paid_on,
                    'gateway'     => $p->gateway ?? null,
                    'note'        => $p->note,
                ];
            }),
        ]);
    }
}

