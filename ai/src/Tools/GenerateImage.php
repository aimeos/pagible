<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Concerns\ObservesPrisma;
use Aimeos\Prisma\Prisma;
use Aimeos\Cms\Permission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('generate-image')]
#[Title('Generate an image from a prompt')]
#[Description('Generates a new image from a text prompt using AI and stores it as a new draft media file.
Optionally pass IDs of existing image files as visual references. Returns the created file including its ID and preview URLs.')]
class GenerateImage extends Tool
{
    use HandlesMedia;
    use ObservesPrisma;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'image:imagine', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

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

        $prompt = $v['prompt'] . ( !empty( $v['context'] ) ? "\n\n" . $v['context'] : '' );
        $options = ['size' => ['1536x1024', '1792x1024', '1024x1024']];

        $provider = config( 'cms.ai.imagine.provider' );
        $config = config( 'cms.ai.imagine', [] );
        $model = config( 'cms.ai.imagine.model' );

        $base64 = Prisma::image()->observe( $this->observer( \Aimeos\Cms\Utils::editor( $request->user() ) ) )
            ->using( $provider, $config )
            ->model( $model )
            ->ensure( 'imagine' )
            ->imagine( $prompt, $this->images( $v['files'] ?? [] ), $options ) // @phpstan-ignore-line method.notFound
            ->base64();

        return Response::structured( $this->store(
            (string) $base64, $v['name'] ?? 'generated-image', $v['lang'] ?? null, $v['description'] ?? null, $request->user()
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


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'image:imagine', $request->user() );
    }
}
