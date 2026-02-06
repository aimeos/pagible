<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Http\Request;


class SearchControllerTest extends TestAbstract
{
    public function testIndex()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $request = Request::create('/cmsapi/search', 'GET', [
            'search' => 'welcome',
            'locale' => 'en',
        ]);

        $controller = new \Aimeos\Cms\Controllers\SearchController();
        $response = $controller->index($request, 'mydomain.tld');

        $expected = [
            (object) [
                'domain' => 'mydomain.tld',
                'path' => 'welcome-to-laravelcms',
                'lang' => 'en',
                'title' => 'Welcome to Laravel CMS | Laravel CMS',
                'content' => 'Welcome to Laravel CMS A new light-weight Laravel CMS is here!',
            ], (object) [
                'domain' => 'mydomain.tld',
                'path' => '',
                'lang' => 'en',
                'title' => 'Home | Laravel CMS',
                'content' => 'Welcome to Laravel CMS',
            ]
        ];

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEqualsCanonicalizing($expected, $response->getData());
    }
}
