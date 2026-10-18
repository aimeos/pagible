<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CdnServiceProvider;
use Aimeos\Cms\CoreServiceProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Guzzle7\Client;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\PluginClient;
use Orchestra\Testbench\TestCase;
use Psr\Http\Message\RequestInterface;


abstract class CdnTestCase extends TestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    protected array $history = [];


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'app.url', 'https://example.com' );
        $app['config']->set( 'queue.default', 'database' );
        $app['config']->set( 'cms.cdn.delay', 0 );
        $app['config']->set( 'cms.cdn.clients', [
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1']],
        ] );
    }


    protected function getPackageProviders( $app )
    {
        return [
            CoreServiceProvider::class,
            CdnServiceProvider::class,
        ];
    }


    /**
     * Sends the purge requests to a mock client returning the responses and records them in $history.
     */
    protected function http( Response ...$responses ) : void
    {
        $stack = HandlerStack::create( new MockHandler( $responses ) );
        $stack->push( Middleware::history( $this->history ) );

        $this->app->instance( 'cms.cdn.http', new PluginClient(
            new Client( new GuzzleClient( ['handler' => $stack, 'http_errors' => false] ) ), [new ErrorPlugin()]
        ) );
    }
}
