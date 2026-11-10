<?php

namespace App\Http\Controllers;

use App\Business;
use App\Transaction;
use App\Utils\TransactionUtil;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MyFatoorah\Library\API\Payment\MyFatoorahPayment;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentEmbedded;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;
use MyFatoorah\Library\MyFatoorah;

class MyFatoorahController extends Controller
{
    /**
     * @var array
     */
    public $mfConfig = [];

    /**
     * @var TransactionUtil
     */
    protected $transactionUtil;

    /**
     * Initiate MyFatoorah Configuration
     */
    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->mfConfig = [
            'apiKey'      => config('myfatoorah.api_key'),
            'isTest'      => (bool) config('myfatoorah.test_mode'),
            'countryCode' => config('myfatoorah.country_iso', 'KWT'),
        ];
    }

    /**
     * Redirect to MyFatoorah Invoice URL or initiate checkout
     */
    public function index(Request $request)
    {
        try {
            $paymentMethodId = $request->input('pmid', 0);
            $sessionId = $request->input('sid');
            $transactionId = $request->input('transaction_id') ?: $request->input('package_id');

            if (empty($transactionId)) {
                throw new Exception('Transaction ID is required.');
            }

            $transaction = Transaction::with(['contact', 'business'])->find($transactionId);
            if (!$transaction) {
                throw new Exception('Transaction not found in system.');
            }

            $business = $transaction->business;

            // Dynamically apply per-business gateway credentials with fallback to config/.env
            $posSettings = !empty($business->pos_settings) ? json_decode($business->pos_settings, true) : [];
            $apiKey = !empty($posSettings['myfatoorah_api_key']) ? $posSettings['myfatoorah_api_key'] : config('myfatoorah.api_key');
            $isTest = isset($posSettings['myfatoorah_test_mode']) ? ((string) $posSettings['myfatoorah_test_mode'] === '1') : (bool) config('myfatoorah.test_mode');
            $countryIso = !empty($posSettings['myfatoorah_country_iso']) ? $posSettings['myfatoorah_country_iso'] : config('myfatoorah.country_iso', 'KWT');

            $this->mfConfig = [
                'apiKey'      => $apiKey,
                'isTest'      => $isTest,
                'countryCode' => $countryIso,
            ];

            $customerName = optional($transaction->contact)->name ?: ($business->name ?? 'Customer');
            $customerEmail = optional($transaction->contact)->email ?: ($business->email ?? 'info@vendify.local');
            $amount = $request->input('amount') ?: $transaction->final_total;
            $currency = $business->currency->code ?? config('myfatoorah.country_iso') === 'KWT' ? 'KWD' : 'SAR';
            $language = $request->input('language', 'en');

            $payload = [
                'CustomerName'       => $customerName,
                'InvoiceValue'       => (float) $amount,
                'DisplayCurrencyIso' => $currency,
                'CustomerEmail'      => $customerEmail,
                'CallBackUrl'        => route('myfatoorah_callback'),
                'ErrorUrl'           => route('myfatoorah_callback'),
                'Language'           => $language,
                'CustomerReference'  => (string) $transaction->id,
                'UserDefinedField'   => json_encode([
                    'business_id'    => $transaction->business_id,
                    'transaction_id' => $transaction->id,
                    'user_id'        => $request->input('user_id', $transaction->created_by),
                ]),
                'SourceInfo'         => 'Vendify ERP - MyFatoorah Gateway',
            ];

            $mfObj = new MyFatoorahPayment($this->mfConfig);
            $payment = $mfObj->getInvoiceURL($payload, $paymentMethodId, $transaction->id, $sessionId);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success'     => true,
                    'invoice_id'  => $payment['invoiceId'] ?? null,
                    'payment_url' => $payment['invoiceURL'] ?? null,
                ]);
            }

            return redirect($payment['invoiceURL']);
        } catch (Exception $ex) {
            Log::error('MyFatoorah Initiation Failed: ' . $ex->getMessage());
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $ex->getMessage(),
                ], 400);
            }
            return response()->json(['IsSuccess' => 'false', 'Message' => $ex->getMessage()], 400);
        }
    }

    /**
     * Get MyFatoorah Payment Information & Reconcile Transaction
     */
    public function callback(Request $request)
    {
        try {
            $paymentId = $request->input('paymentId');
            if (empty($paymentId)) {
                throw new Exception('Payment ID is missing in callback.');
            }

            $mfObj = new MyFatoorahPaymentStatus($this->mfConfig);
            $data = $mfObj->getPaymentStatus($paymentId, 'PaymentId');

            $transactionId = $data->CustomerReference ?? null;
            $invoiceStatus = $data->InvoiceStatus ?? '';
            $paidAmount = $data->InvoiceValue ?? 0;
            $paymentMethod = strtolower($data->PaymentMethod ?? 'knet');

            // Reconcile if paid
            if ($invoiceStatus === 'Paid' && !empty($transactionId)) {
                $this->reconcileTransaction($transactionId, $paidAmount, $paymentMethod, $paymentId);
            }

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success'        => $invoiceStatus === 'Paid',
                    'status'         => $invoiceStatus,
                    'transaction_id' => $transactionId,
                    'amount'         => $paidAmount,
                    'payment_id'     => $paymentId,
                    'method'         => $paymentMethod,
                ]);
            }

            return view('myfatoorah.status', compact('data', 'invoiceStatus', 'transactionId', 'paymentId'));
        } catch (Exception $ex) {
            Log::error('MyFatoorah Callback Error: ' . $ex->getMessage());
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $ex->getMessage(),
                ], 400);
            }
            return response()->json(['success' => false, 'message' => $ex->getMessage()]);
        }
    }

    /**
     * Instant Payment Notification (IPN) Webhook handler
     */
    public function webhook(Request $request)
    {
        try {
            $secretKey = config('myfatoorah.webhook_secret_key');
            $mfSignature = $request->header('MyFatoorah-Signature');

            if (empty($secretKey) || empty($mfSignature)) {
                Log::warning('MyFatoorah webhook rejected: Missing secret key or signature header.');
                return response('Unauthorized', 401);
            }

            $body = $request->getContent();
            $input = json_decode($body, true);

            if (empty($input['Data']) || empty($input['EventType']) || $input['EventType'] != 1) {
                return response('Ignored event', 200);
            }

            if (!MyFatoorah::isSignatureValid($input['Data'], $secretKey, $mfSignature, $input['EventType'])) {
                Log::warning('MyFatoorah webhook rejected: Invalid signature.');
                return response('Invalid Signature', 400);
            }

            $data = $input['Data'];
            $transactionId = $data['CustomerReference'] ?? null;
            $status = $data['TransactionStatus'] ?? '';
            $paidAmount = $data['InvoiceValue'] ?? 0;
            $paymentMethod = strtolower($data['PaymentMethod'] ?? 'knet');
            $paymentId = $data['PaymentId'] ?? ($data['InvoiceId'] ?? 'webhook');

            if ($status === 'SUCCESS' && !empty($transactionId)) {
                $this->reconcileTransaction($transactionId, $paidAmount, $paymentMethod, $paymentId);
            }

            return response()->json(['success' => true, 'status' => 'Reconciled']);
        } catch (Exception $ex) {
            Log::error('MyFatoorah Webhook Failed: ' . $ex->getMessage());
            return response()->json(['success' => false, 'message' => $ex->getMessage()], 400);
        }
    }

    /**
     * Internal atomic reconciliation logic
     */
    protected function reconcileTransaction($transactionId, $paidAmount, $paymentMethod, $gatewayRef)
    {
        DB::beginTransaction();
        try {
            $transaction = Transaction::lockForUpdate()->find($transactionId);
            if (!$transaction) {
                DB::rollBack();
                Log::warning("Reconciliation skipped: Transaction {$transactionId} not found.");
                return;
            }

            // Check if already paid
            if ($transaction->payment_status === 'paid') {
                DB::commit();
                return;
            }

            // Map method to Vendify supported GCC payment types
            $methodMapped = 'card';
            $pmLower = strtolower($paymentMethod);
            if (str_contains($pmLower, 'knet')) {
                $methodMapped = 'knet';
            } elseif (str_contains($pmLower, 'mada')) {
                $methodMapped = 'mada';
            } elseif (str_contains($pmLower, 'apple')) {
                $methodMapped = 'apple_pay';
            } elseif (str_contains($pmLower, 'stc')) {
                $methodMapped = 'stc_pay';
            }

            $payments = [
                [
                    'amount'      => (float) $paidAmount,
                    'method'      => $methodMapped,
                    'paid_on'     => now()->toDateTimeString(),
                    'created_by'  => $transaction->created_by,
                    'note'        => "Automated reconciliation via MyFatoorah (Ref: {$gatewayRef})",
                    'account_id'  => null,
                ]
            ];

            $this->transactionUtil->createOrUpdatePaymentLines($transaction, $payments, $transaction->business_id, $transaction->created_by, false);
            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

            // Audit log
            activity()
                ->performedOn($transaction)
                ->causedBy($transaction->created_by)
                ->withProperties([
                    'gateway'       => 'myfatoorah',
                    'gateway_ref'   => $gatewayRef,
                    'method'        => $methodMapped,
                    'amount'        => $paidAmount,
                ])
                ->log("Online payment reconciled for Invoice #{$transaction->invoice_no}");

            DB::commit();
            Log::info("Transaction {$transactionId} successfully reconciled with {$methodMapped} for {$paidAmount}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to reconcile transaction {$transactionId}: " . $e->getMessage());
            throw $e;
        }
    }
}
