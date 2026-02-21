<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\AnalyticsBridge\Facades\Analytics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[IsOpenWorld]
#[Name('google-queries')]
#[Title('Get Google Query Statistics')]
#[Description('Returns the queries entered by users, including impressions, clicks, click-through rates (ctr), and position in Google search results to analyse and optimize the page specified by the URL for SEO.')]
class GoogleQueries extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request )
    {
        if( !Permission::can( 'page:view', $request->user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'path' => 'string|max:255',
        ], [
            'domain.required' => 'You must specify a domain to get the user queries for. For example, "example.com".',
            'path.max' => 'The path to get the user queries for must not exceed 255 characters. For example, "blog/laravel-cms".',
        ] );

        $url = 'https://' . $validated['domain'] . '/' . trim( $validated['path'], '/' );
        $queries = Analytics::queries( $url );

        foreach( $queries as &$entry )
        {
            // Rename 'key' to 'query' for better understanding by the LLM
            $entry['query'] = $entry['key'];
            unset( $entry['key'] );
        }

        return Response::structured( $queries );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'domain' => $schema->string()
                ->description('The domain of the page to get the user queries for, e.g., "example.com".')
                ->required(),
            'path' => $schema->string()
                ->description('The relative path of the page to get the user queries for, e.g., "blog/laravel-cms".'),
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
        return Permission::can( 'page:view', $request->user() );
    }
}
