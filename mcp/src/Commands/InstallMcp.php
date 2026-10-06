<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;


class InstallMcp extends Command
{
    use PatchesFiles;


    /**
     * Command name
     */
    protected $signature = 'cms:install:mcp';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS MCP package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing Laravel MCP routes ...' );
        $result += $this->call( 'vendor:publish', ['--tag' => 'ai-routes'] );

        $this->comment( '  Updating CMS MCP rate limiter ...' );
        $result += $this->limiter();

        return $result ? 1 : 0;
    }


    /**
     * Updates the limiter in existing MCP route files.
     *
     * @return int 0 on success, 1 on failure
     */
    protected function limiter() : int
    {
        return $this->patch( 'routes/ai.php', fn( string $content ) => preg_replace_callback(
            '/Mcp::web\([^;]*(?:\\\\Aimeos\\\\Cms\\\\Mcp\\\\)?CmsServer::class[^;]*;/s',
            fn( array $matches ) => str_replace( 'throttle:cms-admin', 'throttle:cms-mcp', $matches[0] ),
            $content
        ) ?? $content );
    }
}
