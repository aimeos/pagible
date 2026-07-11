<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;


/**
 * Base class for theme-specific demo content providers.
 *
 * Subclasses implement pages() and own all theme-specific content. The theme
 * and tenant the content is created for are passed to the constructor.
 */
abstract class AbstractDemo
{
    private string $audioFile;
    /** @var array<string, string> File IDs keyed by Unsplash photo path */
    private array $images = [];
    private string $videoFile;
    protected string $tenant;
    protected string $theme;


    /**
     * Initializes the demo content provider.
     *
     * @param string $theme Theme name applied to the created pages
     * @param string $tenant Tenant ID the content is created for
     */
    public function __construct( string $theme = '', string $tenant = '' )
    {
        $this->theme = $theme;
        $this->tenant = $tenant;
    }


    /**
     * Seeds the demo content, replacing any existing content of the tenant.
     */
    public function seed() : void
    {
        Tenancy::$callback = fn() => $this->tenant;
        app()->forgetInstance( Tenancy::class );

        File::where( 'tenant_id', $this->tenant )->forceDelete();
        Version::where( 'tenant_id', $this->tenant )->forceDelete();
        Element::where( 'tenant_id', $this->tenant )->forceDelete();
        Page::where( 'tenant_id', $this->tenant )->forceDelete();

        Page::withoutSyncingToSearch( function() {
            Element::withoutSyncingToSearch( function() {
                File::withoutSyncingToSearch( function() {
                    $this->pages();
                } );
            } );
        } );

        Page::makeAllSearchable();
        Element::makeAllSearchable();
        File::makeAllSearchable();
    }


    /**
     * Builds the theme-specific demo pages, elements and files.
     */
    abstract protected function pages() : void;


    /**
     * Creates the shared demo audio file and returns its ID.
     *
     * @return string File ID
     */
    protected function audioFile() : string
    {
        if( !isset( $this->audioFile ) )
        {
            $file = File::forceCreate( [
                'mime' => 'audio/mpeg',
                'lang' => 'en',
                'name' => 'PagibleAI CMS Podcast Episode',
                'path' => 'https://download.samplelib.com/mp3/sample-12s.mp3',
                'previews' => [],
                'description' => ['en' => 'Learn about PagibleAI CMS features in this audio overview'],
                'editor' => 'demo',
            ] );

            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'mime' => 'audio/mpeg',
                    'lang' => 'en',
                    'name' => 'PagibleAI CMS Podcast Episode',
                    'path' => 'https://download.samplelib.com/mp3/sample-12s.mp3',
                    'previews' => [],
                    'description' => ['en' => 'Learn about PagibleAI CMS features in this audio overview'],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->audioFile = (string) $file->refresh()->id;
        }

        return $this->audioFile;
    }


    /**
     * Creates (once) a demo image from an Unsplash photo and returns its file ID.
     *
     * @param string $photo Unsplash photo path, e.g. "photo-1517336714731-489689fd1ca8"
     * @param string $name File name
     * @param string $desc English image description
     * @return string File ID
     */
    protected function image( string $photo, string $name, string $desc ) : string
    {
        if( !isset( $this->images[$photo] ) )
        {
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w ) => $base . '?w=' . $w . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( 1500 ),
                'previews' => ['500' => $url( 500 ), '1000' => $url( 1000 )],
                'description' => ['en' => $desc],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );

            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->images[$photo] = (string) $file->refresh()->id;
        }

        return $this->images[$photo];
    }


    /**
     * Creates the shared demo video file and returns its ID.
     *
     * @return string File ID
     */
    protected function videoFile() : string
    {
        if( !isset( $this->videoFile ) )
        {
            $poster = 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=500&q=80&fm=jpg&fit=crop';

            $file = File::forceCreate( [
                'mime' => 'video/mp4',
                'lang' => 'en',
                'name' => 'PagibleAI CMS Quick Tour',
                'path' => 'https://media.w3.org/2010/05/sintel/trailer.mp4',
                'previews' => ['500' => $poster],
                'description' => ['en' => 'See how PagibleAI CMS simplifies content creation with AI assistance'],
                'editor' => 'demo',
            ] );

            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'mime' => 'video/mp4',
                    'lang' => 'en',
                    'name' => 'PagibleAI CMS Quick Tour',
                    'path' => 'https://media.w3.org/2010/05/sintel/trailer.mp4',
                    'previews' => ['500' => $poster],
                    'description' => ['en' => 'See how PagibleAI CMS simplifies content creation with AI assistance'],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->videoFile = (string) $file->refresh()->id;
        }

        return $this->videoFile;
    }
}
