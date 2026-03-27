<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallGraphql extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:graphql';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS GraphQL package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing Lighthouse schema ...' );
        $result += $this->call( 'vendor:publish', ['--tag' => 'lighthouse-schema'] );

        $this->comment( '  Publishing Lighthouse configuration ...' );
        $result += $this->call( 'vendor:publish', ['--tag' => 'lighthouse-config'] );

        $this->comment( '  Updating Lighthouse configuration ...' );
        $result += $this->lighthouse();

        $this->comment( '  Adding CMS GraphQL schema ...' );
        $result += $this->schema();

        $this->comment( '  Publishing CMS GraphQL files ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Aimeos\Cms\GraphqlServiceProvider'] );

        return $result ? 1 : 0;
    }


    /**
     * Updates Lighthouse configuration
     *
     * @return int 0 on success, 1 on failure
     */
    protected function lighthouse() : int
    {
        $done = 0;
        $filename = 'config/lighthouse.php';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $string = ", 'Aimeos\\\\Cms\\\\Models'";

        if( strpos( $content, $string ) === false )
        {
            $content = str_replace( "'App\\\\Models'", "'App\\\\Models'" . $string, $content );
            $this->line( sprintf( '  Added CMS models directory to [%1$s]' . PHP_EOL, $filename ) );
            $done++;
        }

        $string = ", 'Aimeos\\\\Cms\\\\GraphQL\\\\Mutations'";

        if( strpos( $content, $string ) === false )
        {
            $content = str_replace( " 'App\\\\GraphQL\\\\Mutations'", " ['App\\\\GraphQL\\\\Mutations'" . $string . "]", $content );
            $this->line( sprintf( '  Added CMS mutations directory to [%1$s]' . PHP_EOL, $filename ) );
            $done++;
        }

        if( strpos( $content, $string ) === false )
        {
            $content = str_replace( "['App\\\\GraphQL\\\\Mutations'", "['App\\\\GraphQL\\\\Mutations'" . $string, $content );
            $this->line( sprintf( '  Added CMS mutations directory to [%1$s]' . PHP_EOL, $filename ) );
            $done++;
        }

        $string = ", 'Aimeos\\\\Cms\\\\GraphQL\\\\Queries'";

        if( strpos( $content, $string ) === false )
        {
            $content = str_replace( " 'App\\\\GraphQL\\\\Queries'", " ['App\\\\GraphQL\\\\Queries'" . $string . "]", $content );
            $this->line( sprintf( '  Added CMS queries directory to [%1$s]' . PHP_EOL, $filename ) );
            $done++;
        }

        if( strpos( $content, $string ) === false )
        {
            $content = str_replace( "['App\\\\GraphQL\\\\Queries'", "['App\\\\GraphQL\\\\Queries'" . $string, $content );
            $this->line( sprintf( '  Added CMS queries directory to [%1$s]' . PHP_EOL, $filename ) );
            $done++;
        }

        $string = "
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ";

        if( strpos( $content, '\Illuminate\Session\Middleware\StartSession::class' ) === false )
        {
            $content = str_replace( "'middleware' => [", "'middleware' => [" . $string, $content );
            $this->line( sprintf( '  Added EncryptCookies/AddQueuedCookiesToResponse/StartSession middlewares to [%1$s]' . PHP_EOL, $filename ) );
            $done++;
        }

        if( $done ) {
            file_put_contents( base_path( $filename ), $content );
        } else {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        }

        return 0;
    }


    /**
     * Updates Lighthouse GraphQL schema file
     *
     * @return int 0 on success, 1 on failure
     */
    protected function schema() : int
    {
        $filename = 'graphql/schema.graphql';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $string = '#import cms.graphql';

        if( strpos( $content, $string ) === false )
        {
            file_put_contents( base_path( $filename ), $content . "\n\n" . $string );
            $this->line( sprintf( '  File [%1$s] updated' . PHP_EOL, $filename ) );
        }
        else
        {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        }

        return 0;
    }
}
