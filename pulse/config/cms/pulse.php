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
    | Content activity is bucketed per transport in a "cms_<source>" Pulse type
    | (cms_graphql, cms_mcp, cms_cli, …), so the graphql/mcp/cli cards each show
    | only that transport's page/element/file operations, with authentication
    | folded into the graphql card. A card for any other source can be added by
    | pointing "type" at its bucket.
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
            'events' => ['Aimeos\\Cms\\Events\\Viewed'],
        ],

        'search' => [
            'title' => 'Search',
            'type' => 'cms_search',
            'group' => 'action',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['domain', 'lang'],
            'events' => ['Aimeos\\Cms\\Events\\Searched'],
        ],

        'contact' => [
            'title' => 'Contact',
            'type' => 'cms_contact',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['ip'],
            'events' => ['Aimeos\\Cms\\Events\\Contacted'],
        ],

        'jsonapi' => [
            'title' => 'JSON:API',
            'type' => 'cms_jsonapi',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['domain'],
            'events' => ['Aimeos\\Cms\\Events\\Queried'],
        ],

        'ai' => [
            'title' => 'AI',
            'type' => 'cms_ai',
            'group' => 'mutation',
            'aggregates' => ['count', 'avg', 'max'],
            'details' => ['provider', 'model'],
            'success' => true,
            'events' => ['Aimeos\\Cms\\Events\\Generated'],
        ],

        'graphql' => [
            'title' => 'GraphQL',
            'type' => 'cms_graphql',
            'aggregates' => ['count', 'sum'],
            'details' => ['domain', 'mime'],
            'events' => ['Aimeos\\Cms\\Events\\Authed'],
        ],

        'mcp' => [
            'title' => 'MCP',
            'type' => 'cms_mcp',
            'aggregates' => ['count', 'sum'],
            'details' => ['domain', 'mime'],
            'events' => ['Aimeos\\Cms\\Mcp\\CmsServer'],
        ],

        'cli' => [
            'title' => 'CLI',
            'type' => 'cms_cli',
            'aggregates' => ['count', 'sum'],
            'details' => ['domain', 'mime'],
        ],

    ],

];
