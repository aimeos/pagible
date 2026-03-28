<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JSON:API settings
    |--------------------------------------------------------------------------
    |
    | The "maxdepth" setting defines the maximum depth of the JSON:API
    | resource relationships that will be included in the response.
    | Example: 1 = include=children; 2 = include=children,children.children
    |
    */
    'maxdepth' => env( 'CMS_JSONAPI_MAXDEPTH', 1 ),
];
