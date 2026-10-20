<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class Install extends Command
{
    use PatchesFiles;


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

        $this->comment( '  Updating composer scripts ...' );
        $result += $this->composer();

        $this->comment( '  Checking robots.txt ...' );
        $this->robots();

        $this->comment( '  Checking application URL ...' );
        $this->url();

        if( $result ) {
            $this->error( '  Error during Pagible CMS installation!' );
            return self::FAILURE;
        }

        $this->line( self::$template );
        return self::SUCCESS;
    }


    /**
     * Adds the commands updating the published CMS files to the "post-update-cmd" composer script.
     *
     * @return int 0 on success, 1 on failure
     */
    protected function composer() : int
    {
        if( !file_exists( base_path( 'composer.json' ) ) )
        {
            $this->line( '  File [composer.json] not found, skipped' . PHP_EOL );
            return 0;
        }

        return $this->patch( 'composer.json', function( string $content ) {

            if( str_contains( $content, '--tag=cms-admin' ) ) {
                return $content;
            }

            if( !( ( $json = json_decode( $content ) ) instanceof \stdClass ) )
            {
                $this->error( '  File [composer.json] contains invalid JSON!' );
                return null;
            }

            $json->scripts ??= new \stdClass();
            $cmds = (array) ( $json->scripts->{'post-update-cmd'} ?? [] );

            $cmds[] = '@php artisan vendor:publish --force --tag=cms-admin --tag=cms-graphql';
            $cmds[] = '@php artisan vendor:publish --tag=cms-theme';

            if( !preg_grep( '/artisan migrate\b/', $cmds ) ) {
                $cmds[] = '@php artisan migrate';
            }

            $json->scripts->{'post-update-cmd'} = array_values( $cmds );

            return json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
        } );
    }


    /**
     * Removes the default robots.txt of Laravel which hides the dynamic robots.txt route.
     */
    protected function robots() : void
    {
        $path = public_path( 'robots.txt' );

        if( !file_exists( $path ) )
        {
            $this->line( '  Dynamic robots.txt route is available' . PHP_EOL );
            return;
        }

        if( preg_replace( '/\s+/', ' ', trim( (string) file_get_contents( $path ) ) ) === 'User-agent: * Disallow:' && unlink( $path ) )
        {
            $this->line( '  Removed default [public/robots.txt] to enable the dynamic robots.txt route' . PHP_EOL );
            return;
        }

        $this->warn( '  File [public/robots.txt] contains custom rules and hides the dynamic robots.txt route.' );
        $this->line( '  Copy the rules into the "robots.txt" element of the root page, publish it and remove the file.' . PHP_EOL );
    }


    /**
     * Warns if file URLs won't work with the built-in web server.
     */
    protected function url() : void
    {
        $url = (string) config( 'app.url' );
        $parts = parse_url( $url ) ?: [];

        if( in_array( $parts['host'] ?? '', ['localhost', '127.0.0.1'] ) && !isset( $parts['port'] ) )
        {
            $this->warn( sprintf( '  APP_URL [%1$s] has no port, uploaded files won\'t load with the built-in web server (php artisan cms:serve).', $url ) );
            $this->line( '  Add the port of the web server to APP_URL in your .env file, e.g. APP_URL=http://localhost:8000' . PHP_EOL );
            return;
        }

        $this->line( sprintf( '  Using APP_URL [%1$s]' . PHP_EOL, $url ) );
    }
}
