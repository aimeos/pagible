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
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Storage;


/**
 * Base class for theme-specific demo content providers.
 *
 * Subclasses implement pages() and own all theme-specific content. The theme
 * and tenant the content is created for are passed to the constructor.
 */
abstract class AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [];

    /** @var array<string, array{string, string, array<string, string>|string}> Unsplash photo path, name and description(s) keyed by image key */
    protected const PHOTOS = [];

    /** @var array<string, string> File IDs of fixed-ratio images keyed by image key and size */
    private array $crops = [];
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
     * Returns an article content element.
     *
     * @param string $title Article title
     * @param string $text Article introduction
     * @param string $fileId Cover file ID
     * @return array<string, mixed> Article content element
     */
    protected function article( string $title, string $text, string $fileId ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => [$fileId], 'data' => [
            'title' => $title,
            'file' => ['id' => $fileId, 'type' => 'file'],
            'text' => $text,
        ]];
    }


    /**
     * Creates (once) a fixed-ratio demo image from the PHOTOS entry and returns its file ID.
     *
     * @param string $key Key of the PHOTOS entry
     * @param int $w Image width
     * @param int $h Image height
     * @param bool $published Whether the version is already marked as published
     * @param array<int, int> $widths Preview widths
     * @return string File ID
     */
    protected function cropped( string $key, int $w, int $h, bool $published = false, array $widths = [500, 1000] ) : string
    {
        $id = $key . ':' . $w . 'x' . $h;

        if( !isset( $this->crops[$id] ) )
        {
            [$photo, $name, $desc] = static::PHOTOS[$key];
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w, int $h ) => $base . '?w=' . $w . '&h=' . $h . '&q=80&fm=jpg&fit=crop';
            $previews = [];

            foreach( $widths as $width ) {
                $previews[(string) $width] = $url( $width, (int) round( $width * $h / $w ) );
            }

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( $w, $h ),
                'previews' => $previews,
                'description' => ['en' => $desc],
            ];

            $this->crops[$id] = $this->saveFile( $data, published: $published );
        }

        return $this->crops[$id];
    }


    /**
     * Returns the file IDs referenced by file objects in the given value.
     *
     * @param mixed $value Value to scan recursively
     * @return array<int, string> File IDs
     */
    protected function ids( mixed $value ) : array
    {
        $ids = [];

        if( is_array( $value ) )
        {
            if( ( $value['type'] ?? null ) === 'file' && is_string( $value['id'] ?? null )
                && !isset( $value['data'] ) && !isset( $value['group'] )
            ) {
                $ids[] = $value['id'];
            }

            foreach( $value as $item ) {
                $ids = array_merge( $ids, $this->ids( $item ) );
            }
        }

        return $ids;
    }


    /**
     * Creates (once) a demo image from an Unsplash photo and returns its file ID.
     *
     * @param string $photo Unsplash photo path, e.g. "photo-1517336714731-489689fd1ca8"
     * @param string $name File name
     * @param array<string, string>|string $desc Localized image description(s)
     * @param string $lang File and description language
     * @return string File ID
     */
    protected function image( string $photo, string $name, array|string $desc, string $lang = 'en' ) : string
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
                'description' => is_array( $desc ) ? $desc : [$lang => $desc],
            ];

            $this->images[$key] = $this->saveFile( $data );
        }

        return $this->images[$key];
    }


    /**
     * Creates (once) a demo image from the PHOTOS entry and returns its file ID.
     *
     * @param string $key Key of the PHOTOS entry
     * @return string File ID
     */
    protected function img( string $key ) : string
    {
        [$photo, $name, $desc] = static::PHOTOS[$key];
        return $this->image( $photo, $name, $desc );
    }


    /**
     * Returns the logo config entries for the given logo file.
     *
     * @param string $logoId Logo file ID
     * @return array<string, array<string, mixed>> Logo config entries keyed by type
     */
    protected function logos( string $logoId ) : array
    {
        return [
            'logo' => [
                'type' => 'logo',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
            'logo-alternative' => [
                'type' => 'logo-alternative',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
        ];
    }


    /**
     * Persists and publishes a shared demo element and returns its ID.
     *
     * @param string $type Element type
     * @param string $name Element name
     * @param array<string, mixed> $data Element data
     * @return string Element ID
     */
    protected function saveElement( string $type, string $name, array $data ) : string
    {
        $element = Element::forceCreate( [
            'lang' => 'en',
            'type' => $type,
            'name' => $name,
            'data' => ['type' => $type, 'data' => $data],
            'editor' => 'demo',
        ] );

        $version = $element->versions()->forceCreate( [
            'lang' => 'en',
            'data' => [
                'lang' => 'en',
                'type' => $type,
                'name' => $name,
                'data' => $data,
            ],
            'editor' => 'demo',
        ] );

        $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $element->publish( $version );

        return (string) $element->refresh()->id;
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
     * Persists and publishes a demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @param string $elementId Shared footer element ID
     * @param string $fileId Default social media file ID
     * @param array<int, array<string, mixed>> $footer Footer content elements
     * @param string $keywords Meta keywords
     * @param array<int, string> $fileIds Additional file IDs to attach
     * @param array<string, array<string, mixed>|object> $meta Meta entries keyed by type
     * @return Page Created page
     */
    protected function savePage( array $data, array $content, Page $parent, string $elementId, string $fileId,
        array $footer, string $keywords, array $fileIds = [], array $meta = [] ) : Page
    {
        $description = static::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';

        $meta = $data['meta'] ?? $meta ?: [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => $keywords,
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $content = array_merge( $content, $footer );

        $page = Page::forceCreate( $data + [
            'theme' => $this->theme,
            'editor' => 'demo',
            'meta' => $meta,
            'content' => $content,
        ] );
        $page->appendToNode( $parent )->save();

        $version = $page->versions()->forceCreate( [
            'lang' => $data['lang'] ?? 'en',
            'data' => array_diff_key( $data, ['content' => 1, 'meta' => 1, 'id' => 1] ) + [
                'domain' => '',
                'theme' => $this->theme,
            ],
            'aux' => ['meta' => $meta, 'content' => $content],
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $this->ids( $content ), $this->ids( $meta ) ) ) );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Persists and publishes the demo root page and returns it.
     *
     * @param string $title Page title
     * @param array<string, mixed> $config Config entries keyed by type
     * @param array<string, mixed> $meta Meta entries keyed by type
     * @param array<int, array<string, mixed>> $content Content elements
     * @param string $elementId Shared footer element ID
     * @param string $fileId Default file ID
     * @return Page Root page
     */
    protected function saveRoot( string $title, array $config, array $meta, array $content, string $elementId,
        string $fileId ) : Page
    {
        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => $title,
            'path' => '',
            'tag' => 'root',
            'theme' => $this->theme,
            'status' => 1,
            'cache' => 5,
            'editor' => 'demo',
            'config' => $config,
            'meta' => $meta,
            'content' => $content,
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => 'en',
            'data' => [
                'name' => 'Home',
                'title' => $title,
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => [
                'config' => $config,
                'meta' => $meta,
                'content' => $content,
            ],
            'editor' => 'demo',
        ] );

        $version->files()->attach( array_unique( array_merge( [$fileId], $this->ids( $config ), $this->ids( $content ), $this->ids( $meta ) ) ) );
        $version->elements()->attach( $elementId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
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
