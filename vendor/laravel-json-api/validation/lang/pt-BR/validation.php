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
        'title' => 'Entidade Não Processável',
        'detail' => 'O documento estava bem formado mas contém erros semânticos.',
        'code' => '',
    ],

    'resource_invalid' => [
        'title' => 'Entidade Não Processável',
        'detail' => 'O documento estava bem formado mas contém erros semânticos.',
        'code' => '',
    ],

    'query_invalid' => [
        'title' => 'Parâmetro de consulta inválido',
        'detail' => 'Os parâmetros de solicitação de consulta não são válidos.',
        'code' => '',
    ],

    'delete_invalid' => [
        'title' => 'Não é possível deletar',
        'detail' => 'O recurso não pode ser deletado.',
        'code' => '',
    ],

    'allowed_field_sets' => [
        'default' => 'Conjuntos de campos esparsos devem conter apenas os permitidos.',
        'singular' => 'Conjunto de campos esparsos :values no é permitido.',
        'plural' => 'Conjuntos de campos esparsos :values valores não são permitidos.',

        'unrecognised' => [
            'singular' => 'Tipo de recurso :types não foi reconhecido.',
            'plural' => 'Tipos de recursos :types não foram reconhecidos.',
        ],
    ],

    'allowed_filter_parameters' => [
        'default' => 'Parâmetros do filtro devem conter apenas os permitidos.',
        'singular' => 'O parâmetro de filtro :values não é permitido.',
        'plural' => 'Os parâmetros de filtro :values não são permitidos.',
    ],

    'allowed_include_paths' => [
        'default' => 'Rotas a serem incluídas devem conter apenas aquelas permitidas.',
        'singular' => 'A rota :values incluída não é permitida.',
        'plural' => 'As rotas :values incluídas ​​não são permitidas.',
    ],

    'allowed_sort_parameters' => [
        'default' => 'Parâmetros de ordenação devem conter apenas os permitidos.',
        'singular' => 'O parâmetro de ordenação :values não é permitido.',
        'plural' => 'Os parâmetros de ordenação :values não são permitidos.',
    ],

    'allowed_page_parameters' => [
        'default' => 'Parâmetros de página devem conter apenas os permitidos.',
        'singular' => 'O parâmetro de página :values não é permitido.',
        'plural' => 'Os parâmetros de página :values não são permitidos.',
    ],

    'allowed_countable_fields' => [
        'default' => 'Campos de contagem devem conter apenas os permitidos.',
        'singular' => 'O campo :values não pode ser contabilizado.',
        'plural' => 'Os campos :values não podem ser contabilizados.',
    ],

    'boolean_string' => 'O campo :attribute deve ser verdadeiro ou falso.',

    'client_id' => 'O formato de :attribute não é válido.',

    'date_time_iso_8601' => 'O campo :attribute não tem um valor de data/hora ISO 8601 válido.',

    'json_boolean' => 'O campo :attribute deve ser booleano.',

    'json_integer' => 'O campo :attribute deve ser um inteiro.',

    'json_number' => 'O campo :attribute deve ser numérico.',

    'json_array' => 'O campo :attribute deve ser um array.',

    'json_object' => 'O campo :attribute deve ser um objeto.',

    'parameter_not_supported' => 'O parâmetro :name não é permitido.',

    'has_one' => 'O campo :attribute deve ser uma relação do tipo um-para-um que contenha os recursos :types.',

    'has_many' => 'O campo :attribute deve ser uma relação do tipo um-para-muitos de muitos que contenha os recursos :types.',
];
