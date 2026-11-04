<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\WordPress;
use Aimeos\Cms\Import\WpArticle;
use Aimeos\Cms\Import\WpBlog;
use Aimeos\Cms\Import\WpMedia;
use Aimeos\Cms\Import\WpReader;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\DB;


/**
 * @phpstan-import-type WpPost from WpReader
 */
class WpImport extends BaseImport
{
    /** Columns of the pages of unchanged posts which are only moved below the blog page by keepPage() */
    protected const KEEP_COLUMNS = ['id', 'variant_id', 'parent_id', 'tenant_id', 'lang', 'domain', 'path', 'editor', 'to', 'deleted_at'];

    /**
     * Command name
     */
    protected $signature = 'cms:wp-import
        {--connection=wordpress : Database connection name for the WordPress database}
        {--domain= : Domain name for the imported pages}
        {--lang=en : Language code for the imported pages}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--blog-path=blog : Path of the parent blog page}
        {--blog-name=Blog : Name of the parent blog page}
        {--type=blog : Page type for imported pages (blog, docs, page, ...)}
        {--theme= : Pagible theme name applied to imported pages}
        {--media-url=* : Media base URL; repeatable, first value replaces wp-content/uploads paths}
        {--editor=wp-import : Editor name for imported records}
        {--since= : Only import the posts changed since the date (e.g. 2026-01-31), the pages of the others are kept as imported before}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports WordPress posts into Pagible CMS blog article pages';

    protected bool $keepPages = false;
    protected string $type;
    protected string $theme;
    /** @var array<string, PageView|null> Existing pages of the posts in the current chunk by path */
    protected array $postPages = [];
    protected ?WordPress $wordpress = null;
    protected WpMedia $media;
    protected WpReader $reader;


    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->type = $this->stringOption( 'type' );
        $this->theme = $this->stringOption( 'theme' );

        if( !$this->init( 'WordPress', 'WP' ) || !$this->sinceOption() ) {
            return;
        }

        $this->reader = new WpReader( DB::connection( $this->connection ) );
        $postCount = $this->reader->count();

        if( $postCount === 0 ) {
            $this->warn( 'No published WordPress posts found.' );
            return;
        }

        $this->info( "Found {$postCount} published WordPress posts." );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun();
            return;
        }

        $this->media = new WpMedia( (array) $this->option( 'media-url' ), $this->createMediaFile( ... ),
            $this->reader->basenames(), $this->reader->attachments( ... ) );
        $blogPage = $this->getBlogPage();

        try {
            $this->importPosts( $blogPage, $postCount );
        } finally {
            $this->forgetDownloads();
        }
    }


    /**
     * Builds the page data array for an article.
     *
     * @param int $status 1 for enabled pages, 0 for disabled ones
     * @return array<string, mixed>
     */
    protected function buildPageData( string $title, string $slug, string $tag = 'article', int $status = 1 ): array
    {
        return ['type' => $this->type, 'theme' => $this->theme] + $this->pageData( $title, $slug, $status, $tag );
    }


    /**
     * Adds the file of a previous import and remembers its MIME type, so preview() doesn't need to fetch it.
     *
     * @param string $path Source URL or path of the file
     */
    protected function reuseFile( string $path, File $file ): void
    {
        parent::reuseFile( $path, $file );
        $this->media->remember( (string) $file->id, (string) $file->mime );
    }


    /**
     * Creates a File record with a published version or returns the one created before by this import.
     *
     * Unlike createFile(), files are not looked up by their path because downloaded files get a new one.
     * The path must be rewritten by the WpMedia object before.
     *
     * @param bool $download TRUE to download the file and store it locally
     */
    protected function createMediaFile( string $mime, string $name, string $path, bool $download = false ): ?string
    {
        if( !Utils::isValidUrl( $path, strict: false ) ) {
            return null;
        }

        if( $id = $this->knownFile( $path ) ) {
            return $id;
        }

        // files used by several posts are only downloaded once if the download fails
        // the MIME type of downloaded files is detected from their content
        $file = $this->downloadOnce( $path, $download, fn() => Files::create( $mime, $name, $path, $this->editor, $download, $download ? $this->downloaded( $path ) : null ) );
        $id = $file->id ?? '';

        $this->createdFiles->put( $path, $id );
        $this->media->remember( $id, $file->mime ?? '' );

        return $id;
    }


    /**
     * Returns the page which uses the URL path including trashed ones.
     *
     * The pages of the posts in the current chunk are fetched at once before and used until the post is saved.
     *
     * @param string $path URL path with or without leading slash
     */
    protected function pageByPath( string $path ): ?PageView
    {
        return array_key_exists( $path, $this->postPages ) ? $this->postPages[$path] : parent::pageByPath( $path );
    }


    /**
     * Publishes the selected theme for an existing blog page.
     *
     * Blog pages which weren't created by the import or were changed since get no new version.
     */
    protected function updateBlogTheme( PageView $page ): void
    {
        if( $this->theme === '' || $page->theme === $this->theme ) {
            return;
        }

        if( !$this->isImported( $page ) )
        {
            $this->warn( "Theme of the blog page /{$page->path} isn't changed because it wasn't imported or was changed since" );
            return;
        }

        $data = $page->toArray();
        $data['theme'] = $this->theme;
        $aux = ['content' => (array) $page->content, 'config' => $page->config, 'meta' => $page->meta];

        Pages::publish( $page, $data, $aux, $page->files->pluck( 'id' )->all(), $page->elements->pluck( 'id' )->all(),
            $page->lang ?: $this->lang, $this->editor );
    }


    /**
     * Finds or creates the parent blog page.
     */
    protected function getBlogPage(): PageView
    {
        $blogPath = trim( $this->stringOption( 'blog-path' ), '/' );
        $blogName = $this->stringOption( 'blog-name' );

        // the blog page is used as parent of the posts even if it wasn't imported
        if( $page = $this->pageByPath( $blogPath ) )
        {
            $page = $this->restore( $page );
            $this->updateBlogTheme( $page );
            $this->info( "Using existing blog page: {$page->name} (/{$blogPath})" );
        }
        else
        {
            $content = [['id' => Utils::uid(), 'type' => $this->type, 'group' => 'main', 'data' => ['title' => $blogName]]];
            $page = $this->createPage( $this->buildPageData( $blogName, $blogPath, '' ), ['elements' => $content, 'fileIds' => []], Pages::root( $this->domain ) );
            $this->info( "Created blog page: {$blogName} (/{$blogPath})" );
        }

        // posts must not overwrite the blog page
        $this->usedPages[$page->id ?? ''] = true;
        return $page;
    }


    /**
     * Imports a single WordPress post as a Pagible blog article page.
     *
     * Pages of previous imports get no new version if their data and content didn't change.
     *
     * @param WpPost $post
     * @return array{page: PageView, status: Status} Imported page and its status
     * @throws \RuntimeException If the URL is used by a page which wasn't imported, was changed since or was saved by this import
     */
    protected function importPost( object $post, PageView $blogPage ): array
    {
        $slug = $this->slug( $post );

        // posts not changed since the given date aren't rendered again
        if( $kept = $this->keepPage( $post->ID, $slug, $blogPage ) ) {
            return $kept;
        }

        $title = html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' );
        $intro = $post->post_excerpt ?: WpArticle::intro( $post->post_content );

        $content = $this->wordpress()->elements( $post->post_content );
        $coverFileId = $this->media->featured( $post->ID );
        $previewFileId = $coverFileId ?? $this->media->preview( $content['fileIds'] );
        $footer = WpBlog::footer( $blogPage );

        $elements = [WpArticle::element( $intro, $coverFileId, $previewFileId ), ...$content['elements'], ...$footer['elements']];
        $fileIds = $coverFileId ? [...$content['fileIds'], $coverFileId] : $content['fileIds'];
        $date = $this->date( $post->post_date_gmt, $post->post_date );

        // password protected posts are only visible to visitors who know the password, so their pages are disabled
        if( $post->post_password !== '' ) {
            $this->warn( "  Disabled [{$post->ID}] {$post->post_title} because it is password protected" );
        }

        $saved = $this->savePage( $slug, $this->buildPageData( $title, $slug, 'article', $post->post_password !== '' ? 0 : 1 ),
            ['elements' => $elements, 'fileIds' => $fileIds, 'elementIds' => $footer['elementIds']], $blogPage, $date );
        $page = $saved['page'];

        if( $date && $page->created_at?->format( 'Y-m-d H:i:s' ) !== $date ) {
            $page->variant->forceFill( ['created_at' => $date] )->save();
        }

        return $saved;
    }


    /**
     * Imports all posts under the given blog page.
     */
    protected function importPosts( PageView $blogPage, int $total ): void
    {
        // the texts of posts not changed since the --since date aren't fetched because they aren't rendered again
        $this->reader->chunk( function( $rows ) use ( $blogPage ) {
            $posts = [];

            foreach( $rows as $idx => $row )
            {
                try {
                    $posts[$idx] = $this->reader->post( $row );
                } catch( \Exception $e ) {
                    $this->failedImport( $this->rowLabel( $row ), $e );
                }
            }

            $this->changed = $this->since !== null ? array_column( array_map( fn( $post ) => [$post->ID, $this->modified( $post )], $posts ), 1, 0 ) : [];
            // the pages of all posts in the chunk are fetched at once, the pages of unchanged posts are only moved
            $unchanged = array_filter( $posts, fn( $post ) => $this->unchangedSince( $post->ID ) );
            $this->postPages = $this->postPages( array_diff_key( $posts, $unchanged ) )
                + $this->postPages( $unchanged, static::KEEP_COLUMNS );

            // unchanged posts without page of a previous import are imported again, so their texts are fetched now
            if( $missing = array_filter( $posts, fn( $post ) => $this->unchangedSince( $post->ID ) && !isset( $this->postPages[$this->slug( $post )] ) ) )
            {
                $this->reader->texts( $missingRows = array_intersect_key( $rows->all(), $missing ) );

                foreach( $missingRows as $idx => $row ) {
                    $posts[$idx] = $this->reader->post( $row );
                }
            }

            $render = array_filter( $posts, fn( $post ) => !$this->unchangedSince( $post->ID ) ) + array_intersect_key( $posts, $missing );

            // the featured images and the used images of all posts in the chunk are fetched at once
            $this->media->prefetch( $this->reader->thumbnails( array_column( $render, 'ID' ) ), array_column( $render, 'post_content', 'ID' ) );
            $this->prefetchFiles( $this->media->paths() );
            $postIds = array_column( $posts, 'ID' );

            foreach( $posts as $post )
            {
                // the files are downloaded before the posts are imported because the downloads would keep the batch transaction open
                // the files of the next posts are downloaded together when the post uses a file which isn't downloaded yet
                if( array_filter( $needed = $this->media->downloads( [$post->ID] ), fn( $url ) => $this->downloadable( $url ) ) ) {
                    $this->download( $this->media->downloads( $postIds ), $needed );
                }

                array_shift( $postIds );
                $result = $this->transaction( "[{$post->ID}] {$post->post_title}", fn() => $this->importPost( $post, $blogPage ) );
                // the fetched page is outdated after the post was saved or its import failed
                unset( $this->postPages[$this->slug( $post )] );

                if( $result ) {
                    $this->imported( $post->ID, $result['page'], $post->post_title, $this->slug( $post ), $result['status'] );
                }
            }
        }, $this->since !== null ? fn( object $row ) => $this->modifiedAt( get_object_vars( $row )['post_modified_gmt'] ?? null ) >= $this->since : null );

        $this->postPages = [];
        $this->totals( $total, 'posts' );
    }


    /**
     * Returns the pages which use the URL paths of the posts including trashed ones.
     *
     * @param array<int|string, WpPost> $posts WordPress posts
     * @param array<int, string> $columns Columns of the pages which are fetched
     * @return array<string, PageView|null> Pages by URL path without leading slash, NULL for posts without page
     */
    protected function postPages( array $posts, array $columns = ['*'] ): array
    {
        if( $posts === [] ) {
            return [];
        }

        $slugs = array_values( array_map( fn( $post ) => $this->slug( $post ), $posts ) );
        // pages of previous imports may use URL paths with leading slash
        $pages = Pages::findAll( ['domain' => $this->domain], 'path', array_merge( $slugs, array_map( fn( $slug ) => '/' . $slug, $slugs ) ), $columns );
        $result = [];

        foreach( $slugs as $slug ) {
            $result[$slug] = $pages[$slug] ?? $pages['/' . $slug] ?? null;
        }

        return $result;
    }


    /**
     * Returns the time when the post was modified last.
     *
     * @param WpPost $post
     * @return int Timestamp, posts without modification time are always changed
     */
    protected function modified( object $post ): int
    {
        return $this->modifiedAt( $post->post_modified_gmt );
    }


    /**
     * Returns the timestamp of the modification date of a post row.
     *
     * @param mixed $gmt Modification date in GMT
     * @return int Timestamp, posts without modification time are always changed
     */
    protected function modifiedAt( mixed $gmt ): int
    {
        $date = $this->date( is_string( $gmt ) ? $gmt : null );
        return $date && ( $time = strtotime( $date . ' UTC' ) ) !== false ? $time : PHP_INT_MAX;
    }


    /**
     * Returns the URL path of the post.
     *
     * @param WpPost $post
     */
    protected function slug( object $post ): string
    {
        return $post->post_name ?: Utils::slugify( $post->post_title );
    }


    /**
     * Returns the ID and title of the WordPress post row for messages, also if the row is invalid.
     */
    protected function rowLabel( object $row ): string
    {
        $data = get_object_vars( $row );
        $id = $data['ID'] ?? '?';
        $title = $data['post_title'] ?? '';
        $id = is_int( $id ) || is_string( $id ) ? $id : '?';
        $title = is_string( $title ) ? $title : '';

        return "[{$id}] {$title}";
    }


    /**
     * Prints a dry run summary.
     */
    protected function printDryRun(): void
    {
        // the texts of the posts aren't fetched because they aren't shown
        $this->reader->chunk( function( $rows ) {
            foreach( $rows as $row )
            {
                try {
                    $post = $this->reader->post( $row );
                    $this->line( "  [{$post->ID}] {$post->post_title} ({$post->post_name})" );
                } catch( \Exception $e ) {
                    // invalid posts are reported like in the import
                    $this->warn( '  Skipped ' . $this->rowLabel( $row ) . ': ' . $e->getMessage() );
                }
            }
        }, fn() => false );
        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Returns the converter for the content of the WordPress posts.
     */
    protected function wordpress(): WordPress
    {
        return $this->wordpress ??= new WordPress(
            fn( string $tag ) => $this->media->image( $tag ),
            fn( string $url ) => $this->media->url( $url )
        );
    }
}
