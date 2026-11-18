<?php
  
namespace App\Rules;
  
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReCaptcha implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (empty($value)) {
            return false;
        }

        $secret = config('constants.google_recaptcha_secret');
        if (empty($secret)) {
            // If secret is not set, allow or fallback
            return true;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            if ($response->successful()) {
                return (bool) ($response->json('success') === true);
            }

            Log::warning('Google reCAPTCHA verification returned non-200 status: ' . $response->status());
            return false;
        } catch (\Exception $e) {
            Log::error('Google reCAPTCHA HTTP error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('Please verify that you are not a robot (reCAPTCHA failed).');
    }
}
