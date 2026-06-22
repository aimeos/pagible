<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Support\Facades\Route;


class BroadcastMiddlewareTest extends CoreTestAbstract
{
    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'cms.broadcast', true );
        // a non-default marker ('signed') so the assertion proves the config is honored
        $app['config']->set( 'cms.broadcast-middleware', ['web', 'auth', 'signed'] );
    }


    public function testAuthRouteUsesConfiguredMiddleware() : void
    {
        $middleware = [];

        foreach( Route::getRoutes()->getRoutes() as $route )
        {
            if( $route->uri() === 'broadcasting/auth' ) {
                $middleware = $route->gatherMiddleware();
            }
        }

        $this->assertContains( 'signed', $middleware ); // proves the config value is used, not the default
        $this->assertContains( 'auth', $middleware );
    }
}
