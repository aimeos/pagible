<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Permission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsIdempotent]
#[Name('set-page-access')]
#[Title('Set immediate frontend page access')]
#[Description('Immediately replaces frontend access for up to 50 pages. Use null for public access, an empty array for authenticated users, or named access values. Optionally applies the change to one root page and all descendants.')]
class SetPageAccess extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:publish', $request->user() )
            || !Permission::can( 'access:view', $request->user() )
        ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|array|min:1|max:50',
            'id.*' => 'string|max:36',
            'access' => 'present|nullable|array|max:250',
            'access.*' => 'string|max:100',
            'descendants' => 'boolean',
        ], [
            'id.required' => 'You must specify one or more page IDs.',
            'access.present' => 'You must specify access as null, an empty array, or named access values.',
        ] );

        $descendants = (bool) ( $v['descendants'] ?? false );
        $count = PageAccess::set( $v['id'], $v['access'], $request->user(), $descendants );

        return Response::structured( ['updated' => $count] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->array()
                ->items( $schema->string() )
                ->min( 1 )
                ->max( 50 )
                ->unique()
                ->description('One to 50 page UUIDs. With descendants, specify exactly one root page UUID.')
                ->required(),
            'access' => $schema->array()
                ->items( $schema->string() )
                ->max( 250 )
                ->unique()
                ->nullable()
                ->description('Required access state: null makes pages public, an empty array requires authentication, and named values grant matching users access.')
                ->required(),
            'descendants' => $schema->boolean()
                ->description('Apply the access state to the specified root page and all descendants.')
                ->default( false ),
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
        return Permission::can( 'page:publish', $request->user() )
            && Permission::can( 'access:view', $request->user() );
    }
}
