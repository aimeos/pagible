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
        $domain = (string) ($this->option( 'domain' ) ?: ''); // @phpstan-ignore cast.string
        $editor = (string) $this->option( 'editor' ); // @phpstan-ignore cast.string

        foreach( $langs as $lang )
        {
            $this->info( "Creating demo data for language: {$lang}" );
            $seeder->run( (string) $lang, $domain, $editor );
        }

        $this->info( 'Indexing ...' );
        Page::makeAllSearchable();
        Element::makeAllSearchable();
        File::makeAllSearchable();

        $this->info( 'Done!' );

        return 0;
    }
}
