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
    | JSON:API Specification Errors
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default translatable members
    | of JSON:API error objects. According to the JSON:API spec, the
    | `title` and `detail` members can be localized. In addition the `code`
    | member is also read from this package if you want to give the error
    | a specific code.
    |
    | Set any value to an empty string if you do not want the member to be
    | included in the error object.
    |
    | @see http://jsonapi.org/format/#errors
    */

    'json_empty' => [
        'title' => 'JSON inválido',
        'detail' => 'Esperava-se JSON para se descodificar.',
        'code' => '',
    ],

    'json_error' => [
        'title' => 'JSON inválido',
    ],

    'json_not_object' => [
        'title' => 'JSON inválido',
        'detail' => 'Esperava-se um JSON para se descodificar num objeto.',
        'code' => '',
    ],

    'member_required' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O membro :member é obrigatório.',
        'code' => '',
    ],

    'member_object_expected' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O membro :member deve ser um objeto.',
        'code' => '',
    ],

    'member_array_expected' => [
        'title' => 'Documento JSON: API não compatível',
        'detail' => 'O membro :member deve ser um array.',
        'code' => '',
    ],

    'member_identifier_expected' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O membro :member deve ser um identificador do recurso.',
        'code' => '',
    ],

    'member_string_expected' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O membro :member deve ser uma string.',
        'code' => '',
    ],

    'member_empty' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O membro :member não pode estar vazio.',
        'code' => '',
    ],

    'member_field_not_allowed' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O membro :member não pode ter um campo :field.',
        'code' => '',
    ],

    'member_field_not_supported' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O campo :field não é do tipo :type suportado.',
        'code' => '',
    ],

    'field_expects_to_one' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O campo :field deve ser uma relação do tipo para-um.',
        'code' => '',
    ],

    'field_expects_to_many' => [
        'title' => 'Documento JSON: API não compatível',
        'detail' => 'O campo :field deve ser uma relação do tipo para-muitos.',
        'code' => '',
    ],

    'resource_type_not_supported' => [
        'title' => 'Não suportado',
        'detail' => 'O tipo de recurso :type não é suportado por este endpoint.',
        'code' => '',
    ],

    'resource_type_not_supported_by_to_one_relationship' => [
        'title' => 'Entidade não processável',
        'detail' => 'O campo :field deve ser uma relação do tipo para-um contendo recursos :types.',
        'code' => '',
    ],

    'resource_type_not_supported_by_to_many_relationship' => [
        'title' => 'Entidade não processável',
        'detail' => 'O: campo deve ser uuma relação do tipo para-muitos contendo recursos :types.',
        'code' => '',
    ],

    'resource_type_not_recognised' => [
        'title' => 'Não suportado',
        'detail' => 'O tipo de recurso :type não é reconhecido.',
        'code' => '',
    ],

    'resource_id_not_supported' => [
        'title' => 'Não suportado',
        'detail' => 'O ID do recurso :id não é suportado por este endpoint.',
        'code' => '',
    ],

    'resource_client_ids_not_supported' => [
        'title' => 'Não suportado',
        'detail' => 'O tipo de recurso :type não suporta IDs gerados pelo cliente.',
        'code' => '',
    ],

    'resource_exists' => [
        'title' => 'Conflito',
        'detail' => 'O recurso :id já existe.',
        'code' => '',
    ],

    'resource_not_found' => [
        'title' => 'Não encontrado',
        'detail' => 'O recurso relacionado não existe.',
        'code' => '',
    ],

    'resource_field_exists_in_attributes_and_relationships' => [
        'title' => 'Documento JSON:API não compatível',
        'detail' => 'O campo :field não pode existir como atributo e como relação.',
        'code' => '',
    ],
];
