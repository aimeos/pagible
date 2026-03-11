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

        if( $tenant = $this->option( 'tenant' ) )
        {
            \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
                return $tenant;
            };
        }

        $seeder = new DemoSeeder();
        $langs = (array) $this->option( 'lang' ) ?: ['en'];
        $domain = (string) ($this->option( 'domain' ) ?: '');
        $editor = (string) $this->option( 'editor' );

        foreach( $langs as $lang )
        {
            $this->info( "Creating demo data for language: {$lang}" );
            $seeder->run( (string) $lang, $domain, $editor );
        }

        $this->info( 'Indexing ...' );
        Page::query()->chunk( 1000, fn( $pages ) => $pages->searchable() ); // @phpstan-ignore method.notFound
        Element::query()->chunk( 1000, fn( $elements ) => $elements->searchable() ); // @phpstan-ignore method.notFound
        File::query()->chunk( 1000, fn( $files ) => $files->searchable() ); // @phpstan-ignore method.notFound

        $this->info( 'Done!' );

        return 0;
    }
}
