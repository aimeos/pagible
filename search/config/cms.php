<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum search term length
    |--------------------------------------------------------------------------
    |
    | Minimum number of characters required in the public search input before a
    | full-text search is performed. Enforced by the search controller and the
    | frontend search widget. Clamped to 1-200 (the maximum query length) so the
    | minimum is always a usable value that never exceeds the maximum.
    |
    */
    'search' => [
        'min' => max( 1, min( (int) env( 'CMS_SEARCH_MIN', 2 ), 200 ) ),
    ],

];
