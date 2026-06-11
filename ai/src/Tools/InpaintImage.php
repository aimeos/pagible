<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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


#[Name('inpaint-image')]
#[Title('Edit part of an image using a mask and prompt')]
#[Description('Replaces the masked area of an image based on a text prompt using AI and stores the result as a new draft version of the source file. Both the image and the mask are referenced by file ID. Returns the updated file.')]
class InpaintImage extends Tool
{
    use HandlesMedia;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'image:inpaint', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate( [
            'file' => 'required|string|max:36',
            'mask' => 'required|string|max:36',
            'prompt' => 'required|string|max:2000',
            'latestId' => 'string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the image file to edit.',
            'mask.required' => 'You must specify the UUID of the mask image file.',
            'prompt.required' => 'You must provide a prompt describing the desired content.',
        ] );

        if( !( $image = $this->image( $v['file'] ) ) ) {
            return Response::structured( ['error' => 'Image file not found or not an image.'] );
        }

        if( !( $mask = $this->image( $v['mask'] ) ) ) {
            return Response::structured( ['error' => 'Mask file not found or not an image.'] );
        }

        $provider = config( 'cms.ai.inpaint.provider' );
        $config = config( 'cms.ai.inpaint', [] );
        $model = config( 'cms.ai.inpaint.model' );

        $base64 = Prisma::image()
            ->using( $provider, $config )
            ->model( $model )
            ->ensure( 'inpaint' )
            ->inpaint( $image, $mask, $v['prompt'], $config ) // @phpstan-ignore-line method.notFound
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
                ->description( 'The UUID of the image file to edit.' )
                ->required(),
            'mask' => $schema->string()
                ->description( 'The UUID of the mask image file marking the area to replace.' )
                ->required(),
            'prompt' => $schema->string()
                ->description( 'Describe what to generate in the masked area.' )
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
        return Permission::can( 'image:inpaint', $request->user() );
    }
}
