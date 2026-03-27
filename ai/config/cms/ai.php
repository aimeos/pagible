<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI providers
    |--------------------------------------------------------------------------
    |
    | Use the AI providers defined in ./config/prism.php to generate content
    | for pages and elements. You can use any other provider that is supported
    | by Prism/Prisma.
    |
    */
    'maxtoken' => env( 'CMS_AI_MAXTOKEN' ), // maximum tokens per request

    'write' => [ // Generate text content based on prompts
        'provider' => env( 'CMS_AI_WRITE', 'gemini' ),
        'model' => env( 'CMS_AI_WRITE_MODEL', 'gemini-2.5-flash' ),
        'api_key' => env( 'CMS_AI_WRITE_API_KEY' ),
    ],
    'refine' => [ // Return content in a defined structure
        'provider' => env( 'CMS_AI_REFINE', 'gemini' ),
        'model' => env( 'CMS_AI_REFINE_MODEL', 'gemini-2.5-flash' ),
        'api_key' => env( 'CMS_AI_REFINE_API_KEY' ),
    ],
    'describe' => [ // Generate summary of file content
        'provider' => env( 'CMS_AI_DESCRIBE', 'gemini' ),
        'model' => env( 'CMS_AI_DESCRIBE_MODEL', 'gemini-2.5-flash' ),
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
        'model' => env( 'CMS_AI_IMAGINE_MODEL', 'gemini-2.5-flash-image' ),
        'api_key' => env( 'CMS_AI_IMAGINE_API_KEY' ),
    ],
    'inpaint' => [ // Change selected parts of images based on prompt
        'provider' => env( 'CMS_AI_INPAINT', 'gemini' ),
        'model' => env( 'CMS_AI_INPAINT_MODEL', 'gemini-2.5-flash-image' ),
        'api_key' => env( 'CMS_AI_INPAINT_API_KEY' ),
    ],
    'isolate' => [ // Remove background from images
        'provider' => env( 'CMS_AI_ISOLATE', 'clipdrop' ),
        'model' => env( 'CMS_AI_ISOLATE_MODEL' ),
        'api_key' => env( 'CMS_AI_ISOLATE_API_KEY' ),
    ],
    'repaint' => [ // Change image based on prompt
        'provider' => env( 'CMS_AI_REPAINT', 'gemini' ),
        'model' => env( 'CMS_AI_REPAINT_MODEL', 'gemini-2.5-flash-image' ),
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
        'model' => env( 'CMS_AI_TRANSCRIBE_MODEL', 'whisper-1' ),
        'api_key' => env( 'CMS_AI_TRANSCRIBE_API_KEY' ),
    ],
];
