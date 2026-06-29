<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Concerns\Watch;
use Aimeos\Cms\Permission;
use Aimeos\Prisma\Prisma;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('isolate-image')]
#[Title('Remove the background of an image')]
#[Description('Removes the background of an existing image using AI and stores the result as a new draft version of the same file. Returns the updated file.')]
class IsolateImage extends Tool
{
    use HandlesMedia;
    use Watch;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'image:isolate', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate( [
            'file' => 'required|string|max:36',
            'latestId' => 'string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the image file to isolate.',
        ] );

        if( !( $image = $this->image( $v['file'] ) ) ) {
            return Response::structured( ['error' => 'Image file not found or not an image.'] );
        }

        $provider = config( 'cms.ai.isolate.provider' );
        $config = config( 'cms.ai.isolate', [] );
        $model = config( 'cms.ai.isolate.model' );

        $base64 = Prisma::image()
            ->observe( $this->observer( \Aimeos\Cms\Utils::editor( $request->user() ) ) )
            ->using( $provider, $config )
            ->model( $model )
            ->ensure( 'isolate' )
            ->isolate( $image, $config ) // @phpstan-ignore-line method.notFound
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
                ->description( 'The UUID of the image file whose background to remove.' )
                ->required(),
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
        return Permission::can( 'image:isolate', $request->user() );
    }
}
