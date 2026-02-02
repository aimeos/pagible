<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validation rules for this package.
    |
    */

    'invalid' => [
        'title' => 'Unprocessable Entity',
        'detail' => 'The document was well-formed but contains semantic errors.',
        'code' => '',
    ],

    'resource_invalid' => [
        'title' => 'Unprocessable Entity',
        'detail' => 'The document was well-formed but contains semantic errors.',
        'code' => '',
    ],

    'query_invalid' => [
        'title' => 'Invalid Query Parameter',
        'detail' => 'The request query parameters are invalid.',
        'code' => '',
    ],

    'delete_invalid' => [
        'title' => 'Not Deletable',
        'detail' => 'The resource cannot be deleted.',
        'code' => '',
    ],

    'allowed_field_sets' => [
        'default' => 'Sparse field sets must contain only allowed ones.',
        'singular' => 'Sparse field set :values is not allowed.',
        'plural' => 'Sparse field sets :values are not allowed.',

        'unrecognised' => [
            'singular' => 'Resource type :types is not recognised.',
            'plural' => 'Resource types :types are not recognised.',
        ],
    ],

    'allowed_filter_parameters' => [
        'default' => 'Filter parameters must contain only allowed ones.',
        'singular' => 'Filter parameter :values is not allowed.',
        'plural' => 'Filter parameters :values are not allowed.',
    ],

    'allowed_include_paths' => [
        'default' => 'Include paths must contain only allowed ones.',
        'singular' => 'Include path :values is not allowed.',
        'plural' => 'Include paths :values are not allowed.',
    ],

    'allowed_sort_parameters' => [
        'default' => 'Sort parameters must contain only allowed ones.',
        'singular' => 'Sort parameter :values is not allowed.',
        'plural' => 'Sort parameters :values are not allowed.',
    ],

    'allowed_page_parameters' => [
        'default' => 'Page parameters must contain only allowed ones.',
        'singular' => 'Page parameter :values is not allowed.',
        'plural' => 'Page parameters :values are not allowed.',
    ],

    'allowed_countable_fields' => [
        'default' => 'Count fields must contain only allowed ones.',
        'singular' => 'Field :values is not countable.',
        'plural' => 'Fields :values are not countable.',
    ],

    'boolean_string' => 'The :attribute field must be true or false.',

    'client_id' => 'The :attribute format is invalid.',

    'date_time_iso_8601' => 'The :attribute is not a valid ISO 8601 date and time.',

    'json_boolean' => 'The :attribute field must be a boolean.',

    'json_integer' => 'The :attribute field must be an integer.',

    'json_number' => 'The :attribute field must be a number.',

    'json_array' => 'The :attribute field must be an array.',

    'json_object' => 'The :attribute field must be an object.',

    'parameter_not_supported' => 'Parameter :name is not allowed.',

    'has_one' => 'The :attribute field must be a to-one relationship containing :types resources.',

    'has_many' => 'The :attribute field must be a to-many relationship containing :types resources.',
];
