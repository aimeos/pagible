<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Database\Seeders\CmsSeeder;


class ModelTest extends TestAbstract
{
    public function testPageToString(): void
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where( 'path', '' )->firstOrFail();
        $this->assertStringContainsString( 'Home', (string) $page );
        $this->assertStringContainsString( 'Home | Laravel CMS', (string) $page );
        $this->assertStringContainsString( 'Welcome to Laravel CMS', (string) $page );
    }


    public function testPageToStringEmpty(): void
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where( 'path', 'disabled' )->firstOrFail();
        $this->assertStringContainsString( 'Disabled', (string) $page );
        $this->assertStringNotContainsString( 'Welcome', (string) $page );
    }


    public function testElementToString(): void
    {
        $this->seed( CmsSeeder::class );

        $element = new Element();
        $element->type = 'heading';
        $element->name = 'Test';
        $element->data = ['type' => 'heading', 'data' => ['title' => 'Test heading']];

        $this->assertStringContainsString( 'Test', (string) $element );
        $this->assertStringContainsString( 'Test heading', (string) $element );
    }


    public function testElementToStringEmpty(): void
    {
        $element = new Element();
        $element->type = 'unknown';
        $element->data = ['type' => 'unknown', 'data' => ['text' => 'test']];

        $this->assertEmpty( (string) $element );
    }


    public function testFileToString(): void
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $this->assertStringContainsString( 'Test image', (string) $file );
        $this->assertStringContainsString( "en:\nTest file description", (string) $file );
    }


    public function testFileToStringEmpty(): void
    {
        $file = new File();
        $this->assertEmpty( (string) $file );
    }


    public function testVersionToStringPage(): void
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where( 'path', '' )->firstOrFail();
        $version = $page->latest;

        $this->assertStringContainsString( 'Home', (string) $version );
        $this->assertStringContainsString( 'Welcome to Laravel CMS', (string) $version );
    }


    public function testVersionToStringElement(): void
    {
        $this->seed( CmsSeeder::class );

        $element = Element::where( 'name', 'Shared footer' )->firstOrFail();
        $version = $element->latest;

        $this->assertNotNull( $version );
    }


    public function testVersionToStringFile(): void
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $version = $file->latest;

        $this->assertStringContainsString( 'Test image', (string) $version );
        $this->assertStringContainsString( "en:\nTest file description", (string) $version );
    }
}
