<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Request;


class SearchControllerTest extends TestAbstract
{
    use DatabaseTruncation;

    protected $connectionsToTransact = [];


    public function testIndex()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        sleep( 5 ); // wait for SQL Server async fulltext index population

        $request = Request::create('/cmsapi/search', 'GET', [
            'search' => 'welcome',
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
