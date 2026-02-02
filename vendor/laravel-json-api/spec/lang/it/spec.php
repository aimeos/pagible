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
        'title' => 'JSON non valido',
        'detail' => 'Previsto un JSON da decodificare.',
        'code' => '',
    ],

    'json_error' => [
        'title' => 'JSON non valido',
    ],

    'json_not_object' => [
        'title' => 'JSON non valido',
        'detail' => 'Previsto un JSON da decodificare in un oggetto.',
        'code' => '',
    ],

    'member_required' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il membro :member è richiesto.',
        'code' => '',
    ],

    'member_object_expected' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il membro :member deve essere un oggetto.',
        'code' => '',
    ],

    'member_array_expected' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il membro :member deve essere un array.',
        'code' => '',
    ],

    'member_identifier_expected' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il membro :member deve essere un identificatore di risorsa.',
        'code' => '',
    ],

    'member_string_expected' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il membro :member deve essere una stringa.',
        'code' => '',
    ],

    'member_empty' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il membro :member non può essere vuoto.',
        'code' => '',
    ],

    'member_field_not_allowed' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il membro :member non può avere un campo :field.',
        'code' => '',
    ],

    'member_field_not_supported' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il campo :field non è un :type supportato.',
        'code' => '',
    ],

    'field_expects_to_one' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il campo :field deve essere una relazione to-one.',
        'code' => '',
    ],

    'field_expects_to_many' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il campo :field deve essere una relazione to-many.',
        'code' => '',
    ],

    'resource_type_not_supported' => [
        'title' => 'Non supportato',
        'detail' => 'Il tipo di risorsa :type non è supportato da questo endpoint.',
        'code' => '',
    ],

    'resource_type_not_supported_by_to_one_relationship' => [
        'title' => 'Entità non processabile',
        'detail' => 'Il campo :field deve essere una relazione to-one contenente risorse :types.',
        'code' => '',
    ],

    'resource_type_not_supported_by_to_many_relationship' => [
        'title' => 'Entità non processabile',
        'detail' => 'Il campo :field deve essere una relazione to-many contenente risorse :types.',
        'code' => '',
    ],

    'resource_type_not_recognised' => [
        'title' => 'Non supportato',
        'detail' => 'Il tipo di risorsa :type non è riconosciuto.',
        'code' => '',
    ],

    'resource_id_not_supported' => [
        'title' => 'Non supportato',
        'detail' => 'L\'id risorsa :id non è supportato da questo endpoint.',
        'code' => '',
    ],

    'resource_client_ids_not_supported' => [
        'title' => 'Non supportato',
        'detail' => 'Il tipo di risorsa :type non supporta gli ID generati dal client.',
        'code' => '',
    ],

    'resource_exists' => [
        'title' => 'Conflitto',
        'detail' => 'La risorsa :id esiste già.',
        'code' => '',
    ],

    'resource_not_found' => [
        'title' => 'Non trovato',
        'detail' => 'La risorsa correlata non esiste.',
        'code' => '',
    ],

    'resource_field_exists_in_attributes_and_relationships' => [
        'title' => 'Documento JSON:API non conforme',
        'detail' => 'Il campo :field non può esistere come attributo e come relazione.',
        'code' => '',
    ],
];
