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
use Illuminate\Support\Str;


/**
 * Base class for theme-specific demo content providers.
 *
 * Subclasses implement pages() to build the page tree using the file(),
 * element() and page() helpers. The theme and tenant the content is created
 * for are passed to the constructor.
 */
abstract class AbstractDemo
{
    private string $audioFile;
    private string $element;
    private string $videoFile;
    /** @var array<string, string> File IDs keyed by Unsplash photo path */
    private array $images = [];
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
     * Creates the demo content provider for the given theme by naming convention.
     *
     * Resolves to "\Database\Seeders\<Studly>Demo" (e.g. "luxury" => LuxuryDemo)
     * when such a class exists, so themes ship a demo provider without a registry.
     * Falls back to the default demo content for the default and unknown themes.
     *
     * @param string $theme Theme name
     * @param string $tenant Tenant ID the content is created for
     * @return self Demo content provider for the theme
     */
    public static function create( string $theme, string $tenant = '' ) : self
    {
        $class = __NAMESPACE__ . '\\' . Str::studly( $theme ) . 'Demo';

        if( $theme !== '' && is_subclass_of( $class, self::class ) ) {
            return new $class( $theme, $tenant );
        }

        return new DefaultDemo( $theme, $tenant );
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
     * Creates the shared demo footer element and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Product', 'text' => "Explore our tools\n\n- [Features](/)\n- [Pricing](/)"],
                ['title' => 'Resources', 'text' => "Learn and grow\n\n- [Docs](/)\n- [Blog](/)"],
                ['title' => 'Company', 'text' => "Get in touch\n\n- [About](/)\n- [Contact](/)"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Shared footer',
                'data' => ['type' => 'cards', 'data' => ['cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Shared footer',
                    'data' => ['cards' => $cards],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    /**
     * Returns the ID of the primary shared demo image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->image(
            'photo-1517336714731-489689fd1ca8',
            'PagibleAI CMS Dashboard',
            'PagibleAI CMS delivers blazing-fast content management'
        );
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
     * Creates a demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page to append to
     * @param array<int, string> $fileIds Additional file IDs to attach
     * @param array<int, array<string, mixed>> $meta Meta data blocks
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $meta = $data['meta'] ?? $meta ?: [
            ['type' => 'meta-tags', 'data' => [
                'description' => $data['title'] ?? '',
                'keywords' => 'PagibleAI CMS, Laravel CMS, AI content management',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => $data['title'] ?? '',
                'description' => $data['title'] ?? '',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ]],
        ];

        $content[] = ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'PagibleAI CMS']];
        $content[] = ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'];

        $page = Page::forceCreate( $data + [
            'theme' => $this->theme,
            'editor' => 'demo',
            'meta' => $meta,
            'content' => $content,
        ] );
        $page->appendToNode( $parent )->save();

        $version = $page->versions()->forceCreate( [
            'lang' => $data['lang'] ?? 'en',
            'data' => array_diff_key( $data, ['content' => 1, 'meta' => 1] ) + [
                'domain' => '',
                'theme' => $this->theme,
            ],
            'aux' => ['meta' => $meta, 'content' => $content],
            'published' => true,
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
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
