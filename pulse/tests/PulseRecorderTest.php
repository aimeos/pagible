<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace {
    require_once __DIR__ . '/../src/PulseServiceProvider.php';
    require_once __DIR__ . '/../src/Recorders/Recorder.php';
    require_once __DIR__ . '/../src/Recorders/ContentPulseRecorder.php';
    require_once __DIR__ . '/../src/Recorders/CmsAiPulseRecorder.php';
    require_once __DIR__ . '/../src/Recorders/CmsAuthPulseRecorder.php';
    require_once __DIR__ . '/../src/Recorders/CmsContactPulseRecorder.php';
    require_once __DIR__ . '/../src/Recorders/CmsElementPulseRecorder.php';
    require_once __DIR__ . '/../src/Recorders/CmsFilePulseRecorder.php';
    require_once __DIR__ . '/../src/Recorders/CmsJsonapiPulseRecorder.php';
    require_once __DIR__ . '/../src/Recorders/CmsPagePulseRecorder.php';
    require_once __DIR__ . '/../src/Recorders/CmsSearchPulseRecorder.php';
}


namespace Tests {

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\Events\Queried;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\PulseServiceProvider;
use Aimeos\Cms\Recorders\CmsAiPulseRecorder;
use Aimeos\Cms\Recorders\CmsAuthPulseRecorder;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsElementPulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsPagePulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;
use Orchestra\Testbench\TestCase;


class PulseRecorderTest extends TestCase
{
    protected FakePulse $pulse;


    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class, PulseServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
        $app['config']->set( 'app.key', 'base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF' );
    }


    protected function setUp() : void
    {
        parent::setUp();

        $this->pulse = new FakePulse;
        $this->app->instance( \Laravel\Pulse\Pulse::class, $this->pulse );
    }


    public function testRecordsPageAction() : void
    {
        ( new CmsPagePulseRecorder )->record(
            new Saved( 'page', 'p1', 'v1', 'editor@test', ['path' => 'about', 'domain' => 'example.org'],
                tenant: 'test', source: 'graphql' )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_page', $this->pulse->entries[0]->type );
        $this->assertSame( ['count'], $this->pulse->entries[0]->aggregates );

        $key = json_decode( $this->pulse->entries[0]->key, true );

        $this->assertSame( 'graphql:save', $key['action'] );
        $this->assertSame( 'editor@test', $key['editor'] );
        $this->assertSame( 'about', $key['path'] );
        $this->assertSame( 'test', $key['tenant'] );
    }


    public function testIgnoresOtherContentTypes() : void
    {
        ( new CmsElementPulseRecorder )->record(
            new Saved( 'page', 'p1', 'v1', 'editor@test', [], tenant: 'test', source: 'graphql' )
        );

        $this->assertSame( [], $this->pulse->entries );
    }


    public function testRecordsBulkItemCount() : void
    {
        ( new CmsElementPulseRecorder )->record(
            new Bulk( 'element', ['e1', 'e2'], ['e1' => 'v1', 'e2' => 'v2'], [], 'editor@test', 'test', 'mcp' )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_element', $this->pulse->entries[0]->type );
        $this->assertSame( 2, $this->pulse->entries[0]->value );
        $this->assertSame( ['count', 'sum'], $this->pulse->entries[0]->aggregates );

        $key = json_decode( $this->pulse->entries[0]->key, true );

        $this->assertSame( 'mcp:bulk', $key['action'] );
    }


    public function testAuthRecorderAnonymizesPersonalData() : void
    {
        ( new CmsAuthPulseRecorder )->record(
            new Authed( 'login-fail', 'user@example.org', '127.0.0.1', 'Browser/1.0', 'test' )
        );

        $key = json_decode( $this->pulse->entries[0]->key, true );

        $this->assertSame( 'graphql:login-fail', $key['action'] );
        $this->assertNotSame( 'user@example.org', $key['email'] );
        $this->assertNotSame( '127.0.0.1', $key['ip'] );
        $this->assertSame( 64, strlen( $key['email'] ) );
    }


    public function testAiRecorderRecordsLatencyAndTokens() : void
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

        $this->assertSame( ['cms_ai', 'cms_ai_input_tokens', 'cms_ai_output_tokens'],
            array_map( fn( FakePulseEntry $entry ) => $entry->type, $this->pulse->entries )
        );

        $this->assertSame( 13, $this->pulse->entries[0]->value );
        $this->assertSame( ['count', 'avg', 'max'], $this->pulse->entries[0]->aggregates );
        $this->assertSame( 100, $this->pulse->entries[1]->value );
        $this->assertSame( 25, $this->pulse->entries[2]->value );

        $key = json_decode( $this->pulse->entries[0]->key, true );

        $this->assertSame( 'ai:write', $key['mutation'] );
        $this->assertTrue( $key['success'] );
    }


    public function testSampledRecordersRespectDisabledSampling() : void
    {
        config( ['cms.watch.sample' => 0.0] );

        ( new CmsSearchPulseRecorder )->record( new Searched( 'term', 0, 1, 5.2, 'example.org', 'en', 'test' ) );
        ( new CmsContactPulseRecorder )->record( new Contacted( 'user@example.org', '127.0.0.1', 3.1, 'test' ) );
        ( new CmsJsonapiPulseRecorder )->record( new Queried( 'jsonapi:search', 4.8, 'example.org', 'children', 'test' ) );

        $this->assertSame( [], $this->pulse->entries );
    }
}


class FakePulse
{
    /**
     * @var list<FakePulseEntry>
     */
    public array $entries = [];


    public function record( string $type, string $key, ?int $value = null ) : FakePulseEntry
    {
        return $this->entries[] = new FakePulseEntry( $type, $key, $value );
    }
}


class FakePulseEntry
{
    /**
     * @var list<string>
     */
    public array $aggregates = [];


    public function __construct(
        public string $type,
        public string $key,
        public ?int $value = null,
    ) {}


    public function count() : self
    {
        $this->aggregates[] = 'count';
        return $this;
    }


    public function avg() : self
    {
        $this->aggregates[] = 'avg';
        return $this;
    }


    public function max() : self
    {
        $this->aggregates[] = 'max';
        return $this;
    }


    public function sum() : self
    {
        $this->aggregates[] = 'sum';
        return $this;
    }
}
}
