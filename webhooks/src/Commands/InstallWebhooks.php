<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class InstallWebhooks extends Command
{
    protected $signature = 'cms:install:webhooks';
    protected $description = 'Installing Pagible CMS webhook package';


    public function handle() : int
    {
        $this->comment( '  Publishing CMS webhook files ...' );
        $result = $this->call( 'vendor:publish', [
            '--provider' => 'Aimeos\Cms\WebhookServiceProvider',
        ] );

        if( array_key_exists( 'lighthouse:clear-cache', Artisan::all() ) ) {
            $this->comment( '  Clearing Lighthouse schema cache ...' );
            $result += $this->call( 'lighthouse:clear-cache' );
        }

        return $result ? 1 : 0;
    }
}
