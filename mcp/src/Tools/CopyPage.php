<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('copy-page')]
#[Title('Copy a page with its sub-pages')]
#[Description('Copies a page and all its sub-pages including all language variants to a new position in the page tree. You can place the copy before a sibling, append it to a parent, or make it a root page. Element IDs stay the same, so translations of the copy stay in sync. Paths already in use get the language code appended. Returns the copied page in its source language as a JSON object.')]
class CopyPage extends Tool
{
    protected const PERMISSIONS = ['page:add'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
            'parent_id' => 'string|max:36',
            'before_id' => 'string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the page to copy.',
        ] );

        // copies of whole subtrees are expensive
        if( RateLimiter::tooManyAttempts( $key = 'cms-copy:' . $request->user()?->getAuthIdentifier(), 10 ) ) {
            throw new \Exception( sprintf( 'Too many copies, try again in %1$d seconds.', RateLimiter::availableIn( $key ) ) );
        }

        RateLimiter::hit( $key );
        $page = Resource::copyPage( $v['id'], $v['before_id'] ?? null, $v['parent_id'] ?? null, $request->user() );

        return Response::structured( Presenter::item( $page, true ) );
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
                ->description( 'The UUID of the page to copy.' )
                ->required(),
            'parent_id' => $schema->string()
                ->description( 'ID of the parent page the copy is appended to. Omit to copy as root page unless before_id is set.' ),
            'before_id' => $schema->string()
                ->description( 'ID of a sibling page the copy is inserted before. Takes priority over parent_id positioning.' ),
        ];
    }
}
