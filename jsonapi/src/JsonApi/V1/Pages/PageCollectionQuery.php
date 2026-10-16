<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\JsonApi\V1\Pages;

use LaravelJsonApi\Validation\Rule as JsonApiRule;


class PageCollectionQuery extends PageQuery
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge( parent::rules(), [
            'page' => [
                'nullable',
                'array',
                JsonApiRule::page(),
            ],
            'page.number' => [
                'integer',
                'between:1,100',
            ],
            'page.size' => [
                'integer',
                'between:1,100',
            ],
        ] );
    }
}
