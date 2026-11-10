<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Media;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


#[Name('generate-image')]
#[Title('Generate an image from a prompt')]
#[Description('Generates a new image from a text prompt using AI and stores it as a new draft media file.
Optionally pass IDs of existing image files as visual references. Returns the created file including its ID and preview URLs.')]
class GenerateImage extends Tool
{
    protected const PERMISSIONS = ['image:imagine', 'file:add'];

    /**
     * Name of the new file if none is passed.
     */
    private const NAME = 'generated-image';


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'prompt' => $schema->string()
                ->description( 'Describe the image to generate, e.g., "A minimalistic hero banner with abstract blue shapes".' )
                ->required(),
            'context' => $schema->string()
                ->description( 'Additional context such as style, mood or brand guidelines.' ),
            'files' => $schema->array()
                ->items( $schema->string() )
                ->description( 'Optional UUIDs of existing JPEG, PNG or WebP image files to use as visual references.' ),
            'name' => $schema->string()
                ->description( 'Display name for the new file. Defaults to "' . self::NAME . '".' ),
            'lang' => $schema->string()
                ->description( 'ISO language code for the file, e.g., "en" or "de".' ),
            'description' => $schema->object()
                ->description( 'Multilingual alt text, e.g., {"en": "A blue hero banner"}.' ),
        ];
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        $v = $request->validate( [
            'prompt' => 'required|string|max:2000',
            'context' => 'nullable|string|max:2000',
            'files' => 'array|max:10',
            'files.*' => 'string|max:36',
            'name' => 'nullable|string|max:255',
            'lang' => 'nullable|string|max:10|cms_locale',
            'description' => 'array',
        ], [
            'prompt.required' => 'You must provide a prompt describing the image to generate.',
        ] );

        $base64 = Ai::imagine( $v['prompt'], Media::load( $v['files'] ?? [], $request->user() ), $v['context'] ?? null );

        $data = array_filter( ['lang' => $v['lang'] ?? null, 'description' => $v['description'] ?? null] );

        return Response::structured( Media::store( $base64, $v['name'] ?? self::NAME, fn( UploadedFile $upload ) => Presenter::item(
            Files::add( $upload, $data, $request->user() )
        ) ) );
    }
}
