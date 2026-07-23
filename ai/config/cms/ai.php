<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum AI input
    |--------------------------------------------------------------------------
    |
    | Maximum serialized size in bytes accepted from AI API callers before it
    | is sent to a provider, and the nesting depth of structured content.
    |
    */
    'maxinput' => (int) env( 'CMS_AI_MAXINPUT', 1024 * 1024 ),
    'maxdepth' => (int) env( 'CMS_AI_MAXDEPTH', 20 ),

    /*
    |--------------------------------------------------------------------------
    | Maximum tokens
    |--------------------------------------------------------------------------
    |
    | Maximum number of tokens an AI provider may generate per request. Caps the
    | length (and cost) of generated content; leave empty to use the
    | provider/model default.
    |
    */
    'maxtoken' => env( 'CMS_AI_MAXTOKEN' ),

    /*
    |--------------------------------------------------------------------------
    | Request timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds an AI request may run. Applied both to the HTTP
    | client talking to the provider and to the PHP execution time of the
    | request handling it, so long content generations and chat streams are not
    | killed prematurely by PHP's default 30s limit.
    |
    */
    'timeout' => (int) env( 'CMS_AI_TIMEOUT', 300 ),

    /*
    |--------------------------------------------------------------------------
    | Chat route middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to the "cmsapi/chat" streaming route. Throttled by
    | default; multi-tenant setups (e.g. stancl/tenancy) must also add their
    | tenancy-init middleware here so Tenancy::value() resolves to the right
    | tenant for the AI tool calls (page reads/creation) during the stream.
    |
    */
    'middleware' => ['web', 'throttle:cms-ai'],

    /*
     |----------------------------------------------------------------------
    | AI tools
    |--------------------------------------------------------------------------
    |
    | Define the AI tools used for content generation. Each tool has a provider,
    | model, and API key. The base URL for the provider is optional. Use the AI
    | providers defined in ./config/prism.php or any other provider supported by
    | Prism/Prisma.
    |
    */
    'write' => [ // Generate text content based on prompts
        'provider' => env( 'CMS_AI_WRITE', 'gemini' ),
        'model' => env( 'CMS_AI_WRITE_MODEL' ),
        'api_key' => env( 'CMS_AI_WRITE_API_KEY' ),
    ],
    'refine' => [ // Return content in a defined structure
        'provider' => env( 'CMS_AI_REFINE', 'openai' ),
        'model' => env( 'CMS_AI_REFINE_MODEL' ),
        'api_key' => env( 'CMS_AI_REFINE_API_KEY' ),
    ],
    'describe' => [ // Generate summary of file content
        'provider' => env( 'CMS_AI_DESCRIBE', 'gemini' ),
        'model' => env( 'CMS_AI_DESCRIBE_MODEL' ),
        'api_key' => env( 'CMS_AI_DESCRIBE_API_KEY' ),
    ],
    'translate' => [ // Translate text content
        'provider' => env( 'CMS_AI_TRANSLATE', 'deepl' ),
        'model' => env( 'CMS_AI_TRANSLATE_MODEL' ),
        'api_key' => env( 'CMS_AI_TRANSLATE_API_KEY' ),
        'url' => env( 'CMS_AI_TRANSLATE_URL' ),
    ],

    'erase' => [ // Remove selected parts of images
        'provider' => env( 'CMS_AI_ERASE', 'clipdrop' ),
        'model' => env( 'CMS_AI_ERASE_MODEL' ),
        'api_key' => env( 'CMS_AI_ERASE_API_KEY' ),
    ],
    'imagine' => [ // Generate images from text prompts
        'provider' => env( 'CMS_AI_IMAGINE', 'gemini' ),
        'model' => env( 'CMS_AI_IMAGINE_MODEL' ),
        'api_key' => env( 'CMS_AI_IMAGINE_API_KEY' ),
    ],
    'inpaint' => [ // Change selected parts of images based on prompt
        'provider' => env( 'CMS_AI_INPAINT', 'gemini' ),
        'model' => env( 'CMS_AI_INPAINT_MODEL' ),
        'api_key' => env( 'CMS_AI_INPAINT_API_KEY' ),
    ],
    'isolate' => [ // Remove background from images
        'provider' => env( 'CMS_AI_ISOLATE', 'clipdrop' ),
        'model' => env( 'CMS_AI_ISOLATE_MODEL' ),
        'api_key' => env( 'CMS_AI_ISOLATE_API_KEY' ),
    ],
    'repaint' => [ // Change image based on prompt
        'provider' => env( 'CMS_AI_REPAINT', 'gemini' ),
        'model' => env( 'CMS_AI_REPAINT_MODEL' ),
        'api_key' => env( 'CMS_AI_REPAINT_API_KEY' ),
    ],
    'uncrop' => [ // Extend images
        'provider' => env( 'CMS_AI_UNCROP', 'clipdrop' ),
        'model' => env( 'CMS_AI_UNCROP_MODEL' ),
        'api_key' => env( 'CMS_AI_UNCROP_API_KEY' ),
    ],
    'upscale' => [ // Upscale images
        'provider' => env( 'CMS_AI_UPSCALE', 'clipdrop' ),
        'model' => env( 'CMS_AI_UPSCALE_MODEL' ),
        'api_key' => env( 'CMS_AI_UPSCALE_API_KEY' ),
    ],

    'transcribe' => [ // Transcribe audio
        'provider' => env( 'CMS_AI_TRANSCRIBE', 'openai' ),
        'model' => env( 'CMS_AI_TRANSCRIBE_MODEL' ),
        'api_key' => env( 'CMS_AI_TRANSCRIBE_API_KEY' ),
    ],
];
