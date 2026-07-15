<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Pulse\CmsMetricCard;
use Illuminate\Support\Collection;


class TestingCmsCard extends CmsMetricCard
{
    /**
     * @var list<array{type: string, aggregates: 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'>, orderBy: string|null, direction: string, limit: int}>
     */
    public array $aggregateCalls = [];

    /**
     * @var Collection<int, object>
     */
    protected Collection $aggregateRows;


    /**
     * @param Collection<int, object>|null $aggregateRows
     */
    public function __construct( ?Collection $aggregateRows = null )
    {
        $this->aggregateRows = $aggregateRows ?? collect();
    }


    /**
     * @return Collection<int, object>
     */
    public function rows( string $type ) : Collection
    {
        return $this->decoded( $type );
    }


    /**
     * @return Collection<int, object{label: non-empty-string, count: int<0, max>, avg: float|null, max: mixed, detail: string}&\stdClass>
     */
    public function summaries( string $type, string $group ) : Collection
    {
        return $this->summary( $type, $group );
    }


    /**
     * @param 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'> $aggregates
     * @return Collection<int, object>
     */
    protected function aggregate( string $type, string|array $aggregates, ?string $orderBy = 'count',
        string $direction = 'desc', int $limit = 101 ) : Collection
    {
        $this->aggregateCalls[] = compact( 'type', 'aggregates', 'orderBy', 'direction', 'limit' );

        return $this->aggregateRows;
    }
}
