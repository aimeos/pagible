<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prism AI providers
    |--------------------------------------------------------------------------
    |
    | Use the Prism AI providers defined in ./config/prism.php to
    | generate content for pages and elements. The default provider is
    | OpenAI, but you can use any other provider that is supported by Prism.
    |
    */
    'ai' => [
        'maxtoken' => env( 'CMS_AI_MAXTOKEN' ), // maxium tokenss per request

        'text' => env( 'CMS_AI_TEXT' ),                 // gemini
        'text-model' => env( 'CMS_AI_TEXT_MODEL' ),     // gemini-2.5-flash
        'struct' => env( 'CMS_AI_STRUCT' ),             // gemini
        'struct-model' => env( 'CMS_AI_STRUCT_MODEL' ), // gemini-2.5-flash
        'image' => env( 'CMS_AI_IMAGE' ),               // openai
        'image-model' => env( 'CMS_AI_IMAGE_MODEL' ),   // dall-e-3
        'audio' => env( 'CMS_AI_AUDIO' ),               // openai
        'audio-model' => env( 'CMS_AI_AUDIO_MODEL' ),   // whisper-1
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache store
    |--------------------------------------------------------------------------
    |
    | Use the cache store defined in ./config/cache.php to store rendered pages
    | for fast response times.
    |
    */
    'cache' => env( 'APP_DEBUG' ) ? 'array' : 'file',

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
    | JSON:API settings
    |--------------------------------------------------------------------------
    |
    | The "jsonapi_maxdepth" setting defines the maximum depth of the JSON:API
    | resource relationships that will be included in the response.
    | Example: 1 = include=children; 2 = include=children,children.children
    |
    */
    'jsonapi_maxdepth' => env( 'CMS_JSONAPI_MAXDEPTH', 1 ),

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
    'menu_maxdepth' => env( 'CMS_MENU_MAXDEPTH', 2 ),

    /*
    |--------------------------------------------------------------------------
    | Use package catch-all page route
    |--------------------------------------------------------------------------
    |
    | If enabled, the package will register a catch-all route that will
    | match all requests and forward them to the CMS. Disable this option
    | if you need to register own routes before the catch-all route.
    |
    */
    'pageroute' => env( 'CMS_PAGEROUTE', true ),

    /*
    |--------------------------------------------------------------------------
    | Proxy settings
    |--------------------------------------------------------------------------
    |
    | The proxy settings define the maximum length of the file that can be
    | downloaded via the proxy in MB and the timeout for streaming the file
    | in seconds. The default values are 10 MB and 30 seconds, respectively.
    |
    | The proxy is used to fetch external resources like images, videos or
    | files that are linked in the content. The proxy will download the file
    | and stream it to the client, so that the browser can display it without
    | potential CORS issues.
    |
    */
    'proxy' => [
        'max-length' => env( 'CMS_PROXY_MAX_LENGTH', 10 ), // in MB
        'stream_timeout' => env( 'CMS_PROXY_STREAM_TIMEOUT', 30 ), // in seconds
    ],

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
    | Page related configuration
    |--------------------------------------------------------------------------
    |
    | Define the page types and their configuration. Each type can have a
    | set of sections that can be used to organize the content. The sections
    | can be used to define the layout of the page.
    |
    */
    'config' => [
        'locales' => ['en', 'ar', 'zh', 'fr', 'de', 'es', 'pt', 'pt-BR', 'ru'],
        'themes' => [
            'cms' => [
                'types' => [
                    'page' => [
                        'sections' => [
                            'main',
                            'footer',
                        ],
                    ],
                    'docs' => [
                    ],
                    'blog' => [
                    ],
                ],
            ],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Content schemas
    |--------------------------------------------------------------------------
    |
    | Define the content schemas that can be used to in pages and elements.
    | Each schema has a group, an icon and a set of fields with the key as
    | property name and the value from the field definition. Each field
    | has a type, which can be one of the following:
    |
    | - audio: an audio file field
    | - autocomplete: a text field with autocomplete options
    | - checkbox: a checkbox field
    | - color: a color picker field
    | - combobox: a dropdown field with options and a text input
    | - date: a date field
    | - file: a generic file field
    | - hidden: a hidden field that is not displayed in the UI
    | - html: a text field with HTML support
    | - image: an image file field
    | - images: a list of images in the defined order
    | - items: a list of items with a defined structure
    | - markdown: a text field with Markdown support
    | - number: a numeric field
    | - plaintext: a text field without formatting
    | - radio: a radio button field with options
    | - range: a range slider field with start and end values
    | - select: a dropdown field with options
    | - slider: a slider field for a value
    | - string: a simple text field
    | - switch: a toggle switch field
    | - table: a table field with rows and columns
    | - text: a text field with very basic formatting and links
    | - url: a URL field
    | - video: a video file field
    |
    | The configuration options for each field depend on the type of the field.
    |
    */
    'schemas' => [
        'content' => [
            'heading' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3,4H5V10H9V4H11V18H9V12H5V18H3V4M13,8H15.31L15.63,5H17.63L17.31,8H19.31L19.63,5H21.63L21.31,8H23V10H21.1L20.9,12H23V14H20.69L20.37,17H18.37L18.69,14H16.69L16.37,17H14.37L14.69,14H13V12H14.9L15.1,10H13V8M17.1,10L16.9,12H18.9L19.1,10H17.1Z" /></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                        'min' => 1,
                    ],
                    'level' => [
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            ['value' => '1', 'label' => 'H1'],
                            ['value' => '2', 'label' => 'H2'],
                            ['value' => '3', 'label' => 'H3'],
                            ['value' => '4', 'label' => 'H4'],
                            ['value' => '5', 'label' => 'H5'],
                            ['value' => '6', 'label' => 'H6'],
                        ],
                    ],
                ],
            ],
            'text' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21,6V8H3V6H21M3,18H12V16H3V18M3,13H21V11H3V13Z" /></svg>',
                'fields' => [
                    'text' => [
                        'type' => 'markdown',
                        'min' => 1,
                    ],
                ],
            ],
            'image-text' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M7 4.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0m-.861 1.542 1.33.886 1.854-1.855a.25.25 0 0 1 .289-.047l1.888.974V7.5a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V7s1.54-1.274 1.639-1.208M5 9a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1"/></svg>',
                'fields' => [
                    'text' => [
                        'type' => 'text',
                        'min' => 1,
                    ],
                    'file' => [
                        'type' => 'image',
                        'label' => 'image',
                        'required' => true,
                    ],
                    'position' => [
                        'type' => 'select',
                        'options' => [
                            ['value' => 'auto', 'label' => 'Auto'],
                            ['value' => 'start', 'label' => 'Start'],
                            ['value' => 'end', 'label' => 'End'],
                        ],
                    ],
                ],
            ],
            'code' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M2.114 8.063V7.9c1.005-.102 1.497-.615 1.497-1.6V4.503c0-1.094.39-1.538 1.354-1.538h.273V2h-.376C3.25 2 2.49 2.759 2.49 4.352v1.524c0 1.094-.376 1.456-1.49 1.456v1.299c1.114 0 1.49.362 1.49 1.456v1.524c0 1.593.759 2.352 2.372 2.352h.376v-.964h-.273c-.964 0-1.354-.444-1.354-1.538V9.663c0-.984-.492-1.497-1.497-1.6M13.886 7.9v.163c-1.005.103-1.497.616-1.497 1.6v1.798c0 1.094-.39 1.538-1.354 1.538h-.273v.964h.376c1.613 0 2.372-.759 2.372-2.352v-1.524c0-1.094.376-1.456 1.49-1.456V7.332c-1.114 0-1.49-.362-1.49-1.456V4.352C13.51 2.759 12.75 2 11.138 2h-.376v.964h.273c.964 0 1.354.444 1.354 1.538V6.3c0 .984.492 1.497 1.497 1.6"/></svg>',
                'fields' => [
                    'language' => [
                        'type' => 'combobox',
                        'options' => [
                            ['value' => 'css', 'label' => 'CSS'],
                            ['value' => 'graphql', 'label' => 'GraphQL'],
                            ['value' => 'html', 'label' => 'HTML'],
                            ['value' => 'java', 'label' => 'Java'],
                            ['value' => 'javascript', 'label' => 'JavaScript'],
                            ['value' => 'json', 'label' => 'JSON'],
                            ['value' => 'markdown', 'label' => 'Markdown'],
                            ['value' => 'php', 'label' => 'PHP'],
                            ['value' => 'python', 'label' => 'Python'],
                            ['value' => 'ruby', 'label' => 'Ruby'],
                            ['value' => 'sql', 'label' => 'SQL'],
                            ['value' => 'typescript', 'label' => 'TypeScript'],
                            ['value' => 'xml', 'label' => 'XML'],
                        ],
                    ],
                    'text' => [
                        'type' => 'plaintext',
                        'label' => 'source code',
                        'min' => 1,
                    ],
                ],
            ],
            'table' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm15 2h-4v3h4zm0 4h-4v3h4zm0 4h-4v3h3a1 1 0 0 0 1-1zm-5 3v-3H6v3zm-5 0v-3H1v2a1 1 0 0 0 1 1zm-4-4h4V8H1zm0-4h4V4H1zm5-3v3h4V4zm4 4H6v3h4z"/></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                    ],
                    'header' => [
                        'type' => 'select',
                        'options' => [
                            ['value' => '', 'label' => 'None'],
                            ['value' => 'row', 'label' => 'First row'],
                            ['value' => 'col', 'label' => 'First column'],
                            ['value' => 'row+col', 'label' => 'First row and column'],
                        ],
                    ],
                    'table' => [
                        'type' => 'table',
                        'label' => 'table',
                    ],
                ],
            ],
            'html' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M10.478 1.647a.5.5 0 1 0-.956-.294l-4 13a.5.5 0 0 0 .956.294zM4.854 4.146a.5.5 0 0 1 0 .708L1.707 8l3.147 3.146a.5.5 0 0 1-.708.708l-3.5-3.5a.5.5 0 0 1 0-.708l3.5-3.5a.5.5 0 0 1 .708 0m6.292 0a.5.5 0 0 0 0 .708L14.293 8l-3.147 3.146a.5.5 0 0 0 .708.708l3.5-3.5a.5.5 0 0 0 0-.708l-3.5-3.5a.5.5 0 0 0-.708 0"/></svg>',
                'fields' => [
                    'text' => [
                        'type' => 'html',
                        'label' => 'source code',
                        'min' => 1,
                    ],
                ],
            ],

            'image' => [
                'group' => 'media',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"><path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/><path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1z"/></svg>',
                'fields' => [
                    'file' => [
                        'type' => 'image',
                        'required' => true,
                    ],
                    'main' => [
                        'type' => 'switch',
                        'label' => 'load immediately',
                        'default' => false,
                    ],
                ],
            ],
            'slideshow' => [
                'group' => 'media',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21,17H7V3H21M21,1H7A2,2 0 0,0 5,3V17A2,2 0 0,0 7,19H21A2,2 0 0,0 23,17V3A2,2 0 0,0 21,1M3,5H1V21A2,2 0 0,0 3,23H19V21H3M15.96,10.29L13.21,13.83L11.25,11.47L8.5,15H19.5L15.96,10.29Z" /></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                    ],
                    'files' => [
                        'type' => 'images',
                        'label' => 'images',
                        'min' => 2,
                    ],
                    'main' => [
                        'type' => 'switch',
                        'label' => 'load immediately',
                        'default' => false,
                    ],
                ],
            ],
            'video' => [
                'group' => 'media',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10,15L15.19,12L10,9V15M21.56,7.17C21.69,7.64 21.78,8.27 21.84,9.07C21.91,9.87 21.94,10.56 21.94,11.16L22,12C22,14.19 21.84,15.8 21.56,16.83C21.31,17.73 20.73,18.31 19.83,18.56C19.36,18.69 18.5,18.78 17.18,18.84C15.88,18.91 14.69,18.94 13.59,18.94L12,19C7.81,19 5.2,18.84 4.17,18.56C3.27,18.31 2.69,17.73 2.44,16.83C2.31,16.36 2.22,15.73 2.16,14.93C2.09,14.13 2.06,13.44 2.06,12.84L2,12C2,9.81 2.16,8.2 2.44,7.17C2.69,6.27 3.27,5.69 4.17,5.44C4.64,5.31 5.5,5.22 6.82,5.16C8.12,5.09 9.31,5.06 10.41,5.06L12,5C16.19,5 18.8,5.16 19.83,5.44C20.73,5.69 21.31,6.27 21.56,7.17Z" /></svg>',
                'fields' => [
                    'file' => [
                        'type' => 'video',
                        'required' => true,
                    ],
                ],
            ],
            'audio' => [
                'group' => 'media',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21,3V15.5A3.5,3.5 0 0,1 17.5,19A3.5,3.5 0 0,1 14,15.5A3.5,3.5 0 0,1 17.5,12C18.04,12 18.55,12.12 19,12.34V6.47L9,8.6V17.5A3.5,3.5 0 0,1 5.5,21A3.5,3.5 0 0,1 2,17.5A3.5,3.5 0 0,1 5.5,14C6.04,14 6.55,14.12 7,14.34V6L21,3Z" /></svg>',
                'fields' => [
                    'file' => [
                        'type' => 'audio',
                        'required' => true,
                    ],
                ],
            ],
            'file' => [
                'group' => 'media',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14,2L20,8V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V4A2,2 0 0,1 6,2H14M18,20V9H13V4H6V20H18M12,19L8,15H10.5V12H13.5V15H16L12,19Z" /></svg>',
                'fields' => [
                    'file' => [
                        'type' => 'file',
                        'required' => true,
                    ],
                ],
            ],

            'article' => [
                'group' => 'content',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 5V19H5V5H19M21 3H3V21H21V3M17 17H7V16H17V17M17 15H7V14H17V15M17 12H7V7H17V12Z" /></svg>',
                'fields' => [
                    'text' => [
                        'type' => 'text',
                        'label' => 'introduction',
                        'min' => 1,
                        'max' => 1000,
                    ],
                    'file' => [
                        'type' => 'image',
                        'label' => 'image',
                    ],
                ],
            ],
            'blog' => [
                'group' => 'content',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2 14H8V20H2M16 8H10V10H16M2 10H8V4H2M10 4V6H22V4M10 20H16V18H10M10 16H22V14H10" /></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                    ],
                    'action' => [
                        'type' => 'hidden',
                        'value' => '\Aimeos\Cms\Actions\Blog',
                    ],
                    'parent-page' => [
                        'type' => 'autocomplete',
                        'api-type' => 'GQL',
                        'query' => 'query {
                          pages(filter: {title: _term_}) {
                            data {
                              id
                              title
                              latest {
                                data
                              }
                            }
                          }
                        }',
                        'list-key' => 'pages/data',
                        'item-title' => 'title',
                        'item-value' => 'id',
                    ],
                    'limit' => [
                        'type' => 'number',
                        'min' => 1,
                        'max' => 100,
                        'default' => 10,
                    ],
                ],
            ],
            'hero' => [
                'group' => 'content',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6,2H18A2,2 0 0,1 20,4V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V4A2,2 0 0,1 6,2M6,4V8H18V4H6Z" /></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                        'min' => 1,
                    ],
                    'subtitle' => [
                        'type' => 'string',
                    ],
                    'text' => [
                        'type' => 'markdown',
                    ],
                    'url' => [
                        'type' => 'url',
                    ],
                    'button' => [
                        'type' => 'string',
                    ],
                    'file' => [
                        'type' => 'image',
                        'label' => 'image',
                    ],
                ],
            ],
            'cards' => [
                'group' => 'content',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 16V6H7V16H21M21 4C21.53 4 22.04 4.21 22.41 4.59C22.79 4.96 23 5.47 23 6V16C23 16.53 22.79 17.04 22.41 17.41C22.04 17.79 21.53 18 21 18H7C5.89 18 5 17.1 5 16V6C5 4.89 5.89 4 7 4H21M3 20H18V22H3C2.47 22 1.96 21.79 1.59 21.41C1.21 21.04 1 20.53 1 20V9H3V20Z" /></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                    ],
                    'cards' => [
                        'type' => 'items',
                        'item' => [
                            'title' => [
                                'type' => 'string',
                                'min' => 1,
                            ],
                            'file' => [
                                'type' => 'image',
                                'label' => 'image',
                            ],
                            'text' => [
                                'type' => 'text',
                            ],
                        ],
                    ],
                ],
            ],
            'questions' => [
                'group' => 'content',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M18,15H6L2,19V3A1,1 0 0,1 3,2H18A1,1 0 0,1 19,3V14A1,1 0 0,1 18,15M23,9V23L19,19H8A1,1 0 0,1 7,18V17H21V8H22A1,1 0 0,1 23,9M8.19,4C7.32,4 6.62,4.2 6.08,4.59C5.56,5 5.3,5.57 5.31,6.36L5.32,6.39H7.25C7.26,6.09 7.35,5.86 7.53,5.7C7.71,5.55 7.93,5.47 8.19,5.47C8.5,5.47 8.76,5.57 8.94,5.75C9.12,5.94 9.2,6.2 9.2,6.5C9.2,6.82 9.13,7.09 8.97,7.32C8.83,7.55 8.62,7.75 8.36,7.91C7.85,8.25 7.5,8.55 7.31,8.82C7.11,9.08 7,9.5 7,10H9C9,9.69 9.04,9.44 9.13,9.26C9.22,9.08 9.39,8.9 9.64,8.74C10.09,8.5 10.46,8.21 10.75,7.81C11.04,7.41 11.19,7 11.19,6.5C11.19,5.74 10.92,5.13 10.38,4.68C9.85,4.23 9.12,4 8.19,4M7,11V13H9V11H7M13,13H15V11H13V13M13,4V10H15V4H13Z" /></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                    ],
                    'items' => [
                        'type' => 'items',
                        'item' => [
                            'title' => [
                                'type' => 'string',
                                'min' => 1,
                            ],
                            'text' => [
                                'type' => 'markdown',
                                'min' => 1,
                            ],
                        ],
                    ],
                ],
            ],
            'contact' => [
                'group' => 'forms',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M13 19C13 18.66 13.04 18.33 13.09 18H4V8L12 13L20 8V13.09C20.72 13.21 21.39 13.46 22 13.81V6C22 4.9 21.1 4 20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H13.09C13.04 19.67 13 19.34 13 19M20 6L12 11L4 6H20M20 22V20H16V18H20V16L23 19L20 22Z" /></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ],

        'meta' => [
            'meta-tags' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21.35,11.1H12.18V13.83H18.69C18.36,17.64 15.19,19.27 12.19,19.27C8.36,19.27 5,16.25 5,12C5,7.9 8.2,4.73 12.2,4.73C15.29,4.73 17.1,6.7 17.1,6.7L19,4.72C19,4.72 16.56,2 12.1,2C6.42,2 2.03,6.8 2.03,12C2.03,17.05 6.16,22 12.25,22C17.6,22 21.5,18.33 21.5,12.91C21.5,11.76 21.35,11.1 21.35,11.1V11.1Z" /></svg>',
                'fields' => [
                    'description' => [
                        'type' => 'string',
                        'min' => 1,
                        'max' => 160,
                    ],
                    'keywords' => [
                        'type' => 'string',
                        'max' => 255,
                    ],
                ],
            ],
            'social-media' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z" /></svg>',
                'fields' => [
                    'title' => [
                        'type' => 'string',
                        'min' => 1,
                        'max' => 60,
                    ],
                    'description' => [
                        'type' => 'string',
                        'max' => 160,
                    ],
                    'file' => [
                        'type' => 'image',
                        'label' => 'image',
                    ],
                ],
            ],
            'canonical' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M5,6.41L6.41,5L17,15.59V9H19V19H9V17H15.59L5,6.41Z" /></svg>',
                'fields' => [
                    'url' => [
                        'type' => 'url',
                        'required' => true,
                    ],
                ],
            ],
        ],

        'config' => [
            'logo' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z" /></svg>',
                'fields' => [
                    'file' => [
                        'type' => 'image',
                        'required' => true,
                    ],
                ]
            ],
            'icon' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14,6L10.25,11L13.1,14.8L11.5,16C9.81,13.75 7,10 7,10L1,18H23L14,6Z" /></svg>',
                'fields' => [
                    'file' => [
                        'type' => 'image',
                        'required' => true,
                    ],
                ]
            ],
            'theme' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19,19L12,11V19H5L12,11V5H19M19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3Z" /></svg>',
                'fields' => [
                    '--pico-font-family-sans-serif' => ['type' => 'string', 'default' => '"Helvetica Neue", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, Arial, sans-serif'],
                    '--pico-color' => ['type' => 'color', 'default' => '#FFFFFFD0'],
                    '--pico-background-color' => ['type' => 'color', 'default' => '#080040'],
                    '--pico-text-selection-color' => ['type' => 'color', 'default' => '#0098e840'],
                    '--pico-contrast' => ['type' => 'color', 'default' => '#FFFFFFE0'],
                    '--pico-contrast-hover' => ['type' => 'color', 'default' => '#FFFFFF'],
                    '--pico-contrast-inverse' => ['type' => 'color', 'default' => '#000000'],
                    '--pico-primary' => ['type' => 'color', 'default' => '#0868D0'],
                    '--pico-primary-background' => ['type' => 'color', 'default' => '#0868D080'],
                    '--pico-primary-hover' => ['type' => 'color', 'default' => '#1080FF'],
                    '--pico-primary-hover-background' => ['type' => 'color', 'default' => '#1080FF80'],
                    '--pico-secondary' => ['type' => 'color', 'default' => '#B008C8'],
                    '--pico-secondary-background' => ['type' => 'color', 'default' => '#B008C880'],
                    '--pico-secondary-hover' => ['type' => 'color', 'default' => '#E010FF'],
                    '--pico-secondary-hover-background' => ['type' => 'color', 'default' => '#E010FF80'],
                    '--pico-border-radius' => ['type' => 'string', 'default' => '0'],
                    '--pico-nav-breadcrumb-divider' => ['type' => 'string', 'default' => '>'],
                ],
            ],
            'styles' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5,3L4.35,6.34H17.94L17.5,8.5H3.92L3.26,11.83H16.85L16.09,15.64L10.61,17.45L5.86,15.64L6.19,14H2.85L2.06,18L9.91,21L18.96,18L20.16,11.97L20.4,10.76L21.94,3H5Z" /></svg>',
                'fields' => [
                    'text' => [
                        'type' => 'plaintext',
                    ],
                ],
            ],
            'javascript' => [
                'group' => 'basic',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M3,3H21V21H3V3M7.73,18.04C8.13,18.89 8.92,19.59 10.27,19.59C11.77,19.59 12.8,18.79 12.8,17.04V11.26H11.1V17C11.1,17.86 10.75,18.08 10.2,18.08C9.62,18.08 9.38,17.68 9.11,17.21L7.73,18.04M13.71,17.86C14.21,18.84 15.22,19.59 16.8,19.59C18.4,19.59 19.6,18.76 19.6,17.23C19.6,15.82 18.79,15.19 17.35,14.57L16.93,14.39C16.2,14.08 15.89,13.87 15.89,13.37C15.89,12.96 16.2,12.64 16.7,12.64C17.18,12.64 17.5,12.85 17.79,13.37L19.1,12.5C18.55,11.54 17.77,11.17 16.7,11.17C15.19,11.17 14.22,12.13 14.22,13.4C14.22,14.78 15.03,15.43 16.25,15.95L16.67,16.13C17.45,16.47 17.91,16.68 17.91,17.26C17.91,17.74 17.46,18.09 16.76,18.09C15.93,18.09 15.45,17.66 15.09,17.06L13.71,17.86Z" /></svg>',
                'fields' => [
                    'text' => [
                        'type' => 'plaintext',
                    ],
                ],
            ],
        ]
    ],
];