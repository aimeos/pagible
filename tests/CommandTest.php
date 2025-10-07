<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Content;
use Database\Seeders\CmsSeeder;


class CommandTest extends TestAbstract
{
    public function testIndex(): void
    {
        $this->seed( CmsSeeder::class );

        $this->artisan('cms:index')->assertExitCode( 0 );

        $this->assertEquals( 2, Content::get()->count() );
    }


    public function testPublish(): void
    {
        $this->seed( CmsSeeder::class );

        $this->artisan('cms:publish')->assertExitCode( 0 );

        $this->assertEquals( 1, Page::where( 'path', 'hidden' )->firstOrFail()?->status );
        $this->assertEquals( 'Powered by Laravel CMS!', Element::where( 'name', 'Shared footer' )->firstOrFail()?->data->text );
        $this->assertEquals( (object) [
            'en' => 'Test file description',
            'de' => 'Beschreibung der Testdatei',
        ], File::where( 'lang', 'en' )->firstOrFail()?->description );
    }
}