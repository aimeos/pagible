<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;


class InstallJsonapi extends Command
{
    use PatchesFiles;


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
        $string = '
        $exceptions->dontReport(
            \LaravelJsonApi\Core\Exceptions\JsonApiException::class,
        );
        $exceptions->render(
            \LaravelJsonApi\Exceptions\ExceptionParser::renderer(),
        );';

        return $this->insert( 'bootstrap/app.php', "->withExceptions(function (Exceptions \$exceptions) {\n", $string,
            '\LaravelJsonApi\Exceptions\ExceptionParser', '  Added JSON:API exception handler to [%1$s]' . PHP_EOL );
    }


    /**
     * Updates JSON:API configuration
     *
     * @return int 0 on success, 1 on failure
     */
    protected function jsonapi() : int
    {
        $string = "
        'cms' => \Aimeos\Cms\JsonApi\V1\Server::class,
        ";

        return $this->insert( 'config/jsonapi.php', "'servers' => [", $string,
            '\Aimeos\Cms\JsonApi\V1\Server::class', '  Added CMS JSON:API server to [%1$s]' . PHP_EOL );
    }
}
