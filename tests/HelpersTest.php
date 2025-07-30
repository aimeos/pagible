<?php

namespace Tests;

use Database\Seeders\CmsSeeder;
use Aimeos\Cms\Models\Page;


class HelpersTest extends TestAbstract
{
	public function testCms()
	{
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'blog')->firstOrFail();

		$this->assertEquals( 'blog', cms( $page, 'path' ) );
	}


	public function testCmsAsset()
	{
		$this->assertEquals( 'http://localhost/not/exists.js?v=0', cmsasset( 'not/exists.js' ) );
	}


	public function testCmsSrcset()
	{
		$this->assertEquals( '/storage/not/exists.jpg 1w', cmssrcset( [1 => 'not/exists.jpg'] ) );
	}


	public function testCmsUrl()
	{
		$this->assertEquals( 'data:ABCD', cmsurl( 'data:ABCD' ) );
		$this->assertEquals( '/storage/not/exists.jpg', cmsurl( 'not/exists.jpg' ) );
		$this->assertEquals( 'http://example.com/not/exists.jpg', cmsurl( 'http://example.com/not/exists.jpg' ) );
		$this->assertEquals( 'https://example.com/not/exists.jpg', cmsurl( 'https://example.com/not/exists.jpg' ) );
	}
}
