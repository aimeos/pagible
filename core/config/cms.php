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
    | Page related configuration
    |--------------------------------------------------------------------------
    |
    | Define the page types and their configuration. Each type can have a
    | set of sections that can be used to organize the content. The sections
    | can be used to define the layout of the page.
    |
    */

];
