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

        return $result ? 1 : 0;
    }
}
