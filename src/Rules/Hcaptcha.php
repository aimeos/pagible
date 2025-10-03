<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;


/**
 * Validates the hCaptcha response.
 */
class Hcaptcha implements Rule
{
    public function passes($attribute, $value): bool
    {
        $response = Http::asForm()->post('https://hcaptcha.com/siteverify', [
            'secret'   => config('services.hcaptcha.secret'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        return $response->ok() && $response->json('success') === true;
    }


    public function message(): string
    {
        return 'Captcha validation failed. Please try again.';
    }
}
