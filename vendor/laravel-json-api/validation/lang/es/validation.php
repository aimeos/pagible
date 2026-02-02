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
        'title' => 'Entidad No Procesable',
        'detail' => 'El documento estaba bien formado pero contiene errores semánticos.',
        'code' => '',
    ],

    'resource_invalid' => [
        'title' => 'Entidad No Procesable',
        'detail' => 'El documento estaba bien formado pero contiene errores semánticos.',
        'code' => '',
    ],

    'query_invalid' => [
        'title' => 'Parámetro de Consulta No Válido',
        'detail' => 'Los parámetros de la petición de consulta no son válidos.',
        'code' => '',
    ],

    'delete_invalid' => [
        'title' => 'No Se Puede Eliminar',
        'detail' => 'El recurso no puede ser eliminado.',
        'code' => '',
    ],

    'allowed_field_sets' => [
        'default' => 'Conjuntos de campos dispersos deben contener solo los permitidos.',
        'singular' => 'Conjunto de campos dispersos :values no está permitido.',
        'plural' => 'Conjuntos de campos dispersos :values no están permitidos.',

        'unrecognised' => [
            'singular' => 'Tipo de recurso :types no ha sido reconocido.',
            'plural' => 'Tipos de recursos :types no han sido reconocidos.',
        ],
    ],

    'allowed_filter_parameters' => [
        'default' => 'Parámetros de filtro deben contener solo los permitidos.',
        'singular' => 'El parámetro de fitro :values no está permido.',
        'plural' => 'Los parámetros de filtro :values no están permitidos.',
    ],

    'allowed_include_paths' => [
        'default' => 'Rutas a ser incluidas deben contener solo las permitidas.',
        'singular' => 'La ruta incluida :values no está permida.',
        'plural' => 'Las rutas incluidas :values no están permitidas.',
    ],

    'allowed_sort_parameters' => [
        'default' => 'Parámetros de ordenación deben contener solo los permitidos.',
        'singular' => 'El parámetro de ordenación :values no está permitido.',
        'plural' => 'Los parámetros de ordenación :values no están permitidos.',
    ],

    'allowed_page_parameters' => [
        'default' => 'Párametros de página deben contener solo los permitidos.',
        'singular' => 'El parámetro de pagina :values no está permitido.',
        'plural' => 'Los parámetros de página :values no están permitidos.',
    ],

    'allowed_countable_fields' => [
        'default' => 'Campos de conteo deben contener solo los permitidos.',
        'singular' => 'El campo :values no puede ser contado.',
        'plural' => 'Los campos :values no pueden ser contados.',
    ],

    'boolean_string' => 'El campo :attribute debe ser verdadero o falso.',

    'client_id' => 'El formato de :attribute no es válido.',

    'date_time_iso_8601' => 'El campo :attribute no tiene un valor de fecha/tiempo ISO 8601 válido.',

    'json_boolean' => 'El campo :attribute debe ser booleano.',

    'json_integer' => 'El campo :attribute debe ser un entero.',

    'json_number' => 'El campo :attribute debe ser númerico.',

    'json_array' => 'El campo :attribute debe ser una matriz.',

    'json_object' => 'El campo :attribute debe ser un objeto.',

    'parameter_not_supported' => 'El parámetro :name no está permitido.',

    'has_one' => 'El campo :attribute debe ser una relación a-uno que contenga los recursos :types.',

    'has_many' => 'El campo :attribute debe ser una relación a-muchos que contenga los recursos :types.',
];
