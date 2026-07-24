<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Validation;
use Aimeos\Cms\Exception;


class ValidationTest extends CoreTestAbstract
{
    public function testContentValid()
    {
        Validation::content( [
            (object) ['type' => 'heading', 'data' => (object) ['title' => 'Test', 'level' => '2']],
        ] );

        $this->assertTrue( true );
    }


    public function testContentBadType()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Unknown content type "nonexistent"' );

        Validation::content( [
            (object) ['type' => 'nonexistent', 'data' => (object) []],
        ] );
    }


    public function testContentNoType()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Unknown content type ""' );

        Validation::content( [
            (object) ['data' => (object) []],
        ] );
    }


    public function testContentNoData()
    {
        Validation::content( [
            (object) ['type' => 'heading'],
        ] );

        $this->assertTrue( true );
    }


    public function testContentRef()
    {
        Validation::content( [
            (object) ['type' => 'reference', 'refid' => 'abc123'],
        ] );

        $this->assertTrue( true );
    }


    public function testContentRefRequiresId()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Invalid content reference ID' );

        Validation::content( [['type' => 'reference']] );
    }


    public function testContentExtraFields()
    {
        Validation::content( [
            (object) ['type' => 'heading', 'data' => (object) ['title' => 'Test', 'level' => '2', 'extra' => 'ignored']],
        ] );

        $this->assertTrue( true );
    }


    public function testContentArray()
    {
        Validation::content( [
            ['type' => 'heading', 'data' => (object) ['title' => 'Test', 'level' => '2']],
        ] );

        $this->assertTrue( true );
    }


    public function testContentEmpty()
    {
        Validation::content( [] );

        $this->assertTrue( true );
    }


    public function testContentDerivesFiles()
    {
        $result = Validation::content( [
            (object) ['type' => 'image', 'data' => (object) ['file' => (object) ['type' => 'file', 'id' => 'file-1']]],
        ] );

        $this->assertEquals( ['file-1'], $result[0]->files );
    }


    public function testContentNoFilesWithoutReference()
    {
        $result = Validation::content( [
            (object) ['type' => 'heading', 'data' => (object) ['title' => 'Test', 'level' => '2']],
        ] );

        $this->assertObjectNotHasProperty( 'files', $result[0] );
    }


    public function testContentKeepsReferenceFiles()
    {
        $result = Validation::content( [
            ['type' => 'reference', 'refid' => 'el-1', 'files' => ['file-1']],
        ] );

        $this->assertEquals( ['file-1'], $result[0]->files );
    }


    public function testPageDerivesFiles()
    {
        $result = Validation::page( ['content' => [
            ['type' => 'image', 'data' => ['file' => ['type' => 'file', 'id' => 'file-1']]],
        ]] );

        $this->assertEquals( ['file-1'], $result['content'][0]->files );
    }


    public function testPageClearsStaleFiles()
    {
        $result = Validation::page( ['content' => [
            ['type' => 'heading', 'data' => ['title' => 'Test', 'level' => '2'], 'files' => ['stale-1']],
        ]] );

        $this->assertObjectNotHasProperty( 'files', $result['content'][0] );
    }


    public function testPageKeepsReferenceFiles()
    {
        $result = Validation::page( ['content' => [
            ['type' => 'reference', 'refid' => 'el-1', 'files' => ['file-1']],
        ]] );

        $this->assertEquals( ['file-1'], $result['content'][0]->files );
    }


    public function testPageAcceptsCanonicalMetaAndConfig()
    {
        $result = Validation::page( [
            'meta' => ['meta-tags' => [
                'type' => 'meta-tags',
                'data' => ['description' => 'Test'],
                'files' => [],
            ]],
        ] );
        $config = Validation::structured( [
            'logo' => [
                'type' => 'logo',
                'data' => ['file' => ['type' => 'file', 'id' => 'file-1']],
                'files' => ['file-1'],
            ],
        ], 'config' );

        $this->assertEquals( 'meta-tags', $result['meta']->{'meta-tags'}->type );
        $this->assertEquals( [], $result['meta']->{'meta-tags'}->files );
        $this->assertEquals( ['file-1'], $config->logo->files );
        $this->assertObjectNotHasProperty( 'id', $config->logo );
    }


    public function testContentHiddenDefault()
    {
        $result = Validation::content( [
            ['type' => 'toc', 'data' => ['title' => 'On this page']],
        ] );

        $this->assertCount( 1, $result );
        $this->assertEquals( 'toc', $result[0]->type );
        $this->assertEquals( '\\Aimeos\\Cms\\Actions\\Toc', $result[0]->data->action );
        $this->assertEquals( 'On this page', $result[0]->data->title );
    }


    public function testContentHiddenDefaultNotOverwritten()
    {
        $result = Validation::content( [
            ['type' => 'toc', 'data' => ['action' => 'custom']],
        ] );

        $this->assertEquals( 'custom', $result[0]->data->action );
    }


    public function testDefaultsAppliesHiddenValue()
    {
        $data = Validation::defaults( 'toc', ['title' => 'Test'] );

        $this->assertEquals( '\\Aimeos\\Cms\\Actions\\Toc', $data->action );
        $this->assertEquals( 'Test', $data->title );
    }


    public function testDefaultsUnknownType()
    {
        $data = Validation::defaults( 'nonexistent', ['foo' => 'bar'] );

        $this->assertEquals( 'bar', $data->foo );
    }


    public function testElementValid()
    {
        Validation::element( 'heading' );

        $this->assertTrue( true );
    }


    public function testElementBadType()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Unknown element type "nonexistent"' );

        Validation::element( 'nonexistent' );
    }


    public function testElementEmptyType()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Unknown element type ""' );

        Validation::element( '' );
    }


    public function testStructuredValid()
    {
        $result = Validation::structured( [
            'meta-tags' => [
                'type' => 'meta-tags',
                'data' => ['description' => 'Test'],
                'files' => [],
            ],
        ], 'meta' );

        $this->assertIsObject( $result );
        $this->assertIsObject( $result->{'meta-tags'} );
        $this->assertEquals( 'meta-tags', $result->{'meta-tags'}->type );
        $this->assertEquals( 'Test', $result->{'meta-tags'}->data->description );
        $this->assertEquals( [], $result->{'meta-tags'}->files );
        $this->assertObjectNotHasProperty( 'id', $result->{'meta-tags'} );
        $this->assertObjectNotHasProperty( 'group', $result->{'meta-tags'} );
    }


    public function testStructuredRejectsId()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'expected type, data and files' );

        Validation::structured( [
            'meta-tags' => [
                'id' => 'legacy-id',
                'type' => 'meta-tags',
                'data' => ['description' => 'Test'],
                'files' => [],
            ],
        ], 'meta' );
    }


    public function testStructuredRejectsGroup()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'expected type, data and files' );

        Validation::structured( [
            'meta-tags' => [
                'type' => 'meta-tags',
                'group' => 'main',
                'data' => ['description' => 'Test'],
                'files' => [],
            ],
        ], 'meta' );
    }


    public function testStructuredLegacyList()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'entries must be keyed by type' );

        Validation::structured( [
            ['type' => 'meta-tags', 'data' => ['description' => 'Test']],
            ['type' => 'robots', 'data' => ['index' => 'noindex']],
        ], 'meta' );
    }


    public function testStructuredSingleLegacyEntry()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'entry must be an object' );

        Validation::structured( [
            'type' => 'meta-tags',
            'data' => ['description' => 'Test'],
        ], 'meta' );
    }


    public function testStructuredUnknownType()
    {
        $result = Validation::structured( [
            'nonexistent' => [
                'type' => 'nonexistent',
                'data' => [],
                'files' => [],
            ],
        ], 'meta' );

        $this->assertIsObject( $result );
    }


    public function testEntryDerivesFiles()
    {
        $result = Validation::entry( 'logo', [
            'file' => ['type' => 'file', 'id' => 'file-1'],
        ], 'config' );

        $this->assertEquals( ['file-1'], $result->files );
    }


    public function testStructuredRejectsCompactInput()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'expected type, data and files' );

        Validation::structured( [
            'meta-tags' => ['description' => 'Test'],
        ], 'meta' );
    }


    public function testStructuredRejectsStaleFiles()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'files must match data references' );

        Validation::structured( [
            'logo' => [
                'type' => 'logo',
                'data' => ['file' => ['type' => 'file', 'id' => 'file-1']],
                'files' => [],
            ],
        ], 'config' );
    }


    public function testStructuredRejectsMismatchedType()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'key and type must match' );

        Validation::structured( [
            'logo' => [
                'type' => 'icon',
                'data' => [],
                'files' => [],
            ],
        ], 'config' );
    }


    public function testStructuredEmpty()
    {
        $result = Validation::structured( [], 'meta' );

        $this->assertIsObject( $result );
    }


    public function testPublishAtNull()
    {
        Validation::publishAt( null );

        $this->assertTrue( true );
    }


    public function testPublishAtEmpty()
    {
        Validation::publishAt( '' );

        $this->assertTrue( true );
    }


    public function testPublishAtFuture()
    {
        Validation::publishAt( now()->addDay()->toDateTimeString() );

        $this->assertTrue( true );
    }


    public function testPublishAtFutureWithTime()
    {
        Validation::publishAt( '2099-06-15 14:30:00' );

        $this->assertTrue( true );
    }


    public function testPublishAtPast()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Publish date must be in the future' );

        Validation::publishAt( '2020-01-01 00:00:00' );
    }


    public function testPublishAtInvalid()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Invalid publish date' );

        Validation::publishAt( 'not-a-date' );
    }
}
