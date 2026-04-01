<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Exceptions\PrismaException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Exceptions\PrismException;


class Description extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:description';

    /**
     * Command description
     */
    protected $description = 'Generates descriptions for pages and files if missing';


    /**
     * Execute command
     */
    public function handle() : void
    {
        $this->pages();
        $this->files();
    }


    /**
     * Generates meta descriptions for pages without one
     */
    protected function pages() : void
    {
        $provider = config( 'cms.ai.write.provider' );
        $model = config( 'cms.ai.write.model' );
        $config = config( 'cms.ai.write', [] );

        Page::with( 'latest' )->where( 'status', '>', 0 )->chunk( 100, function( $pages ) use ( $provider, $model, $config ) {

            foreach( $pages as $page )
            {
                /** @var Page $page */
                if( !empty( $page->meta->{'meta-tags'}->data->description ?? '' ) ) {
                    continue;
                }

                $text = (string) $page;

                if( empty( trim( $text ) ) ) {
                    continue;
                }

                try
                {
                    $response = Prism::text()
                        ->using( $provider, $model, $config )
                        ->withSystemPrompt( 'You are an SEO expert. Generate a concise meta description of max. 160 characters for the given page content. Return only the meta description text, nothing else.' )
                        ->withPrompt( "Page title: {$page->title}\n\nPage content:\n{$text}" )
                        ->withClientOptions( ['timeout' => 30, 'connect_timeout' => 10] )
                        ->asText();

                    $meta = json_decode( (string) json_encode( $page->meta ), true );
                    $meta['meta-tags'] = $meta['meta-tags'] ?? [
                        'id' => Utils::uid(),
                        'type' => 'meta-tags',
                        'group' => 'basic',
                        'data' => [],
                    ];
                    $meta['meta-tags']['data']['description'] = $response->text;
                    $page->meta = $meta;
                    $page->save();
                }
                catch( PrismException $e )
                {
                    $this->error( $page->title . ': ' . $e->getMessage() );
                }

                unset( $page );
            }
        } );
    }


    /**
     * Generates descriptions for files without one
     */
    protected function files() : void
    {
        $lang = current( config( 'cms.config.locales', ['en'] ) );
        $provider = config( 'cms.ai.describe.provider' );
        $model = config( 'cms.ai.describe.model' );
        $config = config( 'cms.ai.describe', [] );

        File::with( 'latest' )->whereRaw( "CAST(description AS CHAR(2)) = '{}'" )
            ->where( function( $query ) {
                $query->where( 'mime', 'like', 'audio/%' )
                    ->orWhere( 'mime', 'like', 'video/%' )
                    ->orWhereIn( 'mime', ['image/jpeg', 'image/png', 'image/webp'] );
            } )
            ->chunk( 100, function( $files ) use ( $provider, $model, $config, $lang ) {

                foreach( $files as $file )
                {
                    $type = current( explode( '/', $file->mime ) );
                    $class = '\\Aimeos\\Prisma\\Files\\' . ucfirst( $type );

                    try
                    {
                        if( !str_starts_with( (string) $file->path, 'http' ) ) {
                            $doc = $class::fromStoragePath( $file->path, config( 'cms.disk', 'public' ), $file->mime );
                        } else {
                            $doc = $class::fromUrl( $file->path, $file->mime );
                        }

                        $text = Prisma::type( $type )
                            ->using( $provider, $config )
                            ->model( $model )
                            ->ensure( 'describe' )
                            ->describe( $doc, $lang, $config ) // @phpstan-ignore-line method.notFound
                            ->text();

                        $file->description = (object) [$lang => $text];
                        $file->save();
                    }
                    catch( PrismaException $e )
                    {
                        $this->error( $file->name . ': ' . $e->getMessage() );
                    }

                    unset( $file, $doc );
                }
            }
        );
    }
}
