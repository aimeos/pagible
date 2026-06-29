<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallWatch extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:watch';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS watch (observability) package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $this->comment( '  Publishing watch configuration ...' );
        $result = $this->call( 'vendor:publish', ['--tag' => 'cms-watch-config'] );

        return $result ? 1 : 0;
    }
}
