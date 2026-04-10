<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;


class SearchControllerTest extends ThemeTestAbstract
{
    use DatabaseTruncation;

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
}
