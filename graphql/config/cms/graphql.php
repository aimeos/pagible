<?php

return [
    'filesize' => env( 'CMS_GRAPHQL_FILESIZE', 50 ),
    'mimetypes' => explode( ',', env( 'CMS_GRAPHQL_MIMETYPES', 'application/gzip,application/pdf,application/vnd.,application/zip,audio/,image/,text/,video/' ) ),

    // Maximum nesting depth allowed for a GraphQL query (limits recursive nav relations).
    'maxdepth' => (int) env( 'CMS_GRAPHQL_MAXDEPTH', 15 ),

    // Maximum query complexity score allowed for a GraphQL query.
    'maxcomplexity' => (int) env( 'CMS_GRAPHQL_MAXCOMPLEXITY', 10000 ),
];
