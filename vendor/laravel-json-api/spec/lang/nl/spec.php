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
        'title' => 'Ongeldige JSON',
        'detail' => 'Verwachten dat JSON zal decoderen.',
        'code' => '',
    ],

    'json_error' => [
        'title' => 'Ongeldige JSON',
    ],

    'json_not_object' => [
        'title' => 'Ongeldige JSON',
        'detail' => 'Verwachten dat JSON naar een object decodeert.',
        'code' => '',
    ],

    'member_required' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'Het onderdeel :member is vereist.',
        'code' => '',
    ],

    'member_object_expected' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'Het onderdeel :member moet een object zijn.',
        'code' => '',
    ],

    'member_array_expected' => [
        'title' => 'Non-Compliant JSON:API Document',
        'detail' => 'Het onderdeel :member moet een array zijn.',
        'code' => '',
    ],

    'member_identifier_expected' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'Het onderdeel :member moet een resource identifier zijn.',
        'code' => '',
    ],

    'member_string_expected' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'Het onderdeel :member moet een string zijn.',
        'code' => '',
    ],

    'member_empty' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'Het onderdeel :member kan niet leeg zijn.',
        'code' => '',
    ],

    'member_field_not_allowed' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'Het onderdeel :member kan niet een veld :field hebben.',
        'code' => '',
    ],

    'member_field_not_supported' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'Het veld :field is geen ondersteund :type.',
        'code' => '',
    ],

    /**
     * @TODO requires translation.
     */
    'field_expects_to_one' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'The field :field must be a to-one relation.',
        'code' => '',
    ],

    /**
     * @TODO requires translation
     */
    'field_expects_to_many' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'The field :field must be a to-many relation.',
        'code' => '',
    ],

    'resource_type_not_supported' => [
        'title' => 'Niet Ondersteund',
        'detail' => 'Resource type :type wordt niet ondersteund door dit endpoint.',
        'code' => '',
    ],

    'resource_type_not_supported_by_to_one_relationship' => [
        'title' => 'Onverwerkbare Entiteit',
        'detail' => 'Het veld :field moet een naar-één relatie zijn die :types resources bevat.',
        'code' => '',
    ],

    'resource_type_not_supported_by_to_many_relationship' => [
        'title' => 'Onverwerkbare Entiteit',
        'detail' => 'Het veld :field moet een naar-velen relatie zijn die :types resources bevat.',
        'code' => '',
    ],

    'resource_type_not_recognised' => [
        'title' => 'Niet Ondersteund',
        'detail' => 'Resource type :type wordt niet herkend.',
        'code' => '',
    ],

    'resource_id_not_supported' => [
        'title' => 'Niet Ondersteund',
        'detail' => 'Resource id :id wordt niet ondersteund door dit endpoint.',
        'code' => '',
    ],

    'resource_client_ids_not_supported' => [
        'title' => 'Niet Ondersteund',
        'detail' => 'Resource type :type ondersteunt geen  client-gegenereerde IDs.',
        'code' => '',
    ],

    'resource_exists' => [
        'title' => 'Conflict',
        'detail' => 'Resource :id bestaat al.',
        'code' => '',
    ],

    'resource_not_found' => [
        'title' => 'Niet gevonden',
        'detail' => 'De gerelateerde resource bestaat niet.',
        'code' => '',
    ],

    'resource_field_exists_in_attributes_and_relationships' => [
        'title' => 'Niet-Conform JSON:API Document',
        'detail' => 'Het veld :field kan niet bestaan als een attribuut en een relatie.',
        'code' => '',
    ],

];
