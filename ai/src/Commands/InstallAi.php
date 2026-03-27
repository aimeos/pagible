<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallAi extends Command
{
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

        $this->comment( '  Publishing Prism PHP configuration ...' );
        $result += $this->call( 'vendor:publish', ['--tag' => 'prism-config'] );

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
        $filename = 'graphql/schema.graphql';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $string = '#import cms-ai.graphql';

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
