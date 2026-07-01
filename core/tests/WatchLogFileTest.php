<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\ThemeServiceProvider;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Orchestra\Testbench\TestCase;


/**
 * End-to-end test: a dispatched event must produce a real JSON log line on disk through the
 * provider-registered listeners and log channel (the unit tests only mock the Log facade).
 *
 * Content events are logged by the listener registered in CoreServiceProvider, searches by the
 * one in ThemeServiceProvider; both providers are loaded so the real registrations are exercised.
 */
class WatchLogFileTest extends TestCase
{
    private string $path = '';


    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class, ThemeServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $this->path = (string) tempnam( sys_get_temp_dir(), 'cmswatch' );

        $app['config']->set( 'logging.channels.cmsfile', [
            'driver' => 'single',
            'path' => $this->path,
            'formatter' => JsonFormatter::class,
        ] );
        $app['config']->set( 'cms.watch.channel', 'cmsfile' );
        $app['config']->set( 'cms.broadcast', false );
    }


    protected function tearDown() : void
    {
        if( $this->path !== '' && file_exists( $this->path ) ) {
            unlink( $this->path );
        }

        parent::tearDown();
    }


    public function testWritesJsonLineToDisk() : void
    {
        event( new Saved( 'page', 'p1', 'v1', 'editor@test', ['path' => 'about', 'domain' => ''], tenant: 'test', source: 'graphql' ) );

        $entry = $this->lastEntry();

        $this->assertSame( 'cms.page', $entry['message'] );
        $this->assertSame( 'saved', $entry['context']['action'] );
        $this->assertSame( 'page', $entry['context']['type'] );
        $this->assertSame( 'graphql', $entry['context']['source'] );
        $this->assertSame( ['p1'], $entry['context']['ids'] );
        $this->assertSame( 'about', $entry['context']['path'] );
        $this->assertNotEmpty( $entry['context']['request_id'] ); // generated correlation id (#7)
    }


    public function testSampleZeroDropsReadStreamButKeepsAudit() : void
    {
        config( ['cms.watch.sample' => 0.0] );

        // High-volume read stream is sampled out completely ...
        event( new Searched( 'term', 3, 1, 12.0, '', 'en', 'test' ) );
        $this->flush();
        $this->assertSame( '', trim( (string) file_get_contents( $this->path ) ) );

        // ... while the content audit stream stays complete regardless of the sampling rate.
        event( new Saved( 'page', 'p1', 'v1', 'editor@test', [], tenant: 'test', source: 'cli' ) );
        $this->assertSame( 'cms.page', $this->lastEntry()['message'] );
    }


    /**
     * Flushes the buffered Monolog stream handlers so writes land on disk before reading.
     */
    private function flush() : void
    {
        foreach( Log::channel( 'cmsfile' )->getLogger()->getHandlers() as $handler ) {
            $handler->close();
        }
    }


    /**
     * Returns the decoded last JSON log entry written to the file.
     *
     * @return array<string, mixed>
     */
    private function lastEntry() : array
    {
        $this->flush();

        $lines = array_filter( explode( "\n", trim( (string) file_get_contents( $this->path ) ) ) );
        $entry = json_decode( (string) end( $lines ), true );

        $this->assertIsArray( $entry );

        return $entry;
    }
}
