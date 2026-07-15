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

    /*
    |--------------------------------------------------------------------------
    | Request logging
    |--------------------------------------------------------------------------
    |
    | When enabled together with "cms.watch.channel", each read-only JSON:API
    | request dispatches a rich audit event containing its request shape. Pulse
    | observations are listener-driven and don't require this flag.
    |
    */
    'watch' => env( 'CMS_JSONAPI_WATCH', false ),
];
