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

        $this->comment( '  Publishing CMS AI files ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Aimeos\Cms\AiServiceProvider'] );

        $this->comment( '  Adding AI GraphQL schema ...' );
        $result += $this->append( 'graphql/schema.graphql', '#import cms-ai.graphql' );

        return $result ? 1 : 0;
    }
}
