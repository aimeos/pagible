<?php

namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;

class McpServiceProvider extends Provider
{
    public function boot(): void
    {
        // MCP server registration is handled by laravel/mcp package discovery

        $this->console();
    }

    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkMcp::class,
                \Aimeos\Cms\Commands\InstallMcp::class,
            ] );
        }
    }
}
