<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Metrics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[IsOpenWorld]
#[Name('get-page-metrics')]
#[Title('Get page analytics and metrics')]
#[Description('Returns analytics data for a page URL including views, visits, conversions, bounce rate, page speed, search impressions, clicks, and top queries. Data is cached for 1 hour.')]
class GetPageMetrics extends Tool
{
    protected const PERMISSIONS = ['page:metrics'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'url' => 'required|string|max:500',
            'days' => 'integer|min:1|max:90',
        ], [
            'url.required' => 'You must specify the full URL of the page, e.g., "https://example.com/blog/my-article".',
        ] );

        $data = Metrics::get( $v['url'], $v['days'] ?? 30 );

        if( array_key_exists( 'queries', $data ) ) {
            $data['queries'] = array_map( function( $entry ) {
                $entry['query'] = $entry['key'] ?? null;
                unset( $entry['key'] );
                return $entry;
            }, $data['queries'] ?? [] );
        }

        return Response::structured( $data );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'url' => $schema->string()
                ->description('The full URL of the page to get metrics for, e.g., "https://example.com/blog/my-article".')
                ->required(),
            'days' => $schema->integer()
                ->description('Number of days to look back for analytics data (1-90, default: 30).'),
        ];
    }
}
