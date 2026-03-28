<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallJsonapi extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:jsonapi';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS JSON:API package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing JSON:API configuration ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'LaravelJsonApi\Laravel\ServiceProvider'] );

        $this->comment( '  Updating JSON:API configuration ...' );
        $result += $this->jsonapi();

        $this->comment( '  Adding JSON:API exception handler ...' );
        $result += $this->exception();

        return $result ? 1 : 0;
    }


    /**
     * Updates application exception handler
     *
     * @return int 0 on success, 1 on failure
     */
    protected function exception() : int
    {
        $done = 0;
        $filename = 'bootstrap/app.php';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $search = "->withExceptions(function (Exceptions \$exceptions) {\n";

        $string = '
        $exceptions->dontReport(
            \LaravelJsonApi\Core\Exceptions\JsonApiException::class,
        );
        $exceptions->render(
            \LaravelJsonApi\Exceptions\ExceptionParser::renderer(),
        );';

        if( strpos( $content, '\LaravelJsonApi\Exceptions\ExceptionParser' ) === false )
        {
            $content = str_replace( $search, $search . $string, $content );
            $this->line( sprintf( '  Added JSON:API exception handler to [%1$s]' . PHP_EOL, $filename ) );
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
     * Updates JSON:API configuration
     *
     * @return int 0 on success, 1 on failure
     */
    protected function jsonapi() : int
    {
        $done = 0;
        $filename = 'config/jsonapi.php';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $string = "
        'cms' => \Aimeos\Cms\JsonApi\V1\Server::class,
        ";

        if( strpos( $content, '\Aimeos\Cms\JsonApi\V1\Server::class' ) === false )
        {
            $done++;
            $content = str_replace( "'servers' => [", "'servers' => [" . $string, $content );
            $this->line( sprintf( '  Added CMS JSON:API server to [%1$s]' . PHP_EOL, $filename ) );
        }

        if( $done ) {
            file_put_contents( base_path( $filename ), $content );
        } else {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        }

        return 0;
    }
}
