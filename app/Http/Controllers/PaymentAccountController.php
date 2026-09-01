<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentAccountController extends Controller
{
    /**
     * Display a listing of payment accounts.
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        
        $payment_accounts = \App\PaymentAccount::where('business_id', $business_id)
            ->get();

        return view('payment-account.index')->with(compact('payment_accounts'));
    }

    /**
     * Store a newly created payment account.
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $request->validate([
            'name' => 'required|string|max:255',
            'payment_method' => 'required|string',
        ]);

        \App\PaymentAccount::create([
            'business_id' => $business_id,
            'name' => $request->name,
            'payment_method' => $request->payment_method,
            'is_active' => 1,
        ]);

        return redirect('payment-account')->with('status', ['success' => 1, 'msg' => 'Payment account created.']);
    }

    /**
     * Update the specified payment account.
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        $account = \App\PaymentAccount::where('business_id', $business_id)->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'payment_method' => 'required|string',
        ]);

        $account->update($request->only(['name', 'payment_method']));

        return redirect('payment-account')->with('status', ['success' => 1, 'msg' => 'Payment account updated.']);
    }

    /**
     * Remove the specified payment account.
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $account = \App\PaymentAccount::where('business_id', $business_id)->findOrFail($id);
        $account->delete();

        return redirect('payment-account')->with('status', ['success' => 1, 'msg' => 'Payment account deleted.']);
    }
}
