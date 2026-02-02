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
        'title' => 'Entità non processabile',
        'detail' => 'Il documento era ben formattato ma contiene errori semantici.',
        'code' => '',
    ],

    'resource_invalid' => [
        'title' => 'Entità non processabile',
        'detail' => 'Il documento era ben formattato ma contiene errori semantici.',
        'code' => '',
    ],

    'query_invalid' => [
        'title' => 'Parametro di query non valido',
        'detail' => 'I parametri della query di richiesta non sono validi.',
        'code' => '',
    ],

    'delete_invalid' => [
        'title' => 'Non eliminabile',
        'detail' => 'La risorsa non può essere eliminata.',
        'code' => '',
    ],

    'allowed_field_sets' => [
        'default' => 'Gli insiemi di campi sparsi devono contenere solo quelli consentiti.',
        'singular' => 'L\'insieme di campi sparsi :values non è permesso.',
        'plural' => 'Gli insiemi di campi sparsi :values non sono ammessi.',

        'unrecognised' => [
            'singular' => 'Il tipo di risorsa :types non è riconosciuto.',
            'plural' => 'I tipi di risorsa :types non sono riconosciuti.',
        ],
    ],

    'allowed_filter_parameters' => [
        'default' => 'I parametri del filtro devono contenere solo quelli consentiti.',
        'singular' => 'Il parametro del filtro :values non è consentito.',
        'plural' => 'Parametri del filtro :values non consentiti.',
    ],

    'allowed_include_paths' => [
        'default' => 'I percorsi di inclusione devono contenere solo quelli consentiti.',
        'singular' => 'Includere il percorso :values non è permesso.',
        'plural' => 'I percorsi di inclusione :values non sono consentiti.',
    ],

    'allowed_sort_parameters' => [
        'default' => 'I parametri di ordinamento devono contenere solo quelli consentiti.',
        'singular' => 'Il parametro di ordinamento :values non è consentito.',
        'plural' => 'I parametri di ordinamento :values non sono consentiti.',
    ],

    'allowed_page_parameters' => [
        'default' => 'I parametri della pagina devono contenere solo quelli consentiti.',
        'singular' => 'Il parametro di pagina :values non è consentito.',
        'plural' => 'I parametri di pagina :values non sono consentiti.',
    ],

    'allowed_countable_fields' => [
        'default' => 'I campi di conteggio devono contenere solo quelli consentiti.',
        'singular' => 'Il campo :values non è conteggiabile.',
        'plural' => 'I campi :values non sono conteggiabili.',
    ],

    'boolean_string' => 'Il campo :attribute deve essere vero o falso.',

    'client_id' => 'Il formato :attribute non è valido.',

    'date_time_iso_8601' => ':attribute non è una data e ora ISO 8601 valida.',

    'json_boolean' => 'Il campo :attribute deve essere un booleano.',

    'json_integer' => 'Il campo :attribute deve essere un intero.',

    'json_number' => 'Il campo :attribute deve essere un numero.',

    'json_array' => 'Il campo :attribute deve essere un array.',

    'json_object' => 'Il campo :attribute deve essere un oggetto.',

    'parameter_not_supported' => 'Il parametro :name non è consentito.',

    'has_one' => 'Il campo :attribute deve essere una relazione to-one contenente risorse :types.',

    'has_many' => 'Il campo :attribute deve essere una relazione to-many contenente risorse :types.',
];
