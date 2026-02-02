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
        'title' => 'JSON non valide',
        'detail' => 'Attendez-vous à ce que JSON décode.',
        'code' => '',
    ],

    'json_error' => [
        'title' => 'JSON non valide',
    ],

    'json_not_object' => [
        'title' => 'JSON non valide',
        'detail' => 'Attendre que JSON décode en objet.',
        'code' => '',
    ],

    'member_required' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le membre :member est obligatoire.',
        'code' => '',
    ],

    'member_object_expected' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le membre :member doit être un objet.',
        'code' => '',
    ],

    'member_array_expected' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le membre :member doit être un tableau.',
        'code' => '',
    ],

    'member_identifier_expected' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le membre :member doit être un identifiant de ressource.',
        'code' => '',
    ],

    'member_string_expected' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le membre :member doit être une chaîne de caractères.',
        'code' => '',
    ],

    'member_empty' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le membre :member ne peut être vide.',
        'code' => '',
    ],

    'member_field_not_allowed' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le membre :member ne peut avoir de champ :field.',
        'code' => '',
    ],

    'member_field_not_supported' => [
        'title' => 'Document JSON:API invalide',
        'detail' => "Le champ :field n'est pas un élément de type :type reconnu.",
        'code' => '',
    ],

    'field_expects_to_one' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le champ :field doit être une relation "to-one".',
        'code' => '',
    ],

    'field_expects_to_many' => [
        'title' => 'Document JSON:API invalide',
        'detail' => 'Le champ :field doit être une relation "to-many".',
        'code' => '',
    ],

    'resource_type_not_supported' => [
        'title' => 'Non supporté',
        'detail' => "Le type de ressource :type n'est pas supporté par ce endpoint.",
        'code' => '',
    ],

    'resource_type_not_supported_by_to_one_relationship' => [
        'title' => 'Entité non traitable',
        'detail' => 'Le champ :field doit être une relation "to-one" contenant des ressources de type :types.',
        'code' => '',
    ],

    'resource_type_not_supported_by_to_many_relationship' => [
        'title' => 'Entité non traitable',
        'detail' => 'Le champ :field doit être une relation "to-many" contenant des ressources de type :types.',
        'code' => '',
    ],

    'resource_type_not_recognised' => [
        'title' => 'Non supporté',
        'detail' => "Le type de ressource :type n'est pas reconnu.",
        'code' => '',
    ],

    'resource_id_not_supported' => [
        'title' => 'Non supporté',
        'detail' => "L'identifiant de ressource :id n'est pas supporté par ce endpoint.",
        'code' => '',
    ],

    'resource_client_ids_not_supported' => [
        'title' => 'Non supporté',
        'detail' => "Le type de ressource :type n'accepte pas les identifiants générés par le client.",
        'code' => '',
    ],

    'resource_exists' => [
        'title' => 'Conflit',
        'detail' => 'La ressource :id existe déjà.',
        'code' => '',
    ],

    'resource_not_found' => [
        'title' => 'Introuvable',
        'detail' => "La ressource spécifiée n'existe pas.",
        'code' => '',
    ],

    'resource_field_exists_in_attributes_and_relationships' => [
        'title' => "Document JSON:API invalide",
        'detail' => 'Le champ :field ne peut être à la fois un attribut et une relation.',
        'code' => '',
    ],
];
