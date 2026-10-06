<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Aimeos\Prisma\Files\Image;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('generate-image')]
#[Title('Generate an image from a prompt')]
#[Description('Generates a new image from a text prompt using AI and stores it as a new draft media file.
Optionally pass IDs of existing image files as visual references. Returns the created file including its ID and preview URLs.')]
class GenerateImage extends Tool
{
    use HandlesMedia;


    protected const PERMISSIONS = ['image:imagine', 'file:add'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate( [
            'prompt' => 'required|string|max:2000',
            'context' => 'string|max:2000',
            'files' => 'array|max:10',
            'files.*' => 'string|max:36',
            'name' => 'string|max:255',
            'lang' => 'nullable|string|max:5',
            'description' => 'array',
        ], [
            'prompt.required' => 'You must provide a prompt describing the image to generate.',
        ] );

        $base64 = Ai::imagine( $v['prompt'], Ai::files( $v['files'] ?? [], Image::class ), $v['context'] ?? null );

        return Response::structured( $this->store(
            $base64, $v['name'] ?? 'generated-image', $v['lang'] ?? null, $v['description'] ?? null, $request->user()
        ) );
    }


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
                ->description( 'Optional UUIDs of existing image files to use as visual references.' ),
            'name' => $schema->string()
                ->description( 'Display name for the new file. Defaults to "generated-image".' ),
            'lang' => $schema->string()
                ->description( 'ISO language code for the file, e.g., "en" or "de".' ),
            'description' => $schema->object()
                ->description( 'Multilingual alt text, e.g., {"en": "A blue hero banner"}.' ),
        ];
    }
}
