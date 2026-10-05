<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('drop-file')]
#[Title('Soft-delete a media file')]
#[Description('Soft-deletes a media file. The file can be restored within the retention period. Returns the deleted file as a JSON object.')]
class DropFile extends Tool
{
    protected const PERMISSIONS = ['file:drop'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the file to delete.',
        ] );

        if( !( $item = Resource::drop( File::class, [$v['id']], $request->user() )->first() ) ) {
            return Response::structured( ['error' => 'File not found.'] );
        }

        return Response::structured( Presenter::item( $item ) );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->string()
                ->description('The UUID of the file to delete.')
                ->required(),
        ];
    }
}
