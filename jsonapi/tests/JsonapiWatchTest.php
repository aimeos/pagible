<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\CmsJsonapi;
use Aimeos\Cms\Events\Observed;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LaravelJsonApi\Testing\MakesJsonApiRequests;


class JsonapiWatchTest extends JsonapiTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;
    use MakesJsonApiRequests;

    protected string $seeder = TestSeeder::class;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'LaravelJsonApi\Laravel\ServiceProvider'
        ] );
    }


    public function testReadDispatchesQueried() : void
    {
        config( ['cms.jsonapi.watch' => true] );
        $page = \Aimeos\Cms\Models\Page::where( 'tag', 'root' )->firstOrFail();
        Event::fake( [CmsJsonapi::class] );

        $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        Event::assertDispatched( CmsJsonapi::class, fn( CmsJsonapi $e ) =>
            $e->action === 'jsonapi:read'
        );
    }


    public function testCollectionDispatchesSearch() : void
    {
        config( ['cms.jsonapi.watch' => true] );
        Event::fake( [CmsJsonapi::class] );

        $this->jsonApi()->expects( 'pages' )->get( 'cms/pages' );

        Event::assertDispatched( CmsJsonapi::class, fn( CmsJsonapi $e ) =>
            $e->action === 'jsonapi:search'
        );
    }


    public function testIncludesAreRecorded() : void
    {
        config( ['cms.jsonapi.watch' => true] );
        $page = \Aimeos\Cms\Models\Page::where( 'tag', 'article' )->firstOrFail();
        Event::fake( [CmsJsonapi::class] );

        $this->jsonApi()->expects( 'pages' )->includePaths( 'ancestors' )->get( "cms/pages/{$page->id}" );

        Event::assertDispatched( CmsJsonapi::class, fn( CmsJsonapi $e ) => $e->includes === 'ancestors' );
    }


    public function testDomainCapturedFromHostWhenMultidomain() : void
    {
        config( ['cms.jsonapi.watch' => true, 'cms.multidomain' => true] );
        Event::fake( [CmsJsonapi::class] );

        $this->jsonApi()->expects( 'pages' )->get( 'cms/pages' );

        // Without multi-domain the domain is empty; with it the request host is recorded.
        Event::assertDispatched( CmsJsonapi::class, fn( CmsJsonapi $e ) => $e->domain !== '' );
    }


    public function testDomainEmptyWithoutMultidomain() : void
    {
        config( ['cms.jsonapi.watch' => true, 'cms.multidomain' => false] );
        Event::fake( [CmsJsonapi::class] );

        $this->jsonApi()->expects( 'pages' )->get( 'cms/pages' );

        Event::assertDispatched( CmsJsonapi::class, fn( CmsJsonapi $e ) => $e->domain === '' );
    }


    public function testNothingDispatchedWhenWatchOff() : void
    {
        config( ['cms.jsonapi.watch' => false] );
        Event::fake( [CmsJsonapi::class] );

        $this->jsonApi()->expects( 'pages' )->get( 'cms/pages' );

        Event::assertNotDispatched( CmsJsonapi::class );
    }


    public function testObserverReceivesQueriedWithDurationWhenWatchOff() : void
    {
        config( ['cms.watch.channel' => null, 'cms.jsonapi.watch' => false] );
        Event::listen( Observed::class, fn() => null );
        Event::fake( [Observed::class] );

        $this->jsonApi()->expects( 'pages' )->get( 'cms/pages' );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'jsonapi'
            && $e->action === 'jsonapi:search'
            && $e->durationMs > 0.0
            && $e->dimensions === ['domain' => '']
            && $e->sample
        );
    }
}
