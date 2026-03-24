<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;


class ModelTest extends TestAbstract
{
    public function testPageToString(): void
    {
        $page = new Page( ['name' => 'Home', 'title' => 'Home | Laravel CMS'] );
        $page->content = [(object) ['type' => 'heading', 'data' => (object) ['title' => 'Welcome to Laravel CMS']]];
        $page->setRelation( 'elements', collect( [] ) );

        $this->assertStringContainsString( 'Home', (string) $page );
        $this->assertStringContainsString( 'Home | Laravel CMS', (string) $page );
        $this->assertStringContainsString( 'Welcome to Laravel CMS', (string) $page );
    }


    public function testPageToStringEmpty(): void
    {
        $page = new Page( ['name' => 'Disabled'] );
        $page->setRelation( 'elements', collect( [] ) );

        $this->assertStringContainsString( 'Disabled', (string) $page );
        $this->assertStringNotContainsString( 'Welcome', (string) $page );
    }


    public function testElementToString(): void
    {
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
        $file = new File( ['name' => 'Test image', 'description' => (object) ['en' => 'Test file description']] );

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
        $version = new Version( [
            'data' => (object) ['name' => 'Home', 'type' => 'heading', 'data' => (object) ['title' => 'Welcome to Laravel CMS']],
            'aux' => (object) ['content' => []],
        ] );
        $version->setRelation( 'elements', collect( [] ) );

        $this->assertStringContainsString( 'Home', (string) $version );
        $this->assertStringContainsString( 'Welcome to Laravel CMS', (string) $version );
    }


    public function testVersionToStringElement(): void
    {
        $version = new Version( ['data' => (object) []] );

        $this->assertNotNull( $version );
    }


    public function testVersionToStringFile(): void
    {
        $version = new Version( [
            'data' => (object) ['name' => 'Test image', 'description' => (object) ['en' => 'Test file description']],
            'aux' => (object) [],
        ] );
        $version->setRelation( 'elements', collect( [] ) );

        $this->assertStringContainsString( 'Test image', (string) $version );
        $this->assertStringContainsString( "en:\nTest file description", (string) $version );
    }
}
