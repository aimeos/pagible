<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Commands\Description;
use Aimeos\Cms\Commands\InstallAi;
use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\GraphQL\Directives\AiExceptionDirective;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Tools as CmsTools;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider as Provider;
use Laravel\Mcp\Response;


class AiServiceProvider extends Provider
{
    /**
     * MCP tools calling an AI provider.
     */
    private const TOOLS = [
        CmsTools\RefineContent::class,
        CmsTools\TranslateContent::class,
        CmsTools\TranslatePage::class,
        CmsTools\DescribeFile::class,
        CmsTools\TranscribeAudio::class,
        CmsTools\GenerateImage::class,
        CmsTools\RepaintImage::class,
        CmsTools\IsolateImage::class,
        CmsTools\UpscaleImage::class,
        CmsTools\UncropImage::class,
        CmsTools\EraseImage::class,
        CmsTools\InpaintImage::class,
    ];


    /**
     * Bootstraps the AI package.
     */
    public function boot() : void
    {
        $basedir = dirname( __DIR__ );

        $this->loadViewsFrom( $basedir . '/views', 'cms' );
        $this->loadRoutesFrom( $basedir . '/routes/ai.php' );
        // like the other limits, 0 means no limit
        if( ( $limit = (int) config( 'cms.ai.limit' ) ) > 0 ) {
            Utils::limit( 'cms-ai', $limit );
        } else {
            RateLimiter::for( 'cms-ai', fn() => Limit::none() );
        }

        $this->publishes( [$basedir . '/config/cms/ai.php' => config_path( 'cms/ai.php' )], 'cms-config' );
        $this->publishes( [$basedir . '/graphql/cms-ai.graphql' => base_path( 'graphql/cms-ai.graphql' )], 'cms-graphql' );

        Permission::register( [
            'audio:transcribe',
            'element:chat',
            'file:chat',
            'file:describe',
            'image:erase',
            'image:imagine',
            'image:inpaint',
            'image:isolate',
            'image:repaint',
            'image:uncrop',
            'image:upscale',
            'page:chat',
            'page:refine',
            'text:translate',
            'text:write',
        ] );

        $this->console();
        $this->graphql();
        $this->mcp();
        $this->watch();
    }


    /**
     * Registers the AI services.
     */
    public function register() : void
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/ai.php', 'cms.ai' );
    }


    /**
     * Registers the console commands.
     */
    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                Description::class,
                InstallAi::class,
            ] );
        }
    }


    /**
     * Makes AI provider errors in GraphQL client-safe.
     */
    protected function graphql() : void
    {
        $middleware = (array) config( 'lighthouse.field_middleware', [] );
        config( ['lighthouse.field_middleware' => array_values( array_unique( [...$middleware, AiExceptionDirective::class] ) )] );
    }


    /**
     * Registers the MCP tools with client-safe AI errors.
     */
    protected function mcp() : void
    {
        CmsServer::register( self::TOOLS );

        CmsTools\Tool::failing( 'ai', fn( \Throwable $e, CmsTools\Tool $tool ) => $e instanceof PrismaException
            ? Response::structured( ['error' => AiError::report( $e, ['tool' => class_basename( $tool )] )] )
            : null
        );
    }


    /**
     * Records the usage of the AI providers.
     */
    protected function watch() : void
    {
        Watch::listen( [Generated::class] );
    }
}
