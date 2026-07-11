<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests {

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Pulse\CmsCard;
use Aimeos\Cms\PulseServiceProvider;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Orchestra\Testbench\TestCase;


abstract class PulseTestCase extends TestCase
{
    protected FakePulse $pulse;


    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class, PulseServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
        $app['config']->set( 'app.key', 'base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF' );
    }


    protected function setUp() : void
    {
        Tenancy::$access = null;
        Tenancy::$callback = null;

        parent::setUp();

        $this->pulse = new FakePulse;
        $this->application()->instance( \Laravel\Pulse\Pulse::class, $this->pulse );
    }


    protected function tearDown() : void
    {
        Tenancy::$access = null;
        Tenancy::$callback = null;

        parent::tearDown();
    }


    protected function application() : Application
    {
        if( !$this->app ) {
            throw new \RuntimeException( 'Application is not initialized.' );
        }

        return $this->app;
    }
}


class FakePulse
{
    /**
     * @var list<FakePulseEntry>
     */
    public array $entries = [];

    /**
     * @var array<class-string, bool>
     */
    public array $recorders = [];


    public function record( string $type, string $key, ?int $value = null ) : FakePulseEntry
    {
        return $this->entries[] = new FakePulseEntry( $type, $key, $value );
    }


    /**
     * @param array<class-string, bool> $recorders
     */
    public function register( array $recorders ) : void
    {
        $this->recorders = $recorders;
    }
}


class FakePulseEntry
{
    /**
     * @var list<string>
     */
    public array $aggregates = [];


    public function __construct(
        public string $type,
        public string $key,
        public ?int $value = null,
    ) {}


    public function count() : self
    {
        $this->aggregates[] = 'count';
        return $this;
    }


    public function avg() : self
    {
        $this->aggregates[] = 'avg';
        return $this;
    }


    public function max() : self
    {
        $this->aggregates[] = 'max';
        return $this;
    }


    public function sum() : self
    {
        $this->aggregates[] = 'sum';
        return $this;
    }
}


class TestingCmsCard extends CmsCard
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
     * @param 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'> $aggregates
     * @return Collection<int, object>
     */
    public function rows( string $type, string|array $aggregates ) : Collection
    {
        return $this->decoded( $type, $aggregates );
    }


    /**
     * @param 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'> $aggregates
     * @return Collection<int, object{label: non-empty-string, count: int<0, max>, sum: int, avg: float|null, max: mixed, detail: string}&\stdClass>
     */
    public function summaries( string $type, string|array $aggregates, string $group ) : Collection
    {
        return $this->summary( $type, $aggregates, $group );
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
}
