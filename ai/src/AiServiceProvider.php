<?php

namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;

class AiServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->loadViewsFrom( $basedir . '/views', 'cms' );

        $this->publishes( [$basedir . '/config/cms/ai.php' => config_path( 'cms/ai.php' )], 'cms-ai-config' );

        if( class_exists( \Nuwave\Lighthouse\LighthouseServiceProvider::class ) )
        {
            $this->publishes( [$basedir . '/graphql/cms-ai.graphql' => base_path( 'graphql/cms-ai.graphql' )], 'cms-graphql-schema' );
        }

        \Aimeos\Cms\Permission::register( [
            'page:synthesize',
            'page:refine',
            'file:describe',
            'audio:transcribe',
            'image:imagine',
            'image:inpaint',
            'image:isolate',
            'image:repaint',
            'image:erase',
            'image:uncrop',
            'image:upscale',
            'text:translate',
            'text:write',
        ] );

        if( class_exists( \Aimeos\Cms\Mcp\CmsServer::class ) )
        {
            \Aimeos\Cms\Mcp\CmsServer::register( [
                \Aimeos\Cms\Tools\RefineContent::class,
                \Aimeos\Cms\Tools\TranslateContent::class,
            ] );
        }

        $this->console();
    }

    public function register()
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/ai.php', 'cms.ai' );
    }

    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\Description::class,
                \Aimeos\Cms\Commands\InstallAi::class,
            ] );
        }
    }
}
