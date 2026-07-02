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
use Aimeos\Cms\Events\Viewed;
use Aimeos\Cms\Recorders\CmsAiPulseRecorder;
use Aimeos\Cms\Recorders\CmsAuthPulseRecorder;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsContentPulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsRequestPulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;
use Illuminate\Contracts\Debug\ExceptionHandler;


class PulseRecorderTest extends PulseTestCase
{
    public function testRecordsGraphqlContentInGraphqlBucket() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new ContentChanged( 'page', 'saved', source: 'graphql', tenant: 'test', domain: 'example.org' )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_graphql:test', $this->pulse->entries[0]->type );
        $this->assertSame( ['count'], $this->pulse->entries[0]->aggregates );

        $key = $this->key( 0 );

        $this->assertSame( 'page:save', $key['action'] );
        $this->assertSame( 'example.org', $key['domain'] );
        $this->assertArrayNotHasKey( 'source', $key );
        $this->assertArrayNotHasKey( 'editor', $key );
        $this->assertArrayNotHasKey( 'path', $key );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testRecordsMcpContentInMcpBucket() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new ContentChanged( 'file', 'published', source: 'mcp', tenant: 'test', mime: 'image/png' )
        );

        $this->assertSame( 'cms_mcp:test', $this->pulse->entries[0]->type );

        $key = $this->key( 0 );

        $this->assertSame( 'file:publish', $key['action'] );
        $this->assertSame( 'image/png', $key['mime'] );
        $this->assertArrayNotHasKey( 'source', $key );
    }


    public function testRecordsOtherSourcesInOwnBucket() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new ContentChanged( 'file', 'saved', source: 'cli', tenant: 'test', mime: 'image/png' )
        );

        $this->assertSame( 'cms_cli:test', $this->pulse->entries[0]->type );

        $key = $this->key( 0 );

        $this->assertSame( 'file:save', $key['action'] );
        $this->assertSame( 'image/png', $key['mime'] );
        $this->assertArrayNotHasKey( 'source', $key );
    }


    public function testTenantlessEntriesKeepBaseType() : void
    {
        ( new CmsContentPulseRecorder )->record(
            new ContentChanged( 'page', 'saved', source: 'graphql' )
        );

        $this->assertSame( 'cms_graphql', $this->pulse->entries[0]->type );
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
        $this->assertSame( 'cms_mcp:test', $this->pulse->entries[0]->type );
        $this->assertSame( 2, $this->pulse->entries[0]->value );
        $this->assertSame( ['count', 'sum'], $this->pulse->entries[0]->aggregates );
        $this->assertSame( 'element:bulk', $this->key( 0 )['action'] );
    }


    public function testAuthRecorderUsesLowCardinalityKey() : void
    {
        ( new CmsAuthPulseRecorder )->record(
            new Authed( 'login-fail', 'user@example.org', '127.0.0.1', 'Browser/1.0', 'test' )
        );

        $key = $this->key( 0 );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_graphql:test', $this->pulse->entries[0]->type );
        $this->assertSame( 'auth:login-fail', $key['action'] );
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


    public function testSearchRecorderRespectsDisabledSampling() : void
    {
        // Sampled in the recorder so the event can still fire for the audit log.
        config( ['cms.watch.sample' => 0.0] );

        ( new CmsSearchPulseRecorder )->record( new Searched( 'term', 12, 3, 5.2, 'example.org', 'en', 'test' ) );

        $this->assertSame( [], $this->pulse->entries );
    }


    public function testJsonapiRecorderRespectsDisabledSampling() : void
    {
        config( ['cms.watch.sample' => 0.0] );

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
        $this->assertSame( '127.0.0.1', $key['ip'] );
        $this->assertArrayNotHasKey( 'email', $key );
    }


    public function testPageRequestRecorderKeysSuccessByPath() : void
    {
        ( new CmsRequestPulseRecorder )->record(
            new Viewed( path: 'about', domain: 'example.org', status: 200, durationMs: 4.2, tenant: 'test' )
        );

        $key = $this->key( 0 );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_request:test', $this->pulse->entries[0]->type );
        $this->assertSame( ['count', 'avg', 'max'], $this->pulse->entries[0]->aggregates );
        $this->assertSame( 4, $this->pulse->entries[0]->value );
        $this->assertSame( 'theme:view', $key['action'] );
        $this->assertSame( '/about', $key['path'] );
        $this->assertSame( 'example.org', $key['domain'] );
        $this->assertSame( 200, $key['status'] );
        $this->assertArrayNotHasKey( 'cached', $key );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testPageRequestRecorderMapsHomePathToSlash() : void
    {
        // Home's empty path would be stripped by Recorder::key()'s empty filter,
        // so it is mapped to "/".
        ( new CmsRequestPulseRecorder )->record(
            new Viewed( path: '', status: 200, tenant: 'test' )
        );

        $this->assertSame( '/', $this->key( 0 )['path'] );
    }


    public function testPageRequestRecorderBucketsNonSuccessPaths() : void
    {
        // A spoofed Host on a 404 must not become a key dimension, else it defeats
        // the "*" bucket and allows unbounded cardinality.
        ( new CmsRequestPulseRecorder )->record(
            new Viewed( path: 'random-bot-scan-url', domain: 'spoofed.example', status: 404, tenant: 'test' )
        );

        $key = $this->key( 0 );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( '*', $key['path'] );
        $this->assertSame( 404, $key['status'] );
        $this->assertArrayNotHasKey( 'domain', $key );
    }


    public function testPageRequestRecorderIgnoresSampling() : void
    {
        // Sampled at the dispatch site (ServeCachedPage), so the recorder records
        // unconditionally.
        config( ['cms.watch.sample' => 0.0] );

        ( new CmsRequestPulseRecorder )->record(
            new Viewed( path: 'about', status: 200, tenant: 'test' )
        );

        $this->assertCount( 1, $this->pulse->entries );
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
