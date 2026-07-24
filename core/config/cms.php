<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Named roles
    |--------------------------------------------------------------------------
    |
    | Define named roles as permission sets. Role names are stored in the
    | user's cmsperms JSON array alongside individual permissions and
    | expanded at check time. Each role maps to an array of entries:
    |
    | - "page:view"    — grant a single permission
    | - "page:*"       — grant all permissions in a group (page, element, …)
    | - "*"            — grant every permission
    | - "!page:purge"  — deny a single permission (applied after grants)
    | - "!page:*"      — deny all permissions in a group
    | - "!*:purge"     — deny all purge permissions
    |
    | Users can hold multiple roles and individual overrides together:
    |   ["editor", "image:imagine"]    — editor + one extra permission
    |   ["admin", "!page:purge"]       — admin minus purge
    |   ["editor", "publisher"]        — union of both roles
    |
    */
    'roles' => [
        'admin' => ['*'],
        'viewer' => ['page:view', 'element:view', 'file:view'],
        'publisher' => ['page:*', 'element:*', 'file:*', 'audio:*', 'image:*', 'text:*', 'page:config'],
        'editor' => ['publisher', '!*:publish', '!*:purge'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allow fetching from internal hosts
    |--------------------------------------------------------------------------
    |
    | When TRUE, the CMS may fetch remote URLs (e.g. when importing a file from
    | a URL) whose host resolves to a private or reserved IP range (e.g.
    | 10.0.0.0/8, 127.0.0.1 or internal services accessed by IP). Set it to
    | FALSE to block them and mitigate SSRF when URLs can be supplied by
    | untrusted users.
    |
    */
    'allow-internal' => env( 'CMS_ALLOW_INTERNAL', false ),

    /*
    |--------------------------------------------------------------------------
    | Real-time broadcasting
    |--------------------------------------------------------------------------
    |
    | When enabled, the CMS will broadcast save events via Laravel Reverb so
    | other editors see changes immediately. Requires laravel/reverb and
    | laravel-echo in the host application.
    |
    */
    'broadcast' => env( 'CMS_BROADCAST', false ),

    /*
    |--------------------------------------------------------------------------
    | Broadcasting authorization middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to the "/broadcasting/auth" channel-authorization route. Throttled by
    | default; multi-tenant setups (e.g. stancl/tenancy) must also add their tenancy-init
    | middleware here so Tenancy::value() resolves when channels are authorized.
    |
    */
    'broadcast-middleware' => ['web', 'auth', 'throttle:cms-broadcast'],

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | Use the database connection defined in ./config/database.php to manage
    | page, element and file records.
    |
    */
    'db' => env( 'DB_CONNECTION', 'sqlite' ),

    /*
    |--------------------------------------------------------------------------
    | Filesystem disk
    |--------------------------------------------------------------------------
    |
    | Use the filesystem disk defined in ./config/filesystems.php to store the
    | uploaded files. By default, they are stored in the ./public/storage/cms/
    | folder but this can be any supported cloud storage too.
    |
    */
    'disk' => env( 'CMS_DISK', 'public' ),

    /*
    |--------------------------------------------------------------------------
    | File upload policy
    |--------------------------------------------------------------------------
    |
    | The maximum upload size is specified in MB and the decoded raster size
    | in pixels. MIME types may be complete types or prefixes and apply to
    | uploads through every CMS interface.
    |
    */
    'upload' => [
        'filesize' => env( 'CMS_UPLOAD_FILESIZE', 50 ),
        'maxpixels' => env( 'CMS_UPLOAD_MAXPIXELS', 4096 * 4096 ),
        'mimetypes' => explode( ',', env( 'CMS_UPLOAD_MIMETYPES', 'application/gzip,application/pdf,application/vnd.,application/zip,audio/,image/,text/,video/' ) ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image settings
    |--------------------------------------------------------------------------
    |
    | The "preview-sizes" array defines the maximum widths and heights of the
    | preview images in pixel that are generated for the uploaded images.
    |
    */
    'image' => [
        'preview-sizes' => [
            ['width' => 480, 'height' => 270],
            ['width' => 960, 'height' => 540],
            ['width' => 1920, 'height' => 1080],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | The list of supported locales for the content. This is used to generate
    | the locale switcher in the admin panel and to validate the locale of the
    | pages and elements. The first locale in the list is used as the default
    | locale for the content.
    |
    */
    'locales' => explode( ',', env( 'CMS_LOCALES', 'en,ar,zh,fr,de,es,pt,pt-BR,ru' ) ),

    /*
    |--------------------------------------------------------------------------
    | Multi-domain support
    |--------------------------------------------------------------------------
    |
    | If enabled, the CMS will use the domain name to determine the pages to
    | display. If disabled, the pages are shared across all domains.
    |
    */
    'multidomain' => env( 'CMS_MULTIDOMAIN', false ),

    /*
    |--------------------------------------------------------------------------
    | Navigation menu depth
    |--------------------------------------------------------------------------
    |
    | The maximum depth of the navigation tree menu that will be displayed.
    |
    */
    'navdepth' => env( 'CMS_NAVDEPTH', 2 ),

    /*
    |--------------------------------------------------------------------------
    | Prune deleted records
    |--------------------------------------------------------------------------
    |
    | Number of days after deleted pages, elements and files will be finally
    | removed. Disable pruning with FALSE as value.
    |
    */
    'prune' => env( 'CMS_PRUNE', 30 ),

    /*
    |--------------------------------------------------------------------------
    | Number of stored versions
    |--------------------------------------------------------------------------
    |
    | Number of versions to keep for each page, element and file. If the
    | number of versions exceeds this value, the oldest versions will be
    | deleted.
    |
    */
    'versions' => env( 'CMS_VERSIONS', 10 ),

    /*
    |--------------------------------------------------------------------------
    | Observability (watch)
    |--------------------------------------------------------------------------
    |
    | Structured audit/observability logging. Set "channel" (e.g. CMS_LOG_CHANNEL=cms)
    | to the Laravel log channel that receives the entries; leave it unset to disable
    | all logging at zero per-request cost. When the named channel is not defined in
    | config/logging.php, the core package registers a daily JSON channel for it.
    |
    | "sample" (0.0-1.0) keeps that fraction of high-volume read entries (page
    | requests, frontend search, JSON:API); audit streams (content, auth, contact)
    | are always complete.
    | "anonymize" SHA-256 hashes personal data (email, IP, user agent) in auth and
    | contact entries before logging; set FALSE to store raw values.
    |
    */
    'watch' => [
        'channel' => env( 'CMS_LOG_CHANNEL' ),
        'sample' => env( 'CMS_WATCH_SAMPLE', 1.0 ),
        'anonymize' => env( 'CMS_WATCH_ANONYMIZE', true ),
    ],

];
