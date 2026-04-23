<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Merge;


class MergeTest extends CoreTestAbstract
{
    public function testStructuredNoConflict()
    {
        $base = ['a' => 1, 'b' => 2];
        $current = ['a' => 1, 'b' => 2];
        $incoming = ['a' => 1, 'b' => 2];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertEquals( ['a' => 1, 'b' => 2], $result );
        $this->assertNull( $diff );
    }


    public function testStructuredCurrentSameAsIncoming()
    {
        $base = ['a' => 1];
        $current = ['a' => 2];
        $incoming = ['a' => 2];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertEquals( ['a' => 2], $result );
        $this->assertNull( $diff );
    }


    public function testStructuredSingleSideChangeIncoming()
    {
        $base = ['a' => 1, 'b' => 2];
        $current = ['a' => 1, 'b' => 2];
        $incoming = ['a' => 1, 'b' => 3];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertEquals( ['a' => 1, 'b' => 3], $result );
        $this->assertNull( $diff );
    }


    public function testStructuredSingleSideChangeCurrent()
    {
        $base = ['a' => 1, 'b' => 2];
        $current = ['a' => 1, 'b' => 3];
        $incoming = ['a' => 1, 'b' => 2];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertEquals( ['a' => 1, 'b' => 3], $result );
        $this->assertNotNull( $diff );
        $this->assertEquals( ['previous' => 2, 'current' => 3], $diff['b'] );
    }


    public function testStructuredBothSidesConflict()
    {
        $base = ['a' => 1];
        $current = ['a' => 2];
        $incoming = ['a' => 3];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertEquals( ['a' => 3], $result );
        $this->assertNotNull( $diff );
        $this->assertEquals( ['previous' => 1, 'current' => 3, 'overwritten' => 2, 'merged' => null], $diff['a'] );
    }


    public function testStructuredNewKeyInCurrent()
    {
        $base = ['a' => 1];
        $current = ['a' => 1, 'b' => 2];
        $incoming = ['a' => 1];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertEquals( ['a' => 1, 'b' => 2], $result );
        $this->assertNotNull( $diff );
        $this->assertEquals( ['previous' => null, 'current' => 2], $diff['b'] );
    }


    public function testStructuredNewKeyInIncoming()
    {
        $base = ['a' => 1];
        $current = ['a' => 1];
        $incoming = ['a' => 1, 'b' => 2];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertEquals( ['a' => 1, 'b' => 2], $result );
        $this->assertNull( $diff );
    }


    public function testStructuredDeepEquality()
    {
        $base = ['a' => ['x' => 1]];
        $current = ['a' => ['x' => 1]];
        $incoming = ['a' => ['x' => 2]];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertEquals( ['a' => ['x' => 2]], $result );
        $this->assertNull( $diff );
    }


    public function testContentNoConflict()
    {
        $base = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'hello']]];
        $current = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'hello']]];
        $incoming = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'hello']]];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertCount( 1, $result );
        $this->assertNull( $diff );
    }


    public function testContentCurrentSameAsIncoming()
    {
        $base = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'old']]];
        $current = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'new']]];
        $incoming = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'new']]];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertCount( 1, $result );
        $this->assertNull( $diff );
    }


    public function testContentOneSideChange()
    {
        $base = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'old']]];
        $current = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'changed']]];
        $incoming = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'old']]];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertCount( 1, $result );
        $this->assertEquals( 'changed', $result[0]['data']['text'] );
        $this->assertNotNull( $diff );
        $this->assertArrayHasKey( 'a', $diff );
        $this->assertArrayNotHasKey( 'overwritten', $diff['a'] );
    }


    public function testContentBothSidesConflict()
    {
        $base = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'old']]];
        $current = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'from-other']]];
        $incoming = [['id' => 'a', 'type' => 'text', 'data' => ['text' => 'from-me']]];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertCount( 1, $result );
        $this->assertEquals( 'from-me', $result[0]['data']['text'] );
        $this->assertNotNull( $diff );
        $this->assertArrayHasKey( 'overwritten', $diff['a'] );
    }


    public function testContentBlockAddedByCurrent()
    {
        $base = [['id' => 'a', 'type' => 'text', 'data' => []]];
        $current = [
            ['id' => 'a', 'type' => 'text', 'data' => []],
            ['id' => 'b', 'type' => 'text', 'data' => ['text' => 'new']],
        ];
        $incoming = [['id' => 'a', 'type' => 'text', 'data' => []]];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertCount( 2, $result );
        $this->assertEquals( 'b', $result[1]['id'] );
        $this->assertNotNull( $diff );
        $this->assertArrayHasKey( 'b', $diff );
        $this->assertNull( $diff['b']['previous'] );
    }


    public function testContentBlockRemovedByIncoming()
    {
        $base = [
            ['id' => 'a', 'type' => 'text', 'data' => []],
            ['id' => 'b', 'type' => 'text', 'data' => []],
        ];
        $current = [
            ['id' => 'a', 'type' => 'text', 'data' => []],
            ['id' => 'b', 'type' => 'text', 'data' => []],
        ];
        $incoming = [['id' => 'a', 'type' => 'text', 'data' => []]];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertCount( 1, $result );
        $this->assertEquals( 'a', $result[0]['id'] );
    }


    public function testContentWithRefid()
    {
        $base = [['refid' => 'r1', 'type' => 'reference']];
        $current = [['refid' => 'r1', 'type' => 'reference']];
        $incoming = [['refid' => 'r1', 'type' => 'reference']];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertCount( 1, $result );
        $this->assertNull( $diff );
    }


    public function testContentIncomingOrderWins()
    {
        $base = [
            ['id' => 'a', 'type' => 'text', 'data' => []],
            ['id' => 'b', 'type' => 'text', 'data' => []],
        ];
        $current = [
            ['id' => 'a', 'type' => 'text', 'data' => []],
            ['id' => 'b', 'type' => 'text', 'data' => []],
        ];
        $incoming = [
            ['id' => 'b', 'type' => 'text', 'data' => []],
            ['id' => 'a', 'type' => 'text', 'data' => []],
        ];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertEquals( 'b', $result[0]['id'] );
        $this->assertEquals( 'a', $result[1]['id'] );
    }


    public function testTryNonOverlapping()
    {
        $base = ['a' => 1, 'b' => 2];
        $current = ['a' => 1, 'b' => 3];
        $incoming = ['a' => 4, 'b' => 2];

        $result = Merge::try( $base, $current, $incoming );

        $this->assertEquals( ['a' => 4, 'b' => 3], $result );
    }


    public function testTryConflict()
    {
        $base = ['a' => 1];
        $current = ['a' => 2];
        $incoming = ['a' => 3];

        $this->assertNull( Merge::try( $base, $current, $incoming ) );
    }


    public function testTryNoChanges()
    {
        $base = ['a' => 1, 'b' => 2];
        $current = ['a' => 1, 'b' => 2];
        $incoming = ['a' => 1, 'b' => 2];

        $this->assertEquals( ['a' => 1, 'b' => 2], Merge::try( $base, $current, $incoming ) );
    }


    public function testStructuredMergedFieldNonOverlapping()
    {
        $base = ['x' => ['a' => 1, 'b' => 2]];
        $current = ['x' => ['a' => 1, 'b' => 3]];
        $incoming = ['x' => ['a' => 4, 'b' => 2]];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertNotNull( $diff['x']['merged'] );
        $this->assertEquals( ['a' => 4, 'b' => 3], $diff['x']['merged'] );
    }


    public function testStructuredMergedFieldConflict()
    {
        $base = ['x' => ['a' => 1]];
        $current = ['x' => ['a' => 2]];
        $incoming = ['x' => ['a' => 3]];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertNull( $diff['x']['merged'] );
    }


    public function testContentMergedFieldNonOverlapping()
    {
        $base = [['id' => 'a', 'type' => 'text', 'data' => ['title' => 'old', 'text' => 'old']]];
        $current = [['id' => 'a', 'type' => 'text', 'data' => ['title' => 'new-title', 'text' => 'old']]];
        $incoming = [['id' => 'a', 'type' => 'text', 'data' => ['title' => 'old', 'text' => 'new-text']]];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertNotNull( $diff['a']['merged'] );
        $this->assertEquals( 'new-title', $diff['a']['merged']['data']['title'] );
        $this->assertEquals( 'new-text', $diff['a']['merged']['data']['text'] );
    }


    public function testContentMergedFieldWithStdClass()
    {
        $base = [json_decode( '{"id":"a","type":"text","data":{"title":"old","text":"old"}}' )];
        $current = [json_decode( '{"id":"a","type":"text","data":{"title":"new-title","text":"old"}}' )];
        $incoming = [['id' => 'a', 'type' => 'text', 'data' => ['title' => 'old', 'text' => 'new-text']]];

        [$result, $diff] = Merge::content( $base, $current, $incoming );

        $this->assertNotNull( $diff['a']['merged'] );
        $this->assertEquals( 'new-title', $diff['a']['merged']['data']['title'] );
        $this->assertEquals( 'new-text', $diff['a']['merged']['data']['text'] );
    }


    public function testStructuredMergedFieldWithStdClass()
    {
        $base = ['x' => json_decode( '{"a":1,"b":2}' )];
        $current = ['x' => json_decode( '{"a":1,"b":3}' )];
        $incoming = ['x' => ['a' => 4, 'b' => 2]];

        [$result, $diff] = Merge::structured( $base, $current, $incoming );

        $this->assertNotNull( $diff['x']['merged'] );
        $this->assertEquals( 4, $diff['x']['merged']['a'] );
        $this->assertEquals( 3, $diff['x']['merged']['b'] );
    }


    public function testTryWithStdClass()
    {
        $base = ['data' => (object) ['title' => 'old', 'text' => 'old']];
        $current = ['data' => (object) ['title' => 'new-title', 'text' => 'old']];
        $incoming = ['data' => (object) ['title' => 'old', 'text' => 'new-text']];

        $result = Merge::try( $base, $current, $incoming );

        $this->assertNotNull( $result );
        $this->assertEquals( 'new-title', $result['data']['title'] );
        $this->assertEquals( 'new-text', $result['data']['text'] );
    }
}
