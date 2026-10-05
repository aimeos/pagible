<?php

/**
 * @license MIT, https://opensource.org/license/mit
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
    public function handle(): int
    {
        $result = 0;
        $seed = (bool) $this->option( 'seed' );

        $all = collect( Artisan::all() )->filter( fn( $cmd, $name ) => str_starts_with( $name, 'cms:install:' ) )->keys();

        $commands = collect( ['cms:install:core', 'cms:install:graphql', 'cms:install:ai'] )
            ->merge( $all )->unique()->filter( fn( $name ) => $all->contains( $name ) );

        foreach( $commands as $command )
        {
            $this->comment( sprintf( '  Running %s ...', $command ) );
            $options = $seed && $this->getApplication()?->find( $command )->getDefinition()->hasOption( 'seed' ) ? ['--seed' => true] : [];
            $result += $this->call( $command, $options );
        }

        if( $result ) {
            $this->error( '  Error during Pagible CMS installation!' );
            return self::FAILURE;
        }

        $this->line( self::$template );
        return self::SUCCESS;
    }
}
