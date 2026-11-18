<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Rules\ReCaptcha;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Number of failed login attempts before locking out the account.
     *
     * @var int
     */
    protected $maxAttempts = 5;

    /**
     * Number of minutes to lock out the account after max attempts.
     *
     * @var int
     */
    protected $decayMinutes = 2;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $moduleUtil;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil, ModuleUtil $moduleUtil)
    {
        $this->middleware('guest')->except('logout');
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Change authentication from email to username
     *
     * @return void
     */
    public function username()
    {
        return 'username';
    }

    public function logout()
    {
        $this->businessUtil->activityLog(auth()->user(), 'logout');

        request()->session()->flush();
        \Auth::logout();

        return redirect('/login');
    }

    /**
     * The user has been authenticated.
     * Check if the business is active or not.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        // Clear IP-level brute-force tracking upon successful login
        \Illuminate\Support\Facades\Cache::forget('login_ip_throttle_' . $request->ip());
        $request->session()->forget('security_captcha_answer');

        $this->businessUtil->activityLog($user, 'login', null, [], false, $user->business_id);

        if (! $user->business->is_active) {
            \Auth::logout();

            return redirect('/login')
              ->with(
                  'status',
                  ['success' => 0, 'msg' => __('lang_v1.business_inactive')]
              );
        } elseif ($user->status != 'active') {
            \Auth::logout();

            return redirect('/login')
              ->with(
                  'status',
                  ['success' => 0, 'msg' => __('lang_v1.user_inactive')]
              );
        } elseif (! $user->allow_login) {
            \Auth::logout();

            return redirect('/login')
                ->with(
                    'status',
                    ['success' => 0, 'msg' => __('lang_v1.login_not_allowed')]
                );
        } elseif (($user->user_type == 'user_customer') && ! $this->moduleUtil->hasThePermissionInSubscription($user->business_id, 'crm_module')) {
            \Auth::logout();

            return redirect('/login')
                ->with(
                    'status',
                    ['success' => 0, 'msg' => __('lang_v1.business_dont_have_crm_subscription')]
                );
        }
    }

    protected function redirectTo()
    {
        $user = \Auth::user();
        if (! $user->can('dashboard.data') && $user->can('sell.create')) {
            return '/pos/create';
        }

        if ($user->user_type == 'user_customer') {
            return 'contact/contact-dashboard';
        }

        return '/home';
    }

    public function validateLogin(Request $request)
    {
        // 1. Honeypot check: Automated brute force bots blindly populate hidden inputs
        if (!empty($request->input('login_extra_field'))) {
            \Illuminate\Support\Facades\Log::warning("Bot login attempt blocked via honeypot from IP: {$request->ip()}");
            abort(403, 'Automated request rejected.');
        }

        // 2. IP-level horizontal brute-force flood protection
        $ipKey = 'login_ip_throttle_' . $request->ip();
        $ipAttempts = \Illuminate\Support\Facades\Cache::get($ipKey, 0);
        if ($ipAttempts >= 15) {
            \Illuminate\Support\Facades\Log::alert("IP lockout triggered for {$request->ip()} due to {$ipAttempts} failed attempts.");
            throw \Illuminate\Validation\ValidationException::withMessages([
                $this->username() => [__('Too many failed login attempts from your IP. Please wait 5 minutes before trying again.')],
            ]);
        }

        // 3. CAPTCHA verification
        $rules = [
            $this->username() => 'required|string',
            'password' => 'required|string',
        ];

        if (config('constants.enable_recaptcha')) {
            if (!empty(config('constants.google_recaptcha_key'))) {
                $rules['g-recaptcha-response'] = ['required', new ReCaptcha];
            } else {
                $rules['security_captcha'] = [
                    'required',
                    function ($attribute, $value, $fail) use ($request) {
                        $expected = $request->session()->get('security_captcha_answer');
                        if (empty($expected) || (int) $value !== (int) $expected) {
                            $fail(__('Invalid security verification answer. Please try again.'));
                        }
                    },
                ];
            }
        }

        $this->validate($request, $rules);
    }

    /**
     * Get the failed login response instance and record security telemetry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        // Increment IP-level brute-force tracker
        $ipKey = 'login_ip_throttle_' . $request->ip();
        $attempts = \Illuminate\Support\Facades\Cache::get($ipKey, 0) + 1;
        \Illuminate\Support\Facades\Cache::put($ipKey, $attempts, now()->addMinutes(10));

        \Illuminate\Support\Facades\Log::warning("Failed login attempt for '{$request->input($this->username())}' from IP: {$request->ip()} (Attempt {$attempts})");

        // Clear answer so a fresh challenge is generated on reload
        $request->session()->forget('security_captcha_answer');

        throw \Illuminate\Validation\ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

}
