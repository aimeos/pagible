<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard cards
    |--------------------------------------------------------------------------
    |
    | The CMS Pulse cards shown on the dashboard, in display order. Reorder the
    | entries to change the layout, remove an entry to hide a card, or add an
    | entry to show a new one. A card only appears when the classes listed in
    | its "requires" option all exist, so cards for packages that are not
    | installed stay hidden even when left in the list.
    |
    | Admin activity is bucketed per transport in a "cms_<source>" Pulse type
    | (cms_graphql, cms_mcp, …), so the graphql/mcp cards each show only that
    | transport's operations. Card keys match their observation source; each card
    | shows count, average duration and maximum duration.
    |
    | Options per card:
    |   title      - Heading shown on the card
    |   group      - Key field the rows are grouped by (default "action")
    |   details    - Key fields shown as the row's detail line
    |   success    - Append a success rate to the detail line when true
    |   requires   - Class-name strings that must exist for the card to appear
    |
    */
    'cards' => [

        'request' => [
            'title' => 'Page requests',
            'group' => 'path',
            'details' => ['domain', 'status'],
            'requires' => ['Aimeos\\Cms\\ThemeServiceProvider'],
        ],

        'search' => [
            'title' => 'Search',
            'details' => ['domain', 'lang'],
            'requires' => ['Aimeos\\Cms\\ThemeServiceProvider'],
        ],

        'contact' => [
            'title' => 'Contact',
            'requires' => ['Aimeos\\Cms\\ThemeServiceProvider'],
        ],

        'jsonapi' => [
            'title' => 'JSON:API',
            'details' => ['domain'],
            'requires' => ['Aimeos\\Cms\\JsonapiServiceProvider'],
        ],

        'graphql' => [
            'title' => 'GraphQL',
            'details' => ['domain'],
            'success' => true,
            'requires' => ['Aimeos\\Cms\\GraphqlServiceProvider'],
        ],

        'mcp' => [
            'title' => 'MCP',
            'details' => ['domain'],
            'success' => true,
            'requires' => ['Aimeos\\Cms\\McpServiceProvider'],
        ],

    ],

];
