<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Schema;
use Database\Seeders\AbstractDemo;
use Illuminate\Console\Command;


class Demo extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:demo
        {--theme= : Theme name applied to the demo pages (default theme if empty)}
        {--tenant= : Tenant ID to seed the demo content into (theme name if empty)}
        {--all : Seed every registered theme into its own tenant (tenant = theme name)}';

    /**
     * Command description
     */
    protected $description = 'Seed theme-specific demo content';


    /**
     * Execute command
     */
    public function handle() : int
    {
        if( $this->option( 'all' ) )
        {
            foreach( array_keys( Schema::all() ) as $theme )
            {
                if( $theme === 'cms' ) {
                    continue;
                }

                $this->seed( $theme, $theme );
            }

            return 0;
        }

        $theme = (string) ( $this->option( 'theme' ) ?? '' );
        $tenant = (string) ( $this->option( 'tenant' ) ?? '' ) ?: $theme;

        $this->seed( $theme, $tenant );

        return 0;
    }


    /**
     * Seeds the demo content for one theme into one tenant.
     *
     * @param string $theme Theme name
     * @param string $tenant Tenant ID
     */
    protected function seed( string $theme, string $tenant ) : void
    {
        $this->comment( sprintf( '  Seeding "%s" demo into tenant "%s" ...', $theme ?: 'default', $tenant ?: '-' ) );

        AbstractDemo::create( $theme, $tenant )->seed();
    }
}
