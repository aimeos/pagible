<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\File;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('restore-file')]
#[Title('Restore a soft-deleted media file')]
#[Description('Restores a previously soft-deleted media file. Returns the restored file as a JSON object.')]
class RestoreFile extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:keep', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the file to restore.',
        ] );

        /** @var File|null $file */
        $file = File::withTrashed()->select( 'id', 'deleted_at' )->find( $v['id'] );

        if( !$file ) {
            return Response::structured( ['error' => 'File not found.'] );
        }

        if( !$file->trashed() ) {
            return Response::structured( ['error' => 'File is not deleted.'] );
        }

        $items = Resource::restore( File::class, [$v['id']], Utils::editor( $request->user() ) );

        return Response::structured( $items->firstOrFail()->toArray() );
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
                ->description('The UUID of the soft-deleted file to restore.')
                ->required(),
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
        return Permission::can( 'file:keep', $request->user() );
    }
}
