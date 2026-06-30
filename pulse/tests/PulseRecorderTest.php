<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\Events\Queried;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\Recorders\CmsAiPulseRecorder;
use Aimeos\Cms\Recorders\CmsAuthPulseRecorder;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsContentPulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;


class PulseRecorderTest extends PulseTestCase
{
    public function testRecordsPageAction() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new Saved( 'page', 'p1', 'v1', 'editor@test', ['path' => 'about', 'domain' => 'example.org'],
                tenant: 'test', source: 'graphql' )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_page:test', $this->pulse->entries[0]->type );
        $this->assertSame( ['count'], $this->pulse->entries[0]->aggregates );

        $key = $this->key( 0 );

        $this->assertSame( 'graphql:save', $key['action'] );
        $this->assertSame( 'editor@test', $key['editor'] );
        $this->assertSame( 'about', $key['path'] );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testTenantlessEntriesKeepBaseType() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new Saved( 'page', 'p1', 'v1', 'editor@test', ['path' => 'about'], tenant: '', source: 'graphql' )
        );

        $this->assertSame( 'cms_page', $this->pulse->entries[0]->type );
    }


    public function testIgnoresUnsupportedContentTypes() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new Saved( 'snippet', 'p1', 'v1', 'editor@test', [], tenant: 'test', source: 'graphql' )
        );

        $this->assertSame( [], $this->pulse->entries );
    }


    public function testRecordsBulkItemCount() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new Bulk( 'element', ['e1', 'e2'], ['e1' => 'v1', 'e2' => 'v2'], [], 'editor@test', 'test', 'mcp' )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_element:test', $this->pulse->entries[0]->type );
        $this->assertSame( 2, $this->pulse->entries[0]->value );
        $this->assertSame( ['count', 'sum'], $this->pulse->entries[0]->aggregates );
        $this->assertSame( 'mcp:bulk', $this->key( 0 )['action'] );
    }


    public function testAuthRecorderAnonymizesPersonalData() : void
    {
        ( new CmsAuthPulseRecorder )->record(
            new Authed( 'login-fail', 'user@example.org', '127.0.0.1', 'Browser/1.0', 'test' )
        );

        $key = $this->key( 0 );

        $this->assertSame( 'cms_auth:test', $this->pulse->entries[0]->type );
        $this->assertSame( 'graphql:login-fail', $key['action'] );
        $this->assertNotSame( 'user@example.org', $key['email'] );
        $this->assertNotSame( '127.0.0.1', $key['ip'] );
        $this->assertSame( 64, strlen( $key['email'] ) );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testAiRecorderRecordsLatency() : void
    {
        ( new CmsAiPulseRecorder )->record( new Generated(
            mutation: 'write',
            provider: 'openai',
            model: 'gpt-test',
            durationMs: 12.7,
            editor: 'editor@test',
            tenant: 'test',
            success: true,
            inputTokens: 100,
            outputTokens: 25,
        ) );

        $this->assertSame( ['cms_ai:test'],
            array_map( fn( FakePulseEntry $entry ) => $entry->type, $this->pulse->entries )
        );

        $this->assertSame( 13, $this->pulse->entries[0]->value );
        $this->assertSame( ['count', 'avg', 'max'], $this->pulse->entries[0]->aggregates );

        $key = $this->key( 0 );

        $this->assertSame( 'ai:write', $key['mutation'] );
        $this->assertTrue( $key['success'] );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testSampledRecordersRespectDisabledSampling() : void
    {
        config( ['cms.watch.sample' => 0.0] );

        ( new CmsSearchPulseRecorder )->record( new Searched( 'term', 0, 1, 5.2, 'example.org', 'en', 'test' ) );
        ( new CmsJsonapiPulseRecorder )->record( new Queried( 'jsonapi:search', 4.8, 'example.org', 'children', 'test' ) );

        $this->assertSame( [], $this->pulse->entries );
    }


    public function testContactRecorderIgnoresSampling() : void
    {
        config( ['cms.watch.sample' => 0.0] );

        ( new CmsContactPulseRecorder )->record( new Contacted( 'user@example.org', '127.0.0.1', 3.1, 'test' ) );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_contact:test', $this->pulse->entries[0]->type );
    }


    /**
     * @return array<string, mixed>
     */
    protected function key( int $index ) : array
    {
        return json_decode( $this->pulse->entries[$index]->key, true, flags: JSON_THROW_ON_ERROR );
    }
}
}
