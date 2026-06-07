<?php

namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;

class AiServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->loadViewsFrom( $basedir . '/views', 'cms' );

        $this->publishes( [$basedir . '/config/cms/ai.php' => config_path( 'cms/ai.php' )], 'cms-config' );
        $this->publishes( [$basedir . '/graphql/cms-ai.graphql' => base_path( 'graphql/cms-ai.graphql' )], 'cms-graphql' );

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
                \Aimeos\Cms\Tools\DescribeFile::class,
                \Aimeos\Cms\Tools\TranscribeAudio::class,
                \Aimeos\Cms\Tools\GenerateImage::class,
                \Aimeos\Cms\Tools\RepaintImage::class,
                \Aimeos\Cms\Tools\IsolateImage::class,
                \Aimeos\Cms\Tools\UpscaleImage::class,
                \Aimeos\Cms\Tools\UncropImage::class,
                \Aimeos\Cms\Tools\EraseImage::class,
                \Aimeos\Cms\Tools\InpaintImage::class,
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
