<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Access as CmsAccess;
use Aimeos\Cms\Commands;
use Aimeos\Cms\Concerns\PendingVersions;
use Aimeos\Cms\Concerns\Vanished;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Tally;
use Aimeos\Cms\Import\WordPress;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\DB;


/**
 * @phpstan-import-type WpPost from WordPress\Reader
 */
class WpImport extends Commands
{
    use PendingVersions;
    use Vanished;

    /** Columns of the pages of unchanged posts which are only moved below the blog page by keepPage() */
    protected const KEEP_COLUMNS = ['id', 'variant_id', 'parent_id', 'tenant_id', 'lang', 'domain', 'path', 'editor', 'to', 'deleted_at'];

    /**
     * Command name
     */
    protected $signature = 'cms:wp-import
        {--connection=wordpress : Database connection name for the WordPress database}
        {--prefix=wp_ : Prefix of the WordPress tables ($table_prefix in wp-config.php, e.g. wp_2_ for multisite blogs)}
        {--domain= : Domain name for the imported pages}
        {--lang= : Language code for the imported pages, the language of the WordPress site by default}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--blog-path=blog : Path of the parent blog page}
        {--blog-name=Blog : Name of the parent blog page}
        {--type=blog : Page type for imported pages (blog, docs, page, ...)}
        {--theme= : Pagible theme name applied to imported pages}
        {--media-url=* : Media base URL; repeatable, first value replaces wp-content/uploads paths}
        {--editor=wp-import : Editor name for imported records}
        {--role=* : WordPress role imported as page access value of private posts with another name (e.g. --role=editor:staff)}
        {--index : Import the pages as indexable even if search engines are discouraged from indexing the WordPress site}
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
    /** @var array<int|string, string> Contents of the posts in the current chunk with the reusable blocks replaced by post ID */
    protected array $contents = [];
    /** @var array<int|string, array<string, string>> Footnotes of the posts in the current chunk by post ID */
    protected array $footnotes = [];
    /** @var array<int|string, array<string, string>> Values of the SEO plugins of the posts in the current chunk by post ID */
    protected array $seo = [];
    /** @var array<int|string, array<int, string>> Category and tag names of the posts in the current chunk by post ID */
    protected array $terms = [];
    /** @var array<int|string, array{categories?: array<int, string>, tags?: array<int, string>, author?: string, modified?: string}> Values of the posts in the current chunk for the variables of the SEO plugins by post ID */
    protected array $variables = [];
    /** TRUE if search engines are discouraged from indexing the WordPress site */
    protected bool $noindex = false;
    protected ?WordPress\Meta $meta = null;
    protected ?WordPress\Html $wordpress = null;
    protected ?WordPress\Links $links = null;
    /** @var array<int|string, string>|null URL paths of the pages by post ID of all imported posts */
    protected ?array $paths = null;
    /** @var array<int|string, array<int, string>>|null Former slugs by post ID */
    protected ?array $oldSlugs = null;
    /** @var array<int|string, object{post_name: string, post_title: string, post_date: string}>|null Slugs, titles and dates of all imported posts by post ID */
    protected ?array $names = null;
    /** @var array<string, int|string>|null Post IDs by URL path of the redirects to the pages of the posts */
    protected ?array $redirects = null;
    /** ID of the blog page the posts are imported below */
    protected ?string $blogId = null;
    /** @var array<string, string> Pagible access values by WordPress role from the --role option */
    protected array $roles = [];
    /** @var array<int, string>|string|null Access values of the pages of private posts or why they can't be restricted */
    protected array|string|null $privateAccess = null;
    /** @var array<string, array<int, string>>|null Sorted access values of the restricted pages by page ID */
    protected ?array $accessValues = null;
    protected WordPress\Media $media;
    protected WordPress\Reader $reader;


    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->type = $this->stringOption( 'type' );
        $this->theme = $this->stringOption( 'theme' );

        if( !$this->init( 'WordPress', 'WP' ) || !$this->sinceOption() || ( $roles = $this->roleOption() ) === null ) {
            return;
        }

        $this->roles = $roles;
        $this->links = $this->paths = $this->oldSlugs = $this->blogId = $this->pendingVariants = null;
        $this->privateAccess = $this->accessValues = $this->names = $this->redirects = $this->meta = null;

        try {
            $this->reader = new WordPress\Reader( DB::connection( $this->connection ), $this->stringOption( 'prefix' ) );
        } catch( \InvalidArgumentException $e ) {
            $this->error( $e->getMessage() );
            return;
        }

        // the language of the site is used unless the pages are imported in another language
        if( $this->lang === '' )
        {
            $this->lang = $this->reader->language() ?? 'en';
            $this->info( "Importing the pages in the language \"{$this->lang}\" of the WordPress site (\"WPLANG\" option), use --lang to change it." );
        }

        $postCount = $this->reader->count();
        $this->noindex = !$this->option( 'index' ) && $this->reader->hidden();

        // staging copies of WordPress sites often discourage search engines, so it's easily overlooked
        if( $this->noindex ) {
            $this->warn( 'Search engines are discouraged from indexing the WordPress site ("blog_public" option), so all pages are imported with "noindex, nofollow". Use --index to import them as indexable.' );
        }

        if( $postCount === 0 ) {
            $this->warn( 'No published, scheduled or private WordPress posts found.' );
            return;
        }

        $this->info( "Found {$postCount} published, scheduled or private WordPress posts." );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun();
            return;
        }

        // files below the uploads URL of the WordPress options are downloaded too, e.g. if "upload_url_path" is set
        $this->media = ( new WordPress\Media( (array) $this->option( 'media-url' ), $this->createMediaFile( ... ),
            $this->reader->basenames(), $this->reader->attachments( ... ) ) )->uploads( $this->reader->uploads() );
        $blogPage = $this->getBlogPage();
        $this->blogId = (string) $blogPage->id;

        try {
            $this->importPosts( $blogPage, $postCount );
            $this->importRedirects( $blogPage );
            $this->disableVanished();
        } finally {
            $this->forgetDownloads();
        }
    }


    /**
     * Builds the page data array for an article.
     *
     * @param int $status Page status, one of the Pages::STATUS_* constants
     * @return array<string, mixed>
     */
    protected function buildPageData( string $title, string $slug, string $tag = 'article', int $status = Pages::STATUS_PUBLISHED ): array
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
     * The path must be rewritten by the WordPress\Media object before.
     *
     * @param bool $download TRUE to download the file and store it locally
     * @return string|null File ID or NULL if the path isn't a valid URL
     */
    protected function createMediaFile( string $mime, string $name, string $path, bool $download = false ): ?string
    {
        return Utils::isValidUrl( $path, strict: false ) ? $this->importFile( $mime, $name, $path, $download ) : null;
    }


    /**
     * Remembers the MIME type of the file created by importFile(), so preview() doesn't need to fetch it.
     *
     * @param string $path Source URL or path of the file
     * @param File $file Created file
     */
    #[\Override]
    protected function importedFile( string $path, File $file ): void
    {
        $this->media->remember( (string) $file->id, (string) $file->mime );
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

        $title = $this->title( $post );
        // links to other posts point to their imported pages
        $html = $this->links()->rewrite( $this->contents[$post->ID] ?? $post->post_content );
        $footnotes = array_map( fn( $text ) => $this->links()->rewrite( $text ), $this->footnotes[$post->ID] ?? [] );
        // manual excerpts can contain HTML tags like links
        $intro = WordPress\Article::excerpt( $post->post_excerpt ) ?: WordPress\Article::intro( $html );

        $content = $this->wordpress()->elements( $html, $footnotes, $post->ID );
        $coverFileId = $this->media->featured( $post->ID );
        $previewFileId = $coverFileId ?? $this->media->preview( $content['fileIds'] );
        $footer = WordPress\Blog::footer( $blogPage );

        $elements = [WordPress\Article::element( $intro, $coverFileId, $previewFileId ), ...$content['elements'], ...$footer['elements']];
        $fileIds = $coverFileId ? [...$content['fileIds'], $coverFileId] : $content['fileIds'];
        $date = $this->date( $post->post_date_gmt, $post->post_date );

        $status = $this->status( $post );
        $seo = $this->seo[$post->ID] ?? [];
        // the excerpts of password protected posts are hidden by the SEO plugins
        $hidden = $post->post_password !== '';
        $vars = ['id' => $post->ID, 'date' => $post->post_date, 'excerpt' => $hidden ? '' : WordPress\Article::excerpt( $post->post_excerpt ),
            'content' => $hidden ? '' : $post->post_content] + ( $this->variables[$post->ID] ?? [] );
        // the SEO title of the plugins is the title of the document, the post title stays the name of the page
        $pageData = ['title' => $this->meta()->title( $seo, $title, $intro, $vars ) ?? $title] + $this->buildPageData( $title, $slug, 'article', $status );
        $meta = $this->meta()->build( $seo, $this->terms[$post->ID] ?? [], $intro, $title, $coverFileId, $vars );
        $content = ['elements' => $elements, 'fileIds' => $fileIds, 'elementIds' => $footer['elementIds'], 'meta' => $meta];

        // the redirect page of the former slug of another post is replaced by the post
        if( ( $this->pageByPath( $slug )->to ?? '' ) !== '' ) {
            $pageData['to'] = '';
        }

        $saved = $this->savePage( $slug, $pageData, $content, $blogPage, $date );
        $page = $saved['page'];
        $this->setAccess( $page, $post );

        if( $date && $page->created_at?->format( 'Y-m-d H:i:s' ) !== $date ) {
            $page->variant->forceFill( ['created_at' => $date] )->save();
        }

        // WordPress publishes scheduled posts at their date, pending versions of posts published since are removed
        $at = $post->post_status === 'future' && $post->post_password === '' && $post->post_date_gmt !== null
            ? $this->modifiedAt( $post->post_date_gmt ) : null;
        $this->saveVersions( $page, $at && $at !== PHP_INT_MAX ? [[array_replace( $pageData, ['status' => Pages::STATUS_PUBLISHED] ), $content, $at]] : [] );

        return $saved;
    }


    /**
     * Returns the status of the page of the post and reports posts whose pages are disabled.
     *
     * Password protected posts are only visible to visitors who know the password, so their pages are disabled.
     * Private posts are only visible to users who can read private posts, so their pages are restricted to these
     * roles or disabled if access restrictions aren't available. Scheduled posts are disabled until their publication date.
     *
     * @param WpPost $post
     * @return int Page status, one of the Pages::STATUS_* constants
     */
    protected function status( object $post ): int
    {
        $reason = match( true ) {
            $post->post_password !== '' => 'it is password protected',
            $post->post_status === 'private' && is_string( $access = $this->privateAccess() ) => "it is private and {$access}",
            $post->post_status === 'future' => 'it is scheduled for ' . ( $post->post_date_gmt ? $post->post_date_gmt . ' UTC' : $post->post_date ),
            default => null,
        };

        if( $reason === null ) {
            return Pages::STATUS_PUBLISHED;
        }

        $this->warn( "  Disabled [{$post->ID}] {$post->post_title} because {$reason}" );
        return Pages::STATUS_DISABLED;
    }


    /**
     * Returns the access values of the pages of private posts or why the pages can't be restricted.
     *
     * WordPress shows private posts to the users of the roles with the "read_private_posts" capability, which are
     * mapped to Pagible access values by the --role option. Roles which don't exist in Pagible are skipped.
     *
     * @return array<int, string>|string Sorted access values or the reason why the pages are disabled instead
     */
    protected function privateAccess(): array|string
    {
        if( $this->privateAccess !== null ) {
            return $this->privateAccess;
        }

        if( !Permission::has( 'access:view' ) ) {
            return $this->privateAccess = "frontend access restrictions aren't set up in Pagible, see Access::using()";
        }

        $roles = $this->reader->privateRoles();
        $values = app( CmsAccess::class )->known( array_unique( array_map( fn( $role ) => $this->roles[$role] ?? $role, $roles ) ) );

        if( !$values ) {
            return $this->privateAccess = 'the roles "' . implode( '", "', $roles ) . '" don\'t exist in Pagible, use --role=<wprole>:<value>';
        }

        return $this->privateAccess = CmsAccess::normalize( $values );
    }


    /**
     * Restricts the page of a private post to the roles which can read private posts.
     *
     * Pages of posts which aren't private any more become public again if they are restricted to the same roles,
     * other access restrictions set in Pagible are kept. The access values of all restricted pages are loaded once,
     * so unchanged access doesn't cause any query.
     *
     * @param WpPost $post
     */
    protected function setAccess( PageView $page, object $post ): void
    {
        // pages can only be restricted to the roles if access restrictions are set up
        if( !is_array( $private = $this->privateAccess() ) ) {
            return;
        }

        $access = $post->post_status === 'private' ? $private : null;
        $this->accessValues ??= PageAccess::get( ['page_id', 'value'] )->groupBy( 'page_id' )
            ->map( fn( $rules ) => (array) PageAccess::values( $rules ) )->all();

        $id = (string) $page->id;
        $current = $this->accessValues[$id] ?? null;

        if( $current === $access || $access === null && $current !== $private ) {
            return;
        }

        Permission::system( fn() => PageAccess::set( [$id], $access ) );

        if( $access !== null ) {
            $this->accessValues[$id] = $access;
            $this->warn( "  Restricted [{$post->ID}] {$post->post_title} to " . implode( ', ', $access ) . ' because it is private' );
        } else {
            unset( $this->accessValues[$id] );
        }
    }


    /**
     * Returns the WordPress roles mapped to the Pagible access values from the --role option.
     *
     * @return array<string, string>|null Pagible access values by WordPress role or NULL if a value is invalid
     */
    protected function roleOption(): ?array
    {
        $roles = [];

        foreach( (array) $this->option( 'role' ) as $value )
        {
            if( !is_string( $value ) || !preg_match( '/^([^:]+):(.+)$/', $value, $m ) ) {
                $this->error( 'Invalid WordPress role: ' . ( is_string( $value ) ? $value : '' ) );
                return null;
            }

            $roles[$m[1]] = trim( $m[2] );
        }

        return $roles;
    }


    /**
     * Imports all posts under the given blog page.
     */
    protected function importPosts( PageView $blogPage, int $total ): void
    {
        // posts using reusable blocks changed since are changed too because WordPress shows the current reusable blocks
        $patternPosts = $this->since !== null ? $this->reader->patternPosts( $this->since ) : [];

        // the texts of posts not changed since the --since date aren't fetched because they aren't rendered again
        $this->reader->chunk( function( $rows ) use ( $blogPage, $patternPosts ) {
            $posts = [];

            foreach( $rows as $idx => $row )
            {
                try {
                    $posts[$idx] = $this->reader->post( $row );
                } catch( \Exception $e ) {
                    $this->failedImport( $this->rowLabel( $row ), $e );
                }
            }

            $this->changed = $this->since !== null ? array_column( array_map( fn( $post ) => [$post->ID, isset( $patternPosts[$post->ID] ) ? PHP_INT_MAX : $this->modified( $post )], $posts ), 1, 0 ) : [];
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

            // the reusable blocks and the images of galleries without IDs are part of the content, so their images are fetched together with the other ones
            $this->contents = $this->reader->playlists( $this->reader->galleries( $this->reader->bindings( $this->reader->reusable( array_column( $render, 'post_content', 'ID' ) ) ) ) );
            $this->footnotes = $this->reader->footnotes( $this->contents );
            $this->seo = $this->reader->seo( array_column( $render, 'ID' ) );
            $this->terms = $this->reader->terms( array_column( $render, 'ID' ) );
            // the values for the variables of the SEO plugins are only fetched if they are used
            $this->variables = $this->meta()->variables( $this->seo ) ? $this->reader->variables( array_column( $render, 'ID' ) ) : [];

            // the featured images and the used images of all posts in the chunk are fetched at once
            $this->media->prefetch( $this->reader->thumbnails( array_column( $render, 'ID' ) ), $this->contents );
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
        }, $this->since !== null ? fn( object $row ) => isset( $patternPosts[get_object_vars( $row )['ID'] ?? ''] )
            || $this->modifiedAt( get_object_vars( $row )['post_modified_gmt'] ?? null ) >= $this->since : null );

        $this->postPages = $this->contents = $this->footnotes = $this->seo = $this->terms = $this->variables = [];
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
        return $this->slugOf( $post->post_name, $post->post_title, $post->ID );
    }


    /**
     * Returns the URL path of a post by its slug, title or ID.
     *
     * @param string $name Slug of the post, empty if WordPress didn't create one
     * @param string $title Title of the post used if the slug is empty
     * @param int|string $id ID of the post used if the title contains no characters for the slug like wp_insert_post() does
     */
    protected function slugOf( string $name, string $title, int|string $id ): string
    {
        // WordPress stores the non-ASCII characters of slugs URL encoded, see sanitize_title_with_dashes()
        return $name !== '' ? rawurldecode( $name ) : ( Utils::slugify( $title ) ?: (string) $id );
    }


    /**
     * Returns the title of the post as plain text.
     *
     * Titles can contain HTML tags like "<em>", which WordPress removes for the title of the document.
     *
     * @param WpPost $post
     */
    protected function title( object $post ): string
    {
        $title = trim( html_entity_decode( strip_tags( $post->post_title ), ENT_QUOTES, 'UTF-8' ) );
        return $title !== '' ? $title : html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' );
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

        if( $page = $this->pageByPath( trim( $this->stringOption( 'blog-path' ), '/' ) ) )
        {
            $this->blogId = (string) $page->id;
            $this->printVanished();
        }

        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Returns the object building the meta data of the posts from the settings of the SEO plugins.
     *
     * Canonical URLs to the posts of the site point to their imported pages, others to the WordPress site are left out.
     */
    protected function meta(): WordPress\Meta
    {
        return $this->meta ??= new WordPress\Meta( $this->reader->site(), $this->noindex, function( string $url ) {
            if( ( $result = $this->links()->canonical( $url ) ) === null ) {
                $this->warn( "  Removed canonical URL {$url} because it isn't an imported post" );
            }

            return $result;
        }, $this->lang );
    }


    /**
     * Returns the object rewriting the links to the posts of the WordPress site to their imported pages.
     */
    protected function links(): WordPress\Links
    {
        if( $this->links ) {
            return $this->links;
        }

        $permalinks = $this->reader->permalinks();
        $paths = $this->paths();
        $old = [];

        foreach( $this->oldSlugs() as $id => $slugs )
        {
            foreach( $slugs as $slug ) {
                $old[$slug] ??= $paths[$id] ?? null;
            }
        }

        return $this->links = new WordPress\Links(
            $this->reader->siteUrls(),
            $paths,
            fn( string $msg ) => $this->warn( $msg ),
            $permalinks['structure'],
            $permalinks['bases'],
            array_filter( $old, fn( $path ) => $path !== null )
        );
    }


    /**
     * Returns the URL paths of the pages of all imported posts, also of the ones which aren't rendered again.
     *
     * @return array<int|string, string> URL paths without leading slash by post ID
     */
    protected function paths(): array
    {
        if( $this->paths === null )
        {
            $this->paths = [];

            foreach( $this->names ??= $this->reader->names() as $id => $post ) {
                $this->paths[$id] = $this->slugOf( $post->post_name, $post->post_title, $id );
            }
        }

        return $this->paths;
    }


    /**
     * Returns the former slugs of the imported posts decoded like the current ones.
     *
     * @return array<int|string, array<int, string>> Former slugs by post ID
     */
    protected function oldSlugs(): array
    {
        return $this->oldSlugs ??= array_map( fn( $slugs ) => array_values( array_unique( array_map( 'rawurldecode', $slugs ) ) ), $this->reader->oldSlugs() );
    }


    /**
     * Creates redirect pages for the former slugs and the permalinks of the posts like WordPress redirects them to the current URL.
     *
     * Former slugs and permalinks used by another post or page are skipped like WordPress only redirects URLs without post.
     */
    protected function importRedirects( PageView $blogPage ): void
    {
        $paths = $this->paths();
        $used = $this->currentPaths();
        $tally = new Tally();

        foreach( $this->redirects() as $path => $id )
        {
            $path = (string) $path;

            if( isset( $used[$path] ) ) {
                continue;
            }

            $used[$path] = true;
            $data = ['to' => '/' . $paths[$id]] + $this->pageData( $path, $path, Pages::STATUS_HIDDEN );
            $result = $this->transaction( "redirect /{$path}", fn() => $this->saveRedirect( $path, $data, $blogPage ) ?? false );

            if( $result === false ) {
                $this->warn( "  Skipped redirect: /{$path} (URL is used by another page)" );
            } elseif( $result ) {
                $tally->add( $result['status'] );
                $this->info( '  ' . $result['status']->label() . ": Redirect /{$path} to /{$paths[$id]}" );
            }
        }

        $this->subtotals( $tally, 'redirects' );
    }


    /**
     * Returns the URL paths of the redirects to the pages of the posts.
     *
     * Former slugs are redirected like by wp_old_slug_redirect(). If the permalinks of the site aren't the slugs
     * of the posts, e.g. "/2026/01/15/my-post/", the permalinks of the current and former slugs are redirected
     * too, so links to the WordPress site still work. Permalinks of former dates ("_wp_old_date") are redirected
     * like by _find_post_by_old_date().
     *
     * @return array<string, int|string> Post IDs by URL path without slashes
     */
    protected function redirects(): array
    {
        if( $this->redirects !== null ) {
            return $this->redirects;
        }

        $paths = $this->paths();
        $structure = trim( $this->reader->permalinks()['structure'], '/' );
        $redirects = [];

        foreach( $this->oldSlugs() as $id => $slugs )
        {
            foreach( isset( $paths[$id] ) ? $slugs : [] as $slug ) {
                $redirects[$slug] ??= $id;
            }
        }

        $tags = ['%category%' => [], '%author%' => []];

        foreach( ['%category%' => fn() => $this->reader->categoryPaths(), '%author%' => fn() => $this->reader->authors()] as $tag => $fn )
        {
            if( $structure !== '' && str_contains( $structure, $tag ) && ( $tags[$tag] = $fn() ) === null )
            {
                $this->warn( "  Permalinks /{$structure}/ aren't redirected because the " . trim( $tag, '%' ) . ' of the posts isn\'t available' );
                $structure = '';
            }
        }

        $oldDates = $structure !== '' ? $this->reader->oldDates() : [];

        foreach( $structure !== '' ? $this->names ?? [] : [] as $id => $post )
        {
            if( !isset( $paths[$id] ) ) {
                continue;
            }

            $extra = array_filter( ['%category%' => $tags['%category%'][$id] ?? null, '%author%' => $tags['%author%'][$id] ?? null] );
            // like wp_old_slug_redirect(), former slugs use the current date and former dates the current slug
            $links = array_map( fn( $slug ) => [$slug, $post->post_date], [$paths[$id], ...$this->oldSlugs()[$id] ?? []] );

            foreach( $oldDates[$id] ?? [] as $date ) {
                $links[] = [$paths[$id], $date . substr( $post->post_date, 10 )];
            }

            foreach( $links as [$slug, $date] )
            {
                if( ( $path = $this->permalink( $structure, $id, $slug, $date, $extra ) ) !== null ) {
                    $redirects[$path] ??= $id;
                }
            }
        }

        return $this->redirects = $redirects;
    }


    /**
     * Returns the URL path of the permalink of the post like get_permalink() of WordPress.
     *
     * @param string $structure Permalink structure without leading and trailing slashes like "%year%/%monthnum%/%postname%"
     * @param int|string $id Post ID
     * @param string $slug Decoded slug of the post
     * @param string $date Local date of the post used for the date tags of the permalink
     * @param array<string, string> $extra Values of the other tags like "%category%" and "%author%"
     * @return string|null URL path without slashes or NULL if the permalink is the slug or it can't be built
     */
    protected function permalink( string $structure, int|string $id, string $slug, string $date, array $extra = [] ): ?string
    {
        $time = preg_match( '/^[1-9]\d{3}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ? $date : null;
        $tags = [
            '%postname%' => $slug,
            '%post_id%' => (string) $id,
            '%year%' => $time ? substr( $time, 0, 4 ) : null,
            '%monthnum%' => $time ? substr( $time, 5, 2 ) : null,
            '%day%' => $time ? substr( $time, 8, 2 ) : null,
            '%hour%' => $time ? substr( $time, 11, 2 ) : null,
            '%minute%' => $time ? substr( $time, 14, 2 ) : null,
            '%second%' => $time ? substr( $time, 17, 2 ) : null,
        ] + $extra;

        // tags of plugins and dates of posts without date can't be replaced
        $path = preg_replace_callback( '/%[a-z_]+%/', fn( $m ) => $tags[$m[0]] ?? "\0", $structure );

        return $path !== null && !str_contains( $path, "\0" ) && ( $path = trim( $path, '/' ) ) !== $slug ? $path : null;
    }


    /**
     * Creates the redirect page of the former slug or updates the one of a previous import.
     *
     * The page of a post imported before the post was renamed becomes the redirect page.
     *
     * @param string $slug Former slug of the post
     * @param array<string, mixed> $pageData Page data from pageData() including the link target
     * @param PageView $blogPage Blog page the posts are imported below
     * @return array{page: PageView, status: Status}|null Saved page and its status or NULL if the URL is used by another page
     */
    protected function saveRedirect( string $slug, array $pageData, PageView $blogPage ): ?array
    {
        $page = $this->pageByPath( $slug );

        if( $page && $page->to === '' && $page->parent_id === $blogPage->id && $this->isImported( $page ) && !isset( $this->usedPages[$page->id ?? ''] ) ) {
            return $this->storeLink( $pageData, $blogPage, $page );
        }

        return $this->saveLink( $slug, $pageData, $blogPage );
    }


    /**
     * Returns the page of the post or the redirect page of a former slug which uses the URL path.
     *
     * The redirect page of a former slug is replaced by the post using the slug now, WordPress shows the post too.
     *
     * @param string $path URL path of the page
     * @return PageView|null Page restored if it was trashed or NULL if no page uses the URL
     * @throws \RuntimeException If the URL is used by a page which wasn't imported, was changed since or was saved by this import
     */
    #[\Override]
    protected function previousPage( string $path ): ?PageView
    {
        $page = $this->pageByPath( $path );

        if( $page && $page->to !== '' && $this->linkOwned( $page, ['lang' => $this->lang] ) && !isset( $this->usedPages[$page->id ?? ''] ) )
        {
            $this->usedPages[$page->id ?? ''] = true;
            return $this->restore( $page );
        }

        return parent::previousPage( $path );
    }


    /**
     * Returns the URL paths of the blog page and the pages of all imported posts.
     *
     * @return array<string, bool> URL paths without slashes as keys
     */
    protected function currentPaths(): array
    {
        $paths = [trim( $this->stringOption( 'blog-path' ), '/' ) => true];

        foreach( $this->paths() as $path ) {
            $paths[trim( $path, '/' )] = true;
        }

        return $paths;
    }


    /**
     * Returns the URL paths of the blog page, the pages of all imported posts and their redirects for disableVanished().
     *
     * The redirects are included because the dry run saves none of them.
     *
     * @return array<string, bool> URL paths without slashes as keys
     */
    protected function importedPaths(): array
    {
        $paths = $this->currentPaths();

        foreach( array_keys( $this->redirects() ) as $path ) {
            $paths[trim( (string) $path, '/' )] = true;
        }

        return $paths;
    }


    /**
     * Limits the vanished pages to the articles and redirects below the blog page, so pages of other blogs aren't disabled.
     *
     * Redirects of former slugs and permalinks are disabled if their post isn't imported any more, WordPress deletes
     * the former slugs together with the post.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Aimeos\Cms\Models\PageVariant> $query Query for the page variants
     * @return \Illuminate\Database\Eloquent\Builder<\Aimeos\Cms\Models\PageVariant> Limited query
     */
    protected function vanishedScope( \Illuminate\Database\Eloquent\Builder $query ): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where( fn( $query ) => $query->where( 'to', '' )->where( 'tag', 'article' )->orWhere( 'to', '!=', '' ) )
            ->whereHas( 'page', fn( $query ) => $query->where( 'parent_id', $this->blogId ?? '' ) );
    }


    /**
     * Returns why the pages of previous imports are disabled.
     */
    protected function vanishedReason(): string
    {
        return "its WordPress post isn't imported any more, e.g. it was deleted, trashed or unpublished, or the post has no such former slug or permalink";
    }


    /**
     * Returns the warning shown after disabling the pages of previous imports.
     *
     * @param int $count Number of disabled pages
     */
    protected function vanishedMessage( int $count ): string
    {
        return "Disabled {$count} pages of previous imports whose WordPress posts aren't imported any more.";
    }


    /**
     * Returns the converter for the content of the WordPress posts.
     */
    protected function wordpress(): WordPress\Html
    {
        return $this->wordpress ??= new WordPress\Html(
            fn( string $tag ) => $this->media->image( $tag ),
            fn( string $url, string $name = '' ) => $this->media->url( $url, $name ),
            fn( string $msg ) => $this->warn( $msg ),
            fn( string $fileId ) => ( $file = File::find( $fileId ) ) ? Files::stored( $file ) : null
        );
    }
}
