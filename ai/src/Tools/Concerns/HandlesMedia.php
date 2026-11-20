<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools\Concerns;

use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Media;
use Aimeos\Cms\Tools\Presenter;
use Aimeos\Prisma\Files\Image;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;


/**
 * Edits images for the AI media MCP tools and stores them as new file versions.
 */
trait HandlesMedia
{
    /**
     * Upload name of the edited images.
     */
    private const NAME = 'image';


    /**
     * Validates the request, edits the image and stores it as new file version.
     *
     * @param Request $request Tool request
     * @param array<string, string> $rules Additional validation rules
     * @param array<string, string> $messages Additional validation messages
     * @param \Closure(Image, array<string, mixed>, Image): string $edit Gets image, parameters and mask (or image), returns base64
     * @return ResponseFactory Updated file
     */
    protected function edit( Request $request, array $rules, array $messages, \Closure $edit ) : ResponseFactory
    {
        $v = $request->validate( ['file' => 'required|string|max:36'] + $rules + ['latest_id' => 'required|string|max:36'], $messages + [
            'file.required' => 'You must specify the UUID of the image file to edit.',
            'latest_id.required' => 'You must pass the latest_id returned by get-file, add-file, or a previous save-file so concurrent edits are detected.',
        ] );

        // loaded together so both count against the size limit
        $images = Media::load( array_values( array_filter( [$v['file'], $v['mask'] ?? null] ) ), $request->user() );
        $base64 = $edit( $images[0], $v, $images[1] ?? $images[0] );

        $result = Media::store( $base64, self::NAME, function( UploadedFile $upload ) use ( $v, $request ) {
            $file = Files::save( $v['file'], [], $request->user(), $v['latest_id'], $upload );
            return Presenter::saved( Presenter::file( $file ), $file );
        } );

        return Response::structured( $result );
    }


    /**
     * Like edit() but with a required mask.
     *
     * @param Request $request Tool request
     * @param array<string, string> $rules Additional validation rules
     * @param array<string, string> $messages Additional validation messages
     * @param \Closure(Image, Image, array<string, mixed>): string $edit Gets image, mask and parameters, returns base64
     * @return ResponseFactory Updated file
     */
    protected function masked( Request $request, array $rules, array $messages, \Closure $edit ) : ResponseFactory
    {
        return $this->edit( $request, ['mask' => 'required|string|max:36|different:file'] + $rules, $messages + [
            'mask.required' => 'You must specify the UUID of the mask image file.',
            'mask.different' => 'The mask must be another image file than the one to edit.',
        ], fn( Image $image, array $v, Image $mask ) => $edit( $image, $mask, $v ) );
    }


    /**
     * Returns the input schema with file, optional mask, tool parameters and latest_id.
     *
     * @param JsonSchema $schema Schema builder
     * @param array<string, \Illuminate\JsonSchema\Types\Type> $params Additional parameters
     * @param string $file Description of "file"
     * @param string|null $mask Description of "mask" or NULL for no mask
     * @return array<string, \Illuminate\JsonSchema\Types\Type> Input schema
     */
    protected function media( JsonSchema $schema, array $params = [], string $file = 'The UUID of the image file to edit.', ?string $mask = null ) : array
    {
        return ['file' => $schema->string()->description( $file )->required()]
            + ( $mask !== null ? ['mask' => $schema->string()->description( $mask )->required()] : [] )
            + $params
            + ['latest_id' => $schema->string()
                ->description( 'Required. The latest_id value returned by get-file, add-file, or your previous save-file for this file. Ensures edits made by another editor in the meantime are merged instead of overwritten.' )
                ->required()];
    }
}
