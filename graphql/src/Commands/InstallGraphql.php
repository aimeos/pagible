<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;


class InstallGraphql extends Command
{
    use PatchesFiles;


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

        $this->comment( '  Updating CMS GraphQL rate limiter ...' );
        $result += $this->limiter();

        return $result ? 1 : 0;
    }


    /**
     * Updates Lighthouse configuration
     *
     * @return int 0 on success, 1 on failure
     */
    protected function lighthouse() : int
    {
        $filename = 'config/lighthouse.php';

        return $this->patch( $filename, function( string $content ) use ( $filename ) {

            $string = ", 'Aimeos\\\\Cms\\\\Models'";

            if( strpos( $content, $string ) === false )
            {
                $content = str_replace( "'App\\\\Models'", "'App\\\\Models'" . $string, $content );
                $this->line( sprintf( '  Added CMS models directory to [%1$s]' . PHP_EOL, $filename ) );
            }

            $string = ", 'Aimeos\\\\Cms\\\\GraphQL\\\\Mutations'";

            if( strpos( $content, $string ) === false )
            {
                $content = str_replace( " 'App\\\\GraphQL\\\\Mutations'", " ['App\\\\GraphQL\\\\Mutations'" . $string . "]", $content );
                $this->line( sprintf( '  Added CMS mutations directory to [%1$s]' . PHP_EOL, $filename ) );
            }

            if( strpos( $content, $string ) === false )
            {
                $content = str_replace( "['App\\\\GraphQL\\\\Mutations'", "['App\\\\GraphQL\\\\Mutations'" . $string, $content );
                $this->line( sprintf( '  Added CMS mutations directory to [%1$s]' . PHP_EOL, $filename ) );
            }

            $string = "
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ";

            if( strpos( $content, '\Illuminate\Session\Middleware\StartSession::class' ) === false )
            {
                $content = str_replace( "'middleware' => [", "'middleware' => [" . $string, $content );
                $this->line( sprintf( '  Added EncryptCookies/AddQueuedCookiesToResponse/StartSession/ValidateCsrfToken middlewares to [%1$s]' . PHP_EOL, $filename ) );
            }
            elseif( strpos( $content, 'ValidateCsrfToken::class' ) === false && strpos( $content, 'VerifyCsrfToken::class' ) === false )
            {
                // Session middleware was added by an earlier version without CSRF protection
                $content = str_replace(
                    "\Illuminate\Session\Middleware\StartSession::class,",
                    "\Illuminate\Session\Middleware\StartSession::class,\n            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,",
                    $content
                );
                $this->line( sprintf( '  Added ValidateCsrfToken middleware to [%1$s]' . PHP_EOL, $filename ) );
            }

            return $content;
        }, null );
    }


    /**
     * Updates the limiter in existing CMS GraphQL schema files.
     *
     * @return int 0 on success, 1 on failure
     */
    protected function limiter() : int
    {
        return $this->patch( 'graphql/cms.graphql', fn( string $content ) => str_replace( 'cms-admin', 'cms-graphql', $content ) );
    }


    /**
     * Updates Lighthouse GraphQL schema file
     *
     * @return int 0 on success, 1 on failure
     */
    protected function schema() : int
    {
        return $this->append( 'graphql/schema.graphql', '#import cms.graphql' );
    }
}
