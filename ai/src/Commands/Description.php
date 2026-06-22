<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Tools;
use Aimeos\Prisma\Exceptions\PrismaException;


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

        Page::select( Page::SELECT_COLUMNS )
            ->where( 'status', '>', 0 )
            ->chunk( 50, function( $pages ) use ( $provider, $model, $config ) {

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
                        $text = Prisma::text()
                            ->using( $provider, $config )
                            ->model( $model )
                            ->withSystemPrompt( 'You are an SEO expert. Generate a concise meta description of max. 160 characters for the given page content. Return only the meta description text, nothing else.' )
                            ->withTools( [Tools::provider( 'web_search' ), Tools::provider( 'web_fetch' )] )
                            ->ensure( 'write' )
                            ->write( "Page title: {$page->title}\n\nPage content:\n{$text}", [], $config ) // @phpstan-ignore-line method.notFound
                            ->text();

                        $meta = $page->meta ?? (object) [];
                        $meta->{'meta-tags'} ??= (object) [
                            'id' => Utils::uid(),
                            'type' => 'meta-tags',
                            'group' => 'basic',
                            'data' => (object) [],
                        ];
                        $meta->{'meta-tags'}->data ??= (object) [];
                        $meta->{'meta-tags'}->data->description = $text;
                        $page->meta = $meta;
                        $page->save();
                    }
                    catch( PrismaException $e )
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
        $lang = current( config( 'cms.locales', ['en'] ) );
        $provider = config( 'cms.ai.describe.provider' );
        $model = config( 'cms.ai.describe.model' );
        $config = config( 'cms.ai.describe', [] );

        File::select(
                'id', 'tenant_id', 'path', 'mime', 'name', 'description', 'transcription',
                'latest_id', 'created_at', 'updated_at', 'deleted_at'
            )
            ->whereRaw( "CAST(description AS CHAR(2)) = '{}'" )
            ->where( function( $query ) {
                $query->where( 'mime', 'like', 'audio/%' )
                    ->orWhere( 'mime', 'like', 'video/%' )
                    ->orWhereIn( 'mime', ['image/jpeg', 'image/png', 'image/webp'] );
            } )
            ->chunk( 50, function( $files ) use ( $provider, $model, $config, $lang ) {

                foreach( $files as $file )
                {
                    $type = explode( '/', $file->mime, 2 )[0];

                    try
                    {
                        $doc = str_starts_with( (string) $file->path, 'http' )
                            ? \Aimeos\Prisma\Files\File::fromUrl( (string) $file->path, $file->mime )
                            : \Aimeos\Prisma\Files\File::fromStoragePath( (string) $file->path, config( 'cms.disk', 'public' ), $file->mime );

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
            } );
    }
}
