<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('relocate-file')]
#[Title('Change file protection')]
#[Description('Moves a managed file and all of its previews and versions between public and private storage. Private files are delivered through page access checks. Remote hot-linked files cannot be relocated.')]
class RelocateFile extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:relocate', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate( [
            'id' => 'required|string|max:36',
            'disk' => 'required|string|in:public,private',
        ], [
            'id.required' => 'You must specify the file ID.',
            'disk.required' => 'You must specify "public" or "private" storage.',
        ] );

        $file = Resource::relocateFiles( [$v['id']], $v['disk'], $request->user() )->firstOrFail();

        return Response::structured( [
            'id' => $file->id,
            'disk' => $file->disk,
            'path' => $file->path,
            'previews' => $file->previews,
        ] );
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
                ->description('The UUID of the file to relocate.')
                ->required(),
            'disk' => $schema->string()
                ->enum( ['public', 'private'] )
                ->description('Target storage: "public" or "private".')
                ->required(),
        ];
    }


    /**
     * Determine if the tool should be registered.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'file:relocate', $request->user() );
    }
}
