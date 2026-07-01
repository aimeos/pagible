<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\Events\Queried;
use Aimeos\Cms\Events\Searched;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;


class CmsMetricCard extends CmsCard
{
    /**
     * @var array<string, array{
     *     title: non-empty-string,
     *     type: non-empty-string,
     *     group?: non-empty-string,
     *     aggregates?: 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'>,
     *     details?: list<string>,
     *     success?: bool,
     *     events?: list<non-empty-string>
     * }>
     */
    private const METRICS = [
        'page' => [
            'title' => 'Pages',
            'type' => 'cms_page',
            'aggregates' => ['count', 'sum'],
            'details' => ['domain'],
        ],
        'element' => [
            'title' => 'Elements',
            'type' => 'cms_element',
            'aggregates' => ['count', 'sum'],
            'details' => ['source'],
        ],
        'file' => [
            'title' => 'Files',
            'type' => 'cms_file',
            'aggregates' => ['count', 'sum'],
            'details' => ['mime', 'source'],
        ],
        'auth' => [
            'title' => 'Authentication',
            'type' => 'cms_auth',
            'events' => [Authed::class],
        ],
        'ai' => [
            'title' => 'AI',
            'type' => 'cms_ai',
            'group' => 'mutation',
            'aggregates' => Metric::LATENCY,
            'details' => ['provider', 'model'],
            'success' => true,
            'events' => [Generated::class],
        ],
        'search' => [
            'title' => 'Search',
            'type' => 'cms_search',
            'group' => 'action',
            'aggregates' => Metric::LATENCY,
            'details' => ['domain', 'lang'],
            'events' => [Searched::class],
        ],
        'contact' => [
            'title' => 'Contact',
            'type' => 'cms_contact',
            'aggregates' => Metric::LATENCY,
            'events' => [Contacted::class],
        ],
        'jsonapi' => [
            'title' => 'JSON:API',
            'type' => 'cms_jsonapi',
            'aggregates' => Metric::LATENCY,
            'details' => ['domain'],
            'events' => [Queried::class],
        ],
    ];

    public string $metric = 'page';


    public function render() : View
    {
        $definition = $this->definition( $this->metric );

        return ViewFactory::make( 'cms-pulse::cms-metric-card', [
            'title' => $definition['title'],
            'entries' => $this->summary(
                $definition['type'],
                $definition['aggregates'] ?? 'count',
                $definition['group'] ?? 'action',
                $definition['details'] ?? [],
                $definition['success'] ?? false,
            ),
        ] );
    }


    /**
     * @return array<string, array<string, mixed>>
     */
    public static function available() : array
    {
        return array_filter( self::METRICS, fn( array $definition ) => self::eventsAvailable( $definition ) );
    }


    /**
     * @return array<string, mixed>
     */
    protected function definition( string $metric ) : array
    {
        return self::METRICS[$metric] ?? self::METRICS['page'];
    }


    /**
     * @param array<string, mixed> $definition
     */
    protected static function eventsAvailable( array $definition ) : bool
    {
        foreach( (array) ( $definition['events'] ?? [] ) as $event )
        {
            if( !is_string( $event ) || !class_exists( $event ) ) {
                return false;
            }
        }

        return true;
    }
}
