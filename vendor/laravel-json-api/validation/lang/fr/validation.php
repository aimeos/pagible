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
        'title' => 'Entité non traitable',
        'detail' => 'Le document est correctement structuré mais contient des erreurs sémantiques.',
        'code' => '',
    ],

    'resource_invalid' => [
        'title' => 'Entité non traitable',
        'detail' => 'Le document est correctement structuré mais contient des erreurs sémantiques.',
        'code' => '',
    ],

    'query_invalid' => [
        'title' => 'Paramètre de requête invalide',
        'detail' => 'Les paramètres de la requête ne sont pas valides.',
        'code' => '',
    ],

    'delete_invalid' => [
        'title' => 'Non supprimable',
        'detail' => 'La ressource ne peut être supprimée.',
        'code' => '',
    ],

    'allowed_field_sets' => [
        'default' => 'Certains champs soumis ne sont pas autorisés.',
        'singular' => "Le champ soumis :values n'est pas autorisé.",
        'plural' => 'Les champs soumis :values ne sont pas autorisés.',

        'unrecognised' => [
            'singular' => "Le type de ressource :types n'est pas reconnu.",
            'plural' => 'Les types de ressources :types ne sont pas reconnus.',
        ],
    ],

    'allowed_filter_parameters' => [
        'default' => 'Certains paramètres de filtre de sont pas autorisés.',
        'singular' => "Le paramètre de filtre :values n'est pas autorisé.",
        'plural' => 'Les paramètres de filtre :values ne sont pas autorisés.',
    ],

    'allowed_include_paths' => [
        'default' => 'Certains chemins inclus ne sont pas autorisés.',
        'singular' => "Le chemin inclus :values n'est pas autorisé.",
        'plural' => 'Les chemins inclus :values ne sont pas autorisés.',
    ],

    'allowed_sort_parameters' => [
        'default' => 'Certains paramètres de tri ne sont pas autorisés.',
        'singular' => "Le paramètre de tri :values n'est pas autorisé.",
        'plural' => 'Les paramètres de tri :values ne sont pas autorisés.',
    ],

    'allowed_page_parameters' => [
        'default' => 'Certains paramètres de pagination ne sont pas autorisés.',
        'singular' => "Le paramètre de pagination :values n'est pas autorisé.",
        'plural' => 'Les paramètres de pagination :values ne sont pas autorisés.',
    ],

    'allowed_countable_fields' => [
        'default' => 'Les champs de comptage ne doivent contenir que ceux autorisés.',
        'singular' => 'Field :values n’est pas dénombrable.',
        'plural' => 'Champ :values ne sont pas dénombrables.',
    ],

    'boolean_string' => 'Le champ :attribute doit être vrai ou faux.',

    'client_id' => "Le format de :attribute n'est pas valide.",

    'date_time_iso_8601' => ":attribute n'est pas au format ISO 8601 de date et heure.",

    'json_boolean' => 'Le champ :attribute doit être un booléen.',

    'json_integer' => 'Le champ :attribute doit être un entier.',

    'json_number' => 'Le champ :attribute doit être un nombre.',

    'json_array' => 'Le champ :attribute doit être un tableau.',

    'json_object' => 'Le champ :attribute doit être un objet.',

    'parameter_not_supported' => "Le paramètre :name n'est pas autorisé.",

    'has_one' => 'Le champ :attribute doit être une relation "to-one" contenant des ressources de type :types.',

    'has_many' => 'Le champ :attribute doit être une relation "to-many" contenant des ressources de type :types.',
];
