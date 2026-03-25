<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Validation;


class ValidationTest extends TestAbstract
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
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Unknown content type "nonexistent"' );

        Validation::content( [
            (object) ['type' => 'nonexistent', 'data' => (object) []],
        ] );
    }


    public function testContentNoType()
    {
        $this->expectException( \InvalidArgumentException::class );
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


    public function testStructuredValid()
    {
        Validation::structured( (object) [
            'meta-tags' => (object) ['data' => (object) ['description' => 'Test']],
        ], 'meta' );

        $this->assertTrue( true );
    }


    public function testStructuredUnknownType()
    {
        Validation::structured( (object) [
            'nonexistent' => (object) ['data' => (object) []],
        ], 'meta' );

        $this->assertTrue( true );
    }


    public function testStructuredEmpty()
    {
        Validation::structured( new \stdClass(), 'meta' );

        $this->assertTrue( true );
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


    public function testPublishAtPast()
    {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Publish date must be in the future' );

        Validation::publishAt( '2020-01-01 00:00:00' );
    }


    public function testPublishAtInvalid()
    {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Invalid publish date' );

        Validation::publishAt( 'not-a-date' );
    }
}
