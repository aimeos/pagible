<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\AnalyticsBridge\Facades\Analytics;
use Illuminate\Support\Facades\Cache;
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
#[Name('get-page-metrics')]
#[Title('Get page analytics and metrics')]
#[Description('Returns analytics data for a page URL including views, visits, conversions, bounce rate, page speed, search impressions, clicks, and top queries. Data is cached for 1 hour.')]
class GetPageMetrics extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:metrics', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'url' => 'required|string|max:500',
            'days' => 'integer|min:1|max:90',
        ], [
            'url.required' => 'You must specify the full URL of the page, e.g., "https://example.com/blog/my-article".',
        ] );

        $url = $v['url'];
        $days = $v['days'] ?? 30;
        $data = [];

        try {
            $data = (array) Cache::remember( "stats:$url:$days", 3600, fn() => Analytics::driver()->stats( $url, $days ) );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $e->getMessage();
        }

        try {
            $data = array_merge( $data, Cache::remember( "search:$url:$days", 3600, fn() => Analytics::search( $url, $days ) ) ?? [] );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $e->getMessage();
        }

        try {
            $queries = Cache::remember( "queries:$url:$days", 3600, fn() => Analytics::queries( $url, $days ) ) ?? [];

            foreach( $queries as &$entry ) {
                $entry['query'] = $entry['key'];
                unset( $entry['key'] );
            }

            $data['queries'] = $queries;
        } catch ( \Throwable $e ) {
            $data['errors'][] = $e->getMessage();
        }

        try {
            $data['pagespeed'] = Cache::remember( "pagespeed:$url", 3600, fn() => Analytics::pagespeed( $url ) );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $e->getMessage();
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


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'page:metrics', $request->user() );
    }
}
