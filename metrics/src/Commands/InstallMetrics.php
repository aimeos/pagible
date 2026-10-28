<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class InstallMetrics extends Command
{
    protected $signature = 'cms:install:metrics';
    protected $description = 'Installing Pagible CMS metrics package';


    public function handle() : int
    {
        $this->comment( '  Publishing Analytics Bridge files ...' );
        $result = $this->call( 'vendor:publish', ['--provider' => 'Aimeos\AnalyticsBridge\ServiceProvider'] );

        if( array_key_exists( 'lighthouse:clear-cache', Artisan::all() ) ) {
            $this->comment( '  Clearing Lighthouse schema cache ...' );
            $result += $this->call( 'lighthouse:clear-cache' );
        }

        return $result ? 1 : 0;
    }
}
