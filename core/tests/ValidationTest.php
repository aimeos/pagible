<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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


    public function testPageDerivesContentFiles()
    {
        $result = Validation::page( ['content' => [
            (object) ['type' => 'image', 'data' => (object) ['file' => (object) ['type' => 'file', 'id' => 'file-1']]],
        ]] );

        $this->assertEquals( ['file-1'], $result['content'][0]->files );
    }


    public function testPageRemovesStaleContentFiles()
    {
        $result = Validation::page( ['content' => [
            (object) ['type' => 'heading', 'data' => (object) ['title' => 'Test', 'level' => '2'], 'files' => ['stale']],
        ]] );

        $this->assertObjectNotHasProperty( 'files', $result['content'][0] );
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
            'meta-tags' => ['description' => 'Test'],
        ], 'meta' );

        $this->assertIsObject( $result );
        $this->assertIsObject( $result->{'meta-tags'} );
        $this->assertEquals( 'meta-tags', $result->{'meta-tags'}->type );
    }


    public function testStructuredUnknownType()
    {
        $result = Validation::structured( [
            'nonexistent' => ['data' => []],
        ], 'meta' );

        $this->assertIsObject( $result );
    }


    public function testStructuredDerivesFiles()
    {
        $result = Validation::structured( [
            'logo' => ['file' => ['type' => 'file', 'id' => 'file-1']],
        ], 'config' );

        $this->assertEquals( ['file-1'], $result->logo->files );
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
