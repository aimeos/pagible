<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests {

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\PulseServiceProvider;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Pulse\PulseServiceProvider as LaravelPulseServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;


abstract class PulseTestCase extends TestCase
{
    protected function application() : Application
    {
        if( !$this->app ) {
            throw new \RuntimeException( 'Application is not initialized.' );
        }

        return $this->app;
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
        $app['config']->set( 'app.key', 'base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF' );
    }


    protected function getPackageProviders( $app )
    {
        return [
            CoreServiceProvider::class,
            LivewireServiceProvider::class,
            LaravelPulseServiceProvider::class,
            PulseServiceProvider::class,
        ];
    }


    protected function setUp() : void
    {
        Tenancy::$access = null;
        Tenancy::$callback = null;

        parent::setUp();
    }


    protected function tearDown() : void
    {
        Tenancy::$access = null;
        Tenancy::$callback = null;

        parent::tearDown();
    }
}


class FakePulse
{
    /**
     * @var list<FakePulseEntry>
     */
    public array $entries = [];

    public function record( string $type, string $key, ?int $value = null ) : FakePulseEntry
    {
        return $this->entries[] = new FakePulseEntry( $type, $key, $value );
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


    public function avg() : self
    {
        $this->aggregates[] = 'avg';
        return $this;
    }


    public function count() : self
    {
        $this->aggregates[] = 'count';
        return $this;
    }


    public function max() : self
    {
        $this->aggregates[] = 'max';
        return $this;
    }
}
}
