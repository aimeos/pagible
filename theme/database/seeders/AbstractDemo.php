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
use Illuminate\Support\Facades\Storage;


/**
 * Base class for theme-specific demo content providers.
 *
 * Subclasses implement pages() and own all theme-specific content. The theme
 * and tenant the content is created for are passed to the constructor.
 */
abstract class AbstractDemo
{
    /** @var array<string, string> File IDs keyed by Unsplash photo path and language */
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
     * Creates (once) a demo image from an Unsplash photo and returns its file ID.
     *
     * @param string $photo Unsplash photo path, e.g. "photo-1517336714731-489689fd1ca8"
     * @param string $name File name
     * @param string $desc Localized image description
     * @param string $lang File and description language
     * @return string File ID
     */
    protected function image( string $photo, string $name, string $desc, string $lang = 'en' ) : string
    {
        $key = $photo . ':' . $lang;

        if( !isset( $this->images[$key] ) )
        {
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w ) => $base . '?w=' . $w . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => $lang,
                'name' => $name,
                'path' => $url( 1500 ),
                'previews' => ['500' => $url( 500 ), '1000' => $url( 1000 )],
                'description' => [$lang => $desc],
            ];

            $this->images[$key] = $this->saveFile( $data );
        }

        return $this->images[$key];
    }


    /**
     * Persists and publishes a demo File with its initial version.
     *
     * @param array<string, mixed> $data File data
     * @param File|null $file Prepared File with a preallocated UUID
     * @param bool $published Whether the version is already marked as published
     * @return string File ID
     */
    protected function saveFile( array $data, ?File $file = null, bool $published = false ) : string
    {
        $file ??= new File();
        $file->forceFill( $data + ['editor' => 'demo'] )->save();

        $version = $file->versions()->forceCreate( [
            'lang' => $data['lang'] ?? null,
            'data' => $data,
            'published' => $published,
            'editor' => 'demo',
        ] );

        $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $file->publish( $version );

        return (string) $file->refresh()->id;
    }


    /**
     * Stores and publishes an SVG demo File.
     */
    protected function svgFile( string $svg, string $filename, string $name, string $desc,
        bool $published = false ) : string
    {
        $file = new File();
        $file->setUniqueIds();
        $path = $file->dir() . '/' . $filename;

        if( !Storage::disk( config( 'cms.disks.public.name', 'public' ) )->put( $path, $svg ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
        }

        return $this->saveFile( [
            'mime' => 'image/svg+xml',
            'lang' => 'en',
            'name' => $name,
            'path' => $path,
            'previews' => ['500' => $path],
            'description' => ['en' => $desc],
        ], $file, $published );
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

            $this->videoFile = $this->saveFile( [
                'mime' => 'video/mp4',
                'lang' => 'en',
                'name' => 'PagibleAI CMS Quick Tour',
                'path' => 'https://media.w3.org/2010/05/sintel/trailer.mp4',
                'previews' => ['500' => $poster],
                'description' => ['en' => 'See how PagibleAI CMS simplifies content creation with AI assistance'],
            ] );
        }

        return $this->videoFile;
    }
}
