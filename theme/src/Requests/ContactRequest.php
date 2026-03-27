<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
        ];

        if( !app()->environment('local') && config('services.hcaptcha.secret') ) {
            $rules['h-captcha-response'] = ['required', new \Aimeos\Cms\Rules\Hcaptcha];
        }

        return $rules;
    }
}
