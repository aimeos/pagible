<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class Install extends Command
{
	private static string $template = '<fg=blue>
    ____              _ __    __     ___    ____   ________  ________
   / __ \____ _____ _(_) /_  / /__  /   |  /  _/  / ____/  |/  / ___/
  / /_/ / __ `/ __ `/ / __ \/ / _ \/ /| |  / /   / /   / /|_/ /\__ \
 / ____/ /_/ / /_/ / / /_/ / /  __/ ___ |_/ /   / /___/ /  / /___/ /
/_/    \__,_/\__, /_/_.___/_/\___/_/  |_/___/   \____/_/  /_//____/
            /____/
</>
Congratulations! You successfully set up <fg=green>Pagible CMS</>!
<fg=cyan>Give a star and contribute</>: https://github.com/aimeos/pagible
Made with <fg=green>love</> by the Pagible CMS community. Be a part of it!
';


    /**
     * Command name
     */
    protected $signature = 'cms:install  {--seed : Add example pages to the database}';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS package';


    /**
     * Execute command
     */
    public function handle(): void
    {
        $result = 0;
        $options = $this->option( 'seed' ) ? ['--seed' => true] : [];

        $commands = collect( Artisan::all() )
            ->filter( fn( $cmd, $name ) => str_starts_with( $name, 'cms:install:' ) )
            ->keys()
            ->sort();

        foreach( $commands as $command )
        {
            $this->comment( sprintf( '  Running %s ...', $command ) );
            $result += $this->call( $command, $options );
        }

        if( $result ) {
            $this->error( '  Error during Pagible CMS installation!' );
        } else {
            $this->line( self::$template );
        }
    }
}
