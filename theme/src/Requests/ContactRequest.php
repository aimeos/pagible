<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Requests;

use Illuminate\Foundation\Http\FormRequest;


class ContactRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name'    => 'required|string|max:255',
            'email'   => 'required|email:rfc,dns',
            'message' => 'required|string|max:5000',
            'source'  => [
                'nullable',
                'url:http,https',
                'max:2048',
                function( string $attribute, mixed $value, \Closure $fail ) : void {
                    if( is_string( $value )
                        && strcasecmp( (string) parse_url( $value, PHP_URL_HOST ), $this->getHost() ) !== 0
                    ) {
                        $fail( __( 'The source page must use the current host.' ) );
                    }
                },
            ],
        ];

        if( !app()->environment('local') && config('services.hcaptcha.secret') ) {
            $rules['h-captcha-response'] = ['required', new \Aimeos\Cms\Rules\Hcaptcha];
        }

        return $rules;
    }
}
