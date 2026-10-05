<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('isolate-image')]
#[Title('Remove the background of an image')]
#[Description('Removes the background of an existing image using AI and stores the result as a new draft version of the same file. Returns the updated file.')]
class IsolateImage extends Tool
{
    use HandlesMedia;


    protected const PERMISSIONS = ['image:isolate', 'file:save', 'file:view'];


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
            'latest_id' => $schema->string()
                ->description( 'Required. The latest_id value returned by get-file, add-file, or your previous save-file for this file. Ensures edits made by another editor in the meantime are merged instead of overwritten.' )
                ->required(),
        ];
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        $v = $request->validate( [
            'file' => 'required|string|max:36',
            'latest_id' => 'required|string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the image file to isolate.',
            'latest_id.required' => 'You must pass the latest_id returned by get-file, add-file, or a previous save-file so concurrent edits are detected.',
        ] );

        $image = $this->image( $v['file'] );
        $base64 = Ai::isolate( $image );

        return Response::structured( $this->update( $v['file'], $base64, $v['latest_id'], $request->user() ) );
    }
}
