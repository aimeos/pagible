<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Events\ContentChanged;
use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\Events\Queried;
use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\Recorders\CmsAiPulseRecorder;
use Aimeos\Cms\Recorders\CmsAuthPulseRecorder;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsContentPulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;
use Illuminate\Contracts\Debug\ExceptionHandler;


class PulseRecorderTest extends PulseTestCase
{
    public function testRecordsPageAction() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new ContentChanged( 'page', 'saved', source: 'graphql', tenant: 'test', domain: 'example.org' )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_page:test', $this->pulse->entries[0]->type );
        $this->assertSame( ['count'], $this->pulse->entries[0]->aggregates );

        $key = $this->key( 0 );

        $this->assertSame( 'graphql:save', $key['action'] );
        $this->assertSame( 'graphql', $key['source'] );
        $this->assertSame( 'example.org', $key['domain'] );
        $this->assertArrayNotHasKey( 'editor', $key );
        $this->assertArrayNotHasKey( 'path', $key );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testTenantlessEntriesKeepBaseType() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new ContentChanged( 'page', 'saved', source: 'graphql' )
        );

        $this->assertSame( 'cms_page', $this->pulse->entries[0]->type );
    }


    public function testIgnoresUnsupportedContentTypes() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new ContentChanged( 'snippet', 'saved', source: 'graphql', tenant: 'test' )
        );

        $this->assertSame( [], $this->pulse->entries );
    }


    public function testRecordsBulkItemCount() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new ContentChanged( 'element', 'bulk', source: 'mcp', tenant: 'test', value: 2 )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_element:test', $this->pulse->entries[0]->type );
        $this->assertSame( 2, $this->pulse->entries[0]->value );
        $this->assertSame( ['count', 'sum'], $this->pulse->entries[0]->aggregates );
        $this->assertSame( 'mcp:bulk', $this->key( 0 )['action'] );
    }


    public function testAuthRecorderUsesLowCardinalityKey() : void
    {
        ( new CmsAuthPulseRecorder )->record(
            new Authed( 'login-fail', 'user@example.org', '127.0.0.1', 'Browser/1.0', 'test' )
        );

        $key = $this->key( 0 );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_auth:test', $this->pulse->entries[0]->type );
        $this->assertSame( 'graphql:login-fail', $key['action'] );
        $this->assertArrayNotHasKey( 'email', $key );
        $this->assertArrayNotHasKey( 'ip', $key );
        $this->assertArrayNotHasKey( 'user_agent', $key );
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
        $this->assertArrayNotHasKey( 'editor', $key );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testSearchRecorderUsesLowCardinalityKey() : void
    {
        ( new CmsSearchPulseRecorder )->record( new Searched( 'term', 12, 3, 5.2, 'example.org', 'en', 'test' ) );

        $key = $this->key( 0 );

        $this->assertSame( 'cms_search:test', $this->pulse->entries[0]->type );
        $this->assertSame( 'theme:search', $key['action'] );
        $this->assertSame( 'example.org', $key['domain'] );
        $this->assertSame( 'en', $key['lang'] );
        $this->assertArrayNotHasKey( 'query', $key );
        $this->assertArrayNotHasKey( 'results', $key );
        $this->assertArrayNotHasKey( 'page', $key );
    }


    public function testJsonapiRecorderUsesLowCardinalityKey() : void
    {
        ( new CmsJsonapiPulseRecorder )->record( new Queried( 'jsonapi:search', 4.8, 'example.org', 'children', 'test' ) );

        $key = $this->key( 0 );

        $this->assertSame( 'cms_jsonapi:test', $this->pulse->entries[0]->type );
        $this->assertSame( 'jsonapi:search', $key['action'] );
        $this->assertSame( 'example.org', $key['domain'] );
        $this->assertArrayNotHasKey( 'includes', $key );
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

        $key = $this->key( 0 );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_contact:test', $this->pulse->entries[0]->type );
        $this->assertSame( 'theme:contact', $key['action'] );
        $this->assertArrayNotHasKey( 'email', $key );
        $this->assertArrayNotHasKey( 'ip', $key );
    }


    public function testRecorderFailuresAreReportedThroughLaravelOncePerThrottleWindow() : void
    {
        $handler = new class implements ExceptionHandler {
            /**
             * @var list<\Throwable>
             */
            public array $reported = [];


            public function report( \Throwable $e ) : void
            {
                $this->reported[] = $e;
            }


            public function shouldReport( \Throwable $e ) : bool
            {
                return true;
            }


            public function render( $request, \Throwable $e ) : \Symfony\Component\HttpFoundation\Response
            {
                return new \Symfony\Component\HttpFoundation\Response( '', 500 );
            }


            public function renderForConsole( $output, \Throwable $e ) : void
            {
            }
        };

        $this->application()->instance( ExceptionHandler::class, $handler );
        $this->application()->instance( \Laravel\Pulse\Pulse::class, new class {
            public function record( string $type, string $key, ?int $value = null ) : never
            {
                throw new \RuntimeException( 'cms-pulse-recorder-test-failure' );
            }
        } );

        ( new CmsContentPulseRecorder )->record( new ContentChanged( 'page', 'saved', source: 'graphql' ) );
        ( new CmsContentPulseRecorder )->record( new ContentChanged( 'page', 'saved', source: 'graphql' ) );

        $this->assertCount( 1, $handler->reported );
        $this->assertSame( 'cms-pulse-recorder-test-failure', $handler->reported[0]->getMessage() );
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
