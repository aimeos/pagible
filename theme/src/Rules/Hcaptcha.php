<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;


/**
 * Validates the hCaptcha response.
 */
class Hcaptcha implements Rule
{
    public function passes($attribute, $value): bool
    {
        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(5)->post('https://hcaptcha.com/siteverify', [
                'secret'   => config('services.hcaptcha.secret'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
        } catch( ConnectionException $e ) {
            report( $e );
            return false;
        }

        return $response->ok() && $response->json('success') === true;
    }


    public function message(): string
    {
        return __( 'Captcha validation failed. Please try again.' );
    }
}
