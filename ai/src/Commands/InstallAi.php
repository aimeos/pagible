<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;


class InstallAi extends Command
{
    use PatchesFiles;


    /**
     * Command name
     */
    protected $signature = 'cms:install:ai';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS AI package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing Analytics Bridge files ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Aimeos\AnalyticsBridge\ServiceProvider'] );

        $this->comment( '  Publishing CMS AI files ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Aimeos\Cms\AiServiceProvider'] );

        $this->comment( '  Adding AI GraphQL schema ...' );
        $result += $this->schema();

        return $result ? 1 : 0;
    }


    /**
     * Updates Lighthouse GraphQL schema file to import AI schema
     *
     * @return int 0 on success, 1 on failure
     */
    protected function schema() : int
    {
        return $this->append( 'graphql/schema.graphql', '#import cms-ai.graphql' );
    }
}
