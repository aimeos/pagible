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
    | its "events" all exist, so cards for packages that are not installed stay
    | hidden even when left in the list.
    |
    | Admin activity is bucketed per transport in a "cms_<source>" Pulse type
    | (cms_graphql, cms_mcp, …), so the graphql/mcp cards each show only that
    | transport's operations. A card for any other source can be added by pointing
    | "type" at its bucket.
    |
    | Options per card:
    |   title      - Heading shown on the card
    |   type       - Pulse metric type to read (without the tenant suffix)
    |   group      - Key field the rows are grouped by (default "action")
    |   aggregates - Pulse aggregates to fetch (default ["count"])
    |   details    - Key fields shown as the row's detail line
    |   success    - Append a success rate to the detail line when true
    |   events     - Fully qualified class-name strings that must exist for the card to appear
    |
    */
    'cards' => [

        'request' => [
            'title' => 'Page requests',
            'type' => 'cms_request',
            'group' => 'path',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['domain', 'status'],
            'events' => ['Aimeos\\Cms\\Events\\CmsRequest'],
        ],

        'search' => [
            'title' => 'Search',
            'type' => 'cms_search',
            'group' => 'action',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['domain', 'lang'],
            'events' => ['Aimeos\\Cms\\Events\\CmsSearch'],
        ],

        'contact' => [
            'title' => 'Contact',
            'type' => 'cms_contact',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['ip'],
            'events' => ['Aimeos\\Cms\\Events\\CmsContact'],
        ],

        'jsonapi' => [
            'title' => 'JSON:API',
            'type' => 'cms_jsonapi',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['domain'],
            'events' => ['Aimeos\\Cms\\Events\\CmsJsonapi'],
        ],

        'graphql' => [
            'title' => 'GraphQL',
            'type' => 'cms_graphql',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['domain'],
            'success' => true,
            'events' => ['Aimeos\\Cms\\Events\\CmsGraphql'],
        ],

        'mcp' => [
            'title' => 'MCP',
            'type' => 'cms_mcp',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['domain'],
            'success' => true,
            'events' => ['Aimeos\\Cms\\Events\\CmsMcp', 'Aimeos\\Cms\\Mcp\\CmsServer'],
        ],

    ],

];
