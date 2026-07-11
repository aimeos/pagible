<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Nestedset\NestedSet;


class ModelTest extends CoreTestAbstract
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


    public function testPageAcceptsCanonicalStructuredAttributes(): void
    {
        $page = new Page();
        $page->meta = ['meta-tags' => [
            'type' => 'meta-tags',
            'data' => ['description' => 'Test'], 'files' => [],
        ]];
        $page->config = ['logo' => [
            'type' => 'logo',
            'data' => ['file' => ['type' => 'file', 'id' => 'file-1']], 'files' => ['file-1'],
        ]];

        $this->assertEquals( 'Test', $page->meta->{'meta-tags'}->data->description );
        $this->assertObjectNotHasProperty( 'id', $page->meta->{'meta-tags'} );
        $this->assertEquals( ['file-1'], $page->config->logo->files );
    }


    public function testJsonKeyOrderDoesNotMarkModelDirty(): void
    {
        $page = new Page();
        $page->setRawAttributes( [
            'meta' => '{"meta-tags":{"data":{"keywords":"cms","description":"Test"},"type":"meta-tags","files":[]}}',
            'content' => '[{"type":"heading"},{"type":"text"}]',
        ], true );

        $page->meta = ['meta-tags' => [
            'type' => 'meta-tags',
            'data' => ['description' => 'Test', 'keywords' => 'cms'],
            'files' => [],
        ]];
        $page->content = [['type' => 'heading'], ['type' => 'text']];

        $this->assertFalse( $page->isDirty( 'meta' ) );
        $this->assertFalse( $page->isDirty( 'content' ) );

        $page->content = [['type' => 'text'], ['type' => 'heading']];

        $this->assertTrue( $page->isDirty( 'content' ) );
    }


    public function testPageRejectsLegacyStructuredAttributes(): void
    {
        $this->expectException( \Aimeos\Cms\Exception::class );

        $page = new Page();
        $page->meta = [['type' => 'meta-tags', 'data' => ['description' => 'Test']]];
    }


    public function testPageHasDescendantCount(): void
    {
        $page = new Page();

        // leaf: rgt = lft + 1, no descendants
        $page->setAttribute( NestedSet::LFT, 2 );
        $page->setAttribute( NestedSet::RGT, 3 );
        $this->assertSame( 0, $page->has );

        // subtree spanning 3 descendants: (9 - 2 - 1) / 2
        $page->setAttribute( NestedSet::RGT, 9 );
        $this->assertSame( 3, $page->has );
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


    public function testVersionAcceptsCanonicalStructuredAux(): void
    {
        $version = new Version( [
            'data' => (object) ['type' => 'page'],
            'aux' => [
                'meta' => ['meta-tags' => [
                    'type' => 'meta-tags',
                    'data' => ['description' => 'Test'], 'files' => [],
                ]],
                'config' => ['logo' => [
                    'type' => 'logo',
                    'data' => ['file' => ['type' => 'file', 'id' => 'file-1']], 'files' => ['file-1'],
                ]],
            ],
        ] );

        $this->assertEquals( 'Test', $version->aux->meta->{'meta-tags'}->data->description );
        $this->assertObjectNotHasProperty( 'id', $version->aux->meta->{'meta-tags'} );
        $this->assertEquals( ['file-1'], $version->aux->config->logo->files );
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
