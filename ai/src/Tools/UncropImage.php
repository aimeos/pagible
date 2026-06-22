<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Prisma\Prisma;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('uncrop-image')]
#[Title('Extend an image (outpaint)')]
#[Description('Extends an existing image outwards by the given number of pixels on each side using AI (outpainting) and stores the result as a new draft version of the same file. Returns the updated file.')]
class UncropImage extends Tool
{
    use HandlesMedia;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'image:uncrop', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate( [
            'file' => 'required|string|max:36',
            'top' => 'integer|min:0|max:4096',
            'right' => 'integer|min:0|max:4096',
            'bottom' => 'integer|min:0|max:4096',
            'left' => 'integer|min:0|max:4096',
            'latestId' => 'string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the image file to extend.',
        ] );

        if( !( $image = $this->image( $v['file'] ) ) ) {
            return Response::structured( ['error' => 'Image file not found or not an image.'] );
        }

        $provider = config( 'cms.ai.uncrop.provider' );
        $config = config( 'cms.ai.uncrop', [] );
        $model = config( 'cms.ai.uncrop.model' );

        $base64 = Prisma::image()
            ->using( $provider, $config )
            ->model( $model )
            ->ensure( 'uncrop' )
            ->uncrop( $image, $v['top'] ?? 0, $v['right'] ?? 0, $v['bottom'] ?? 0, $v['left'] ?? 0 ) // @phpstan-ignore-line method.notFound
            ->base64();

        return Response::structured( $this->update( $v['file'], (string) $base64, $v['latestId'] ?? null, $request->user() ) );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'file' => $schema->string()
                ->description( 'The UUID of the image file to extend.' )
                ->required(),
            'top' => $schema->integer()
                ->description( 'Pixels to add at the top.' ),
            'right' => $schema->integer()
                ->description( 'Pixels to add on the right.' ),
            'bottom' => $schema->integer()
                ->description( 'Pixels to add at the bottom.' ),
            'left' => $schema->integer()
                ->description( 'Pixels to add on the left.' ),
            'latestId' => $schema->string()
                ->description( 'Version ID the caller last retrieved. Enables conflict detection.' ),
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
        return Permission::can( 'image:uncrop', $request->user() );
    }
}
