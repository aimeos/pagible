<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Database\Seeders\DemoSeeder;


class Demo extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:demo
        {--lang=* : Language codes (fallback: en)}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--domain= : Domain name for the pages}
        {--editor=demo : Editor name for records}';

    /**
     * Command description
     */
    protected $description = 'Generates demo data with multilingual page trees for performance testing';


    /**
     * Execute command
     */
    public function handle(): int
    {
        if( !class_exists( \Faker\Factory::class ) )
        {
            $this->error( 'fakerphp/faker is required but not installed.' );
            $this->info( 'Install it with: composer require --dev fakerphp/faker' );
            return 1;
        }

        $this->setupTenant();

        $langs = $this->option( 'lang' ) ?: ['en']; // @phpstan-ignore cast.string
        $domain = (string) ($this->option( 'domain' ) ?: ''); // @phpstan-ignore cast.string
        $editor = (string) $this->option( 'editor' ); // @phpstan-ignore cast.string

        foreach( $langs as $lang )
        {
            $this->info( "Creating demo data for language: {$lang}" );
            ( new DemoSeeder() )->run( $lang, $domain, $editor );
        }

        $this->info( 'Indexing for search...' );
        Page::query()->chunk( 1000, fn( $pages ) => $pages->searchable() );
        Element::query()->chunk( 1000, fn( $elements ) => $elements->searchable() );
        File::query()->chunk( 1000, fn( $files ) => $files->searchable() );

        $this->info( 'Done!' );

        return 0;
    }


    /**
     * Set up tenant if provided
     */
    protected function setupTenant(): void
    {
        if( $tenant = $this->option( 'tenant' ) )
        {
            \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
                return $tenant;
            };
        }
    }
}
