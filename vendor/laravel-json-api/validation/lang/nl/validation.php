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
        'title' => 'Onverwerkbare Entiteit',
        'detail' => 'Het document was goed opgemaakt, maar bevat semantische fouten.',
        'code' => '',
    ],

    'resource_invalid' => [
        'title' => 'Onverwerkbare Entiteit',
        'detail' => 'Het document was goed opgemaakt, maar bevat semantische fouten.',
        'code' => '',
    ],

    'query_invalid' => [
        'title' => 'Ongeldige queryparameter',
        'detail' => 'De queryparameters van het verzoek zijn ongeldig.',
        'code' => '',
    ],

    'delete_invalid' => [
        'title' => 'Niet Verwijderbaar',
        'detail' => 'Deze resource kan niet worden verwijderd.',
        'code' => '',
    ],

    'allowed_field_sets' => [
        'default' => 'Spaarzame veldsets mogen alleen toegestane bevatten.',
        'singular' => 'Spaarzame veldset :values is niet toegestaan.',
        'plural' => 'Spaarzame veldsets :values zijn niet toegestaan.',

        'unrecognised' => [
            'singular' => 'Resource type :type wordt niet herkend.',
            'plural' => 'Resource types :types worden niet herkend.',
        ],
    ],

    'allowed_filter_parameters' => [
        'default' => 'Filterparameters mogen alleen toegestane bevatten.',
        'singular' => 'Filterparameter :values is niet toegestaan.',
        'plural' => 'Filterparameters :values zijn niet toegestaan.',
    ],

    'allowed_include_paths' => [
        'default' => 'Insluit-paden mogen alleen toegestane bevatten.',
        'singular' => 'Insluit-pad :values is niet toegestaan.',
        'plural' => 'Insluit-paden :values zijn niet toegestaan.',
    ],

    'allowed_sort_parameters' => [
        'default' => 'Sorteerparameters mogen alleen toegestane bevatten.',
        'singular' => 'Sorteerparameter :values is niet toegestaan.',
        'plural' => 'Sorteerparameters :values zijn niet toegestaan.',
    ],

    'allowed_page_parameters' => [
        'default' => 'Pagina-parameters mogen alleen toegestane bevatten.',
        'singular' => 'Pagina-parameter :values is niet toegestaan.',
        'plural' => 'Pagina-parameters :values zijn niet toegestaan.',
    ],

    'allowed_countable_fields' => [
        'default' => 'Telvelden mogen alleen toegestane getallen bevatten.',
        'singular' => 'Veld :values is niet telbaar.',
        'plural' => 'Veld :values zijn niet telbaar.',
    ],

    'boolean_string' => 'Het :attribute moet waar of onwaar zijn.',

    'client_id' => 'Het :attribute-formaat is ongeldig.',

    'date_time_iso_8601' => 'Het attribuut :attribute heeft geen geldig ISO 8601 datum/tijd formaat.',

    'json_boolean' => 'Het veld :attribute moet een boolean zijn.',

    'json_integer' => 'Het veld :attribute moet een geheel getal zijn.',

    'json_number' => 'Het veld :attribute moet een nummer zijn.',

    'json_array' => 'Het veld :attribute moet een array zijn.',

    'json_object' => 'Het :attribute-veld moet een object zijn.',

    'parameter_not_supported' => 'Parameter :name is niet toegestaan.',

    'has_one' => 'Het veld :attribute moet een naar-één relatie zijn die :types resources bevat.',

    'has_many' => 'Het veld :attribute moet een naar-velen relatie zijn die :types resources bevat.',
];
