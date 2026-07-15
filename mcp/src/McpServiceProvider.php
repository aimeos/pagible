<?php

namespace Aimeos\Cms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider as Provider;

class McpServiceProvider extends Provider
{
    public function boot(): void
    {
        // MCP server registration is handled by laravel/mcp package discovery
        RateLimiter::for( 'cms-mcp', fn( $request ) =>
            Limit::perMinute( 120 )->by( $request->user()?->getAuthIdentifier() ?: $request->ip() )
        );

        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkMcp::class,
                \Aimeos\Cms\Commands\InstallMcp::class,
            ] );
        }
    }
}
