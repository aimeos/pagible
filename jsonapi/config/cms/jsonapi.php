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
    | request dispatches a watch event so the request duration, result count and
    | includes can be logged and shown in the metrics dashboard. Off by default
    | as these requests can be high volume.
    |
    */
    'watch' => env( 'CMS_JSONAPI_WATCH', false ),
];
