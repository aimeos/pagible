<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\Events\CmsContact;
use Aimeos\Cms\Events\CmsGraphql;
use Aimeos\Cms\Events\CmsMcp;
use Aimeos\Cms\Events\CmsJsonapi;
use Aimeos\Cms\Events\CmsSearch;
use Aimeos\Cms\Events\CmsRequest;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsGraphqlPulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsMcpPulseRecorder;
use Aimeos\Cms\Recorders\CmsRequestPulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;
use Illuminate\Contracts\Debug\ExceptionHandler;


class PulseRecorderTest extends PulseTestCase
{
    public function testGraphqlRecorderRecordsRequestLatency() : void
    {
        ( new CmsGraphqlPulseRecorder )->record(
            new CmsGraphql( action: 'pages', durationMs: 12.7, tenant: 'test',
                domain: 'example.org', success: true )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_graphql:test', $this->pulse->entries[0]->type );
        $this->assertSame( 13, $this->pulse->entries[0]->value );
        $this->assertSame( ['count', 'avg', 'max'], $this->pulse->entries[0]->aggregates );

        $key = $this->key( 0 );

        $this->assertSame( 'pages', $key['action'] );
        $this->assertSame( 'example.org', $key['domain'] );
        $this->assertTrue( $key['success'] );
        $this->assertArrayNotHasKey( 'source', $key );
        $this->assertArrayNotHasKey( 'editor', $key );
        $this->assertArrayNotHasKey( 'provider', $key );
        $this->assertArrayNotHasKey( 'model', $key );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testMcpRecorderRecordsRequestLatency() : void
    {
        ( new CmsMcpPulseRecorder )->record(
            new CmsMcp( action: 'save-page', durationMs: 8.2, tenant: 'test', success: false )
        );

        $this->assertSame( 'cms_mcp:test', $this->pulse->entries[0]->type );
        $this->assertSame( ['count', 'avg', 'max'], $this->pulse->entries[0]->aggregates );

        $key = $this->key( 0 );

        $this->assertSame( 'save-page', $key['action'] );
        $this->assertFalse( $key['success'] );
        $this->assertArrayNotHasKey( 'source', $key );
    }


    public function testGraphqlRecorderIgnoresOtherEvents() : void
    {
        ( new CmsGraphqlPulseRecorder )->record(
            new CmsRequest( path: 'about', durationMs: 8.2, tenant: 'test' )
        );

        $this->assertSame( [], $this->pulse->entries );
    }


    public function testTenantlessEntriesKeepBaseType() : void
    {
        ( new CmsGraphqlPulseRecorder )->record(
            new CmsGraphql( action: 'page', durationMs: 1.0 )
        );

        $this->assertSame( 'cms_graphql', $this->pulse->entries[0]->type );
    }


    public function testGraphqlRecorderIgnoresSampling() : void
    {
        config( ['cms.watch.sample' => 0.0] );

        ( new CmsGraphqlPulseRecorder )->record(
            new CmsGraphql( action: 'cmsLogin', durationMs: 5.2, tenant: 'test' )
        );

        $this->assertCount( 1, $this->pulse->entries );
    }


    public function testSearchRecorderUsesLowCardinalityKey() : void
    {
        ( new CmsSearchPulseRecorder )->record( new CmsSearch( 'term', 12, 3, 5.2, 'example.org', 'en', 'test' ) );

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
        ( new CmsJsonapiPulseRecorder )->record( new CmsJsonapi( 'jsonapi:search', 4.8, 'example.org', 'children', 'test' ) );

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

        ( new CmsSearchPulseRecorder )->record( new CmsSearch( 'term', 12, 3, 5.2, 'example.org', 'en', 'test' ) );

        $this->assertSame( [], $this->pulse->entries );
    }


    public function testJsonapiRecorderRespectsDisabledSampling() : void
    {
        config( ['cms.watch.sample' => 0.0] );

        ( new CmsJsonapiPulseRecorder )->record( new CmsJsonapi( 'jsonapi:search', 4.8, 'example.org', 'children', 'test' ) );

        $this->assertSame( [], $this->pulse->entries );
    }


    public function testContactRecorderIgnoresSampling() : void
    {
        config( ['cms.watch.sample' => 0.0] );

        ( new CmsContactPulseRecorder )->record( new CmsContact( 'user@example.org', '127.0.0.1', 3.1, 'test' ) );

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
            new CmsRequest( path: 'about', domain: 'example.org', status: 200, durationMs: 4.2, tenant: 'test' )
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
            new CmsRequest( path: '', status: 200, tenant: 'test' )
        );

        $this->assertSame( '/', $this->key( 0 )['path'] );
    }


    public function testPageRequestRecorderBucketsNonSuccessPaths() : void
    {
        // A spoofed Host on a 404 must not become a key dimension, else it defeats
        // the "*" bucket and allows unbounded cardinality.
        ( new CmsRequestPulseRecorder )->record(
            new CmsRequest( path: 'random-bot-scan-url', domain: 'spoofed.example', status: 404, tenant: 'test' )
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
            new CmsRequest( path: 'about', status: 200, tenant: 'test' )
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

        ( new CmsGraphqlPulseRecorder )->record( new CmsGraphql( action: 'pages' ) );
        ( new CmsGraphqlPulseRecorder )->record( new CmsGraphql( action: 'pages' ) );

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
