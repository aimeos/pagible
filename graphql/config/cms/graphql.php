<?php

return [
    // Maximum nesting depth allowed for a GraphQL query (limits recursive nav relations).
    'maxdepth' => (int) env( 'CMS_GRAPHQL_MAXDEPTH', 15 ),

    // Maximum query complexity score allowed for a GraphQL query.
    'maxcomplexity' => (int) env( 'CMS_GRAPHQL_MAXCOMPLEXITY', 10000 ),
];
