<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;


class SearchControllerTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;

    protected $seeder = TestSeeder::class;
    protected $connectionsToTransact = [];


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );
        $app['config']->set('scout.driver', 'collection');
    }


    protected function beforeTruncatingDatabase(): void
    {
        // In-memory SQLite databases don't persist across test classes
        RefreshDatabaseState::$migrated = false;
    }


    public function testIndex()
    {
        $request = Request::create('/cmsapi/search', 'GET', [
            'q' => 'welcome',
            'locale' => 'en',
            'size' => 10,
        ]);

        $controller = new \Aimeos\Cms\Controllers\SearchController();
        $response = $controller->index($request, 'mydomain.tld');

        $data = $response->getData();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertObjectHasProperty('data', $data);
        $this->assertObjectHasProperty('current_page', $data);
        $this->assertObjectHasProperty('last_page', $data);
        $this->assertEquals(1, $data->current_page);
        $this->assertIsArray($data->data);
        $this->assertNotEmpty($data->data);

        $item = $data->data[0];
        $this->assertEquals('mydomain.tld', $item->domain);
        $this->assertEquals('en', $item->lang);
        $this->assertEquals('Home | Laravel CMS', $item->title);
    }


    public function testIndexAllowsTwoChars()
    {
        $request = Request::create('/cmsapi/search', 'GET', ['q' => 'we', 'locale' => 'en', 'size' => 10]);

        $controller = new \Aimeos\Cms\Controllers\SearchController();
        $response = $controller->index($request, 'mydomain.tld');

        $this->assertEquals(200, $response->getStatusCode());
    }


    public function testIndexRejectsSingleChar()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $request = Request::create('/cmsapi/search', 'GET', ['q' => 'a', 'locale' => 'en', 'size' => 10]);

        ( new \Aimeos\Cms\Controllers\SearchController() )->index($request, 'mydomain.tld');
    }


    public function testIndexHonorsConfiguredMinimum()
    {
        config(['cms.search.min' => 4]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $request = Request::create('/cmsapi/search', 'GET', ['q' => 'abc', 'locale' => 'en', 'size' => 10]);

        ( new \Aimeos\Cms\Controllers\SearchController() )->index($request, 'mydomain.tld');
    }


    public function testExternalSearchResultsAreRecheckedAgainstDatabaseAccess(): void
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $engine = new StaleAccessSearchEngine( [$page->id] );
        $manager = app( EngineManager::class );
        $manager->extend( 'stale-access-test', fn() => $engine );
        $manager->forgetDrivers();
        config( ['scout.driver' => 'stale-access-test'] );
        Access::availableUsing( fn() => [] );
        PageAccess::restrict( [$page->id], null );

        $request = Request::create( '/cmsapi/search', 'GET', [
            'q' => 'welcome',
            'locale' => $page->lang,
            'size' => 10,
        ] );

        $response = ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, $page->domain );

        $this->assertSame( [], $response->getData()->data );
    }


    public function testExternalSearchResultsAreRecheckedAgainstDatabaseDomain(): void
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $domain = $page->domain;
        $engine = new StaleAccessSearchEngine( [$page->id] );
        $manager = app( EngineManager::class );
        $manager->extend( 'stale-domain-test', fn() => $engine );
        $manager->forgetDrivers();
        config( ['scout.driver' => 'stale-domain-test'] );
        $page->update( ['domain' => 'other.example'] );

        $request = Request::create( '/cmsapi/search', 'GET', [
            'q' => 'welcome',
            'locale' => $page->lang,
            'size' => 10,
        ] );

        $response = ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, $domain );

        $this->assertSame( [], $response->getData()->data );
    }


    public function testExternalSearchResultsAreRecheckedAgainstDatabaseLanguage(): void
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $lang = $page->lang;
        $engine = new StaleAccessSearchEngine( [$page->id] );
        $manager = app( EngineManager::class );
        $manager->extend( 'stale-language-test', fn() => $engine );
        $manager->forgetDrivers();
        config( ['scout.driver' => 'stale-language-test'] );
        $page->update( ['lang' => 'de'] );

        $request = Request::create( '/cmsapi/search', 'GET', [
            'q' => 'welcome',
            'locale' => $lang,
            'size' => 10,
        ] );

        $response = ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, $page->domain );

        $this->assertSame( [], $response->getData()->data );
    }
}


class StaleAccessSearchEngine extends NullEngine
{
    /** @param list<string> $ids */
    public function __construct( private array $ids ) {}


    public function paginate( Builder $builder, $perPage, $page ) : array
    {
        return $this->ids;
    }


    public function map( Builder $builder, $results, $model )
    {
        return $model->getScoutModelsByIds( $builder, $results );
    }


    public function mapIds( $results ) : Collection
    {
        return collect( $results );
    }


    public function getTotalCount( $results ) : int
    {
        return count( $results );
    }
}
