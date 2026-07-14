<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallMcp extends Command
{
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
        $filename = 'routes/ai.php';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $updated = preg_replace_callback(
            '/Mcp::web\([^;]*(?:\\\\Aimeos\\\\Cms\\\\Mcp\\\\)?CmsServer::class[^;]*;/s',
            fn( array $matches ) => str_replace( 'throttle:cms-admin', 'throttle:cms-mcp', $matches[0] ),
            $content
        ) ?? $content;

        if( $updated !== $content ) {
            file_put_contents( base_path( $filename ), $updated );
            $this->line( sprintf( '  File [%1$s] updated' . PHP_EOL, $filename ) );
        } else {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        }

        return 0;
    }
}
