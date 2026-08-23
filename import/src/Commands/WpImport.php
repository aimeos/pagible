<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Http\UploadedFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class WpImport extends Command
{
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
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports WordPress posts into Pagible CMS blog article pages';

    protected string $wpConnection;
    protected string $domain;
    protected string $lang;
    protected string $type;
    protected string $theme;
    /** @var array<string> */
    protected array $mediaUrls = [];
    protected string $editor;
    /** @var array<int|string, array{guid: string, title: string, mime: string}> */
    protected array $attachmentsById = [];
    /** @var array<string, array{guid: string, title: string, mime: string}> */
    protected array $attachmentsByBasename = [];
    /** @var array<string, string> */
    protected array $createdFileIds = [];


    /**
     * Execute command
     */
    public function handle(): void
    {
        $optConn = $this->option( 'connection' );
        $this->wpConnection = is_string( $optConn ) ? $optConn : 'wordpress';
        $optDomain = $this->option( 'domain' );
        $this->domain = is_string( $optDomain ) ? $optDomain : '';
        $optLang = $this->option( 'lang' );
        $this->lang = is_string( $optLang ) ? $optLang : 'en';
        $optType = $this->option( 'type' );
        $this->type = is_string( $optType ) ? $optType : 'blog';
        $optTheme = $this->option( 'theme' );
        $this->theme = is_string( $optTheme ) ? $optTheme : '';
        $optMedia = $this->option( 'media-url' );
        $this->mediaUrls = [];

        foreach( is_array( $optMedia ) ? $optMedia : [$optMedia] as $url )
        {
            if( is_string( $url ) && ( $url = rtrim( trim( $url ), '/' ) ) !== ''
                && !in_array( $url, $this->mediaUrls, true ) ) {
                $this->mediaUrls[] = $url;
            }
        }
        $optEditor = $this->option( 'editor' );
        $this->editor = is_string( $optEditor ) ? $optEditor : 'wp-import';
        $this->createdFileIds = [];

        $this->setupTenant();

        if( !$this->check() ) {
            return;
        }

        $postQuery = DB::connection( $this->wpConnection )
            ->table( 'wp_posts' )
            ->where( 'post_type', 'post' )
            ->where( 'post_status', 'publish' )
            ->orderBy( 'post_date', 'asc' );

        $postCount = $postQuery->count();

        if( $postCount === 0 ) {
            $this->warn( 'No published WordPress posts found.' );
            return;
        }

        $this->info( "Found {$postCount} published WordPress posts." );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun( $postQuery );
            return;
        }

        $this->fetchAttachments();
        $blogPage = $this->getBlogPage();

        $this->importPosts( $postQuery, $blogPage, $postCount );
    }


    /**
     * Builds the content elements array with article element first.
     *
     * @param array<int|string, mixed> $bodyElements
     * @return array<int|string, mixed>
     */
    protected function buildContentElements( string $intro, ?string $coverFileId, array $bodyElements,
        ?string $previewFileId = null
    ): array
    {
        $articleData = ['text' => $intro];
        $articleFiles = $previewFileId ? [$previewFileId] : [];

        if( $coverFileId ) {
            $articleData['file'] = ['id' => $coverFileId, 'type' => 'file'];
            $articleFiles = [$coverFileId];
        }

        return array_merge(
            [['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => $articleFiles,
                'data' => $articleData]],
            $bodyElements
        );
    }


    /**
     * Builds the page data array for an article.
     *
     * @return array<string, mixed>
     */
    protected function buildPageData( string $title, string $slug ): array
    {
        return [
            'name' => $title,
            'title' => $title,
            'path' => $slug,
            'tag' => 'article',
            'type' => $this->type,
            'theme' => $this->theme,
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => 1,
            'editor' => $this->editor,
        ];
    }


    /**
     * Tests the WordPress database connection.
     */
    protected function check(): bool
    {
        try {
            DB::connection( $this->wpConnection )->getPdo();
            return true;
        } catch( \Exception $e ) {
            $this->error( "Cannot connect to WordPress database using connection \"{$this->wpConnection}\"." );
            $this->error( "Add a \"{$this->wpConnection}\" connection to config/database.php, e.g.:" );
            $this->line( "  '{$this->wpConnection}' => [" );
            $this->line( "      'driver' => 'mysql'," );
            $this->line( "      'host' => env('WP_DB_HOST', '127.0.0.1')," );
            $this->line( "      'database' => env('WP_DB_DATABASE', 'wordpress')," );
            $this->line( "      'username' => env('WP_DB_USERNAME', 'root')," );
            $this->line( "      'password' => env('WP_DB_PASSWORD', '')," );
            $this->line( "  ]" );
            return false;
        }
    }


    /**
     * Cleans a code block by removing HTML tags and decoding entities.
     */
    protected function cleanCodeBlock( string $code ): string
    {
        $code = (string) preg_replace( '/<\/?code>/i', '', $code );
        $code = strip_tags( $code );
        $code = html_entity_decode( $code, ENT_QUOTES, 'UTF-8' );

        return trim( $code );
    }


    /**
     * Merges body file IDs with an optional cover file ID.
     *
     * @param array<string> $bodyFileIds
     * @return array<string>
     */
    protected function collectFileIds( array $bodyFileIds, ?string $coverFileId ): array
    {
        if( $coverFileId ) {
            $bodyFileIds[] = $coverFileId;
        }

        return $bodyFileIds;
    }


    /**
     * Creates the article page and attaches files.
     *
     * @param array<string, mixed> $pageData
     * @param array<int|string, mixed> $contentElements
     * @param Page $blogPage
     * @return Page The created article page
     */
    protected function createArticlePage( array $pageData, array $contentElements, Page $blogPage ): Page
    {
        $page = Page::forceCreate( $pageData + ['content' => $contentElements] );
        $page->appendToNode( $blogPage )->save();

        return $page;
    }


    /**
     * Creates a version for the article page and publishes it.
     *
     * @param array<string, mixed> $pageData
     * @param array<int|string, mixed> $contentElements
     * @param array<string> $fileIds
     * @param array<string> $elementIds
     */
    protected function createArticleVersion( Page $page, array $pageData, array $contentElements, array $fileIds,
        array $elementIds
    ): void {
        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => $pageData,
            'aux' => ['content' => $contentElements],
            'editor' => $this->editor,
        ] );

        if( !empty( $fileIds ) ) {
            $version->files()->attach( $fileIds );
        }
        if( !empty( $elementIds ) ) {
            $version->elements()->attach( $elementIds );
        }

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );
    }


    /**
     * Creates a File record with a published version.
     */
    protected function createFile( string $mime, string $name, string $path, bool $download = false ): ?string
    {
        $path = $this->rewriteMediaUrl( $path );

        if( !Utils::isValidUrl( $path, strict: false ) ) {
            return null;
        }

        if( ( $id = $this->createdFileIds[$path] ?? null ) && File::whereKey( $id )->exists() ) {
            return $id;
        }

        $file = new File();
        $resource = null;

        try
        {
            $file->mime = $mime;
            $file->name = $name;
            $file->editor = $this->editor;

            if( $download )
            {
                $resource = $this->downloadFile( $path );

                if( $mime === 'image/svg+xml' ) {
                    $this->prepareSvgResource( $resource );
                }

                $tmp = stream_get_meta_data( $resource )['uri'] ?? null;
                $filename = basename( rawurldecode( (string) parse_url( $path, PHP_URL_PATH ) ) ) ?: $name;

                if( !is_string( $tmp ) ) {
                    throw new \Aimeos\Cms\Exception( 'Unable to create temporary file' );
                }

                $file->ingest( new UploadedFile( $tmp, $filename, $mime, null, true ) );
            }
            else
            {
                $file->path = $path;
                $file->previews = [];
            }

            $file->save();

            $snapshot = File::snapshot( $file->toArray() );
            $version = $file->versions()->forceCreate( [
                'lang' => $file->lang,
                'data' => $snapshot['data'],
                'aux' => $snapshot['aux'],
                'editor' => $this->editor,
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );

            $id = $file->id ?? '';
            $this->createdFileIds[$path] = $id;

            return $id;
        }
        catch( \Throwable $e )
        {
            $file->removePreviews()->removeFile();
            throw $e;
        }
        finally
        {
            if( is_resource( $resource ) ) {
                fclose( $resource );
            }
        }
    }


    /**
     * Creates a Pagible File record from a WordPress attachment.
     *
     * @param array<string, string> $attachment
     */
    protected function createFileFromAttachment( array $attachment, string $alt = '' ): ?string
    {
        $url = $attachment['guid'];
        $name = $alt ?: $attachment['title'] ?: basename( parse_url( $url, PHP_URL_PATH ) ?: 'image' );
        $mime = $attachment['mime'] ?: $this->guessMimeFromUrl( $url );

        return $this->createFile( $mime, $name, $url, $this->isManagedMediaUrl( $url ) );
    }


    /**
     * Creates a Pagible File record from a URL.
     */
    protected function createFileFromUrl( string $url, string $alt = '' ): ?string
    {
        $name = $alt ?: basename( parse_url( $url, PHP_URL_PATH ) ?: 'image' );
        $mime = $this->guessMimeFromUrl( $url );

        return $this->createFile( $mime, $name, $url, $this->isManagedMediaUrl( $url ) );
    }


    /**
     * Downloads a remote file into a bounded temporary stream.
     *
     * @return resource
     */
    protected function downloadFile( string $url )
    {
        $response = Utils::http( $url, ['stream' => true] );

        if( !$response->successful() ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Failed to download "%s"', $url ) );
        }

        $limit = max( 0, (float) config( 'cms.upload.filesize', 50 ) );
        $max = (int) ( $limit * 1024 * 1024 );
        $body = $response->toPsrResponse()->getBody();
        $length = trim( $response->header( 'Content-Length' ) );

        if( $length !== '' && ctype_digit( $length ) && (int) $length > $max ) {
            $body->close();
            throw new \Aimeos\Cms\Exception( 'Remote file exceeds the maximum upload size' );
        }

        if( !( $tmp = tmpfile() ) ) {
            $body->close();
            throw new \Aimeos\Cms\Exception( 'Unable to create temporary file' );
        }

        $size = 0;

        while( !$body->eof() )
        {
            $chunk = $body->read( min( 1048576, $max - $size + 1 ) );
            $size += strlen( $chunk );

            if( $size > $max ) {
                $body->close();
                fclose( $tmp );
                throw new \Aimeos\Cms\Exception( 'Remote file exceeds the maximum upload size' );
            }

            fwrite( $tmp, $chunk );
        }

        $body->close();
        fseek( $tmp, 0 );

        return $tmp;
    }


    /**
     * Adds an XML declaration so fileinfo recognizes plain SVG markup.
     *
     * @param resource $resource
     */
    protected function prepareSvgResource( $resource ): void
    {
        rewind( $resource );
        $content = stream_get_contents( $resource );

        if( !is_string( $content ) ) {
            throw new \Aimeos\Cms\Exception( 'Unable to read SVG file' );
        }

        $normalized = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $content );

        if( preg_match( '/^\s*<\?xml\b/i', $normalized ) !== 1 ) {
            $normalized = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $normalized;
        }

        if( $normalized !== $content )
        {
            rewind( $resource );

            if( !ftruncate( $resource, 0 ) || fwrite( $resource, $normalized ) !== strlen( $normalized ) ) {
                throw new \Aimeos\Cms\Exception( 'Unable to normalize SVG file' );
            }
        }

        rewind( $resource );
    }


    /**
     * Extracts the first paragraph of text as an introduction.
     */
    protected function extractIntro( string $html ): string
    {
        if( str_contains( $html, '<!--more-->' ) ) {
            $html = explode( '<!--more-->', $html )[0];
        }

        $text = strip_tags( $html );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = (string) preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        if( mb_strlen( $text ) > 300 ) {
            $text = mb_substr( $text, 0, 297 ) . '...';
        }

        return $text;
    }


    /**
     * Fetches WordPress image attachments keyed by ID.
     */
    protected function fetchAttachments(): void
    {
        $this->attachmentsById = [];
        $this->attachmentsByBasename = [];

        DB::connection( $this->wpConnection )
            ->table( 'wp_posts' )
            ->select( 'ID', 'guid', 'post_title', 'post_mime_type' )
            ->where( 'post_type', 'attachment' )
            ->whereIn( 'post_mime_type', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'] )
            ->orderBy( 'ID' )
            ->chunk( 100, function( $chunk ) {
                foreach( $chunk as $attachment )
                {
                    $this->attachmentsById[$attachment->ID] = [
                        'guid' => $attachment->guid,
                        'title' => $attachment->post_title,
                        'mime' => $attachment->post_mime_type,
                    ];
                    $this->attachmentsByBasename[basename( $attachment->guid )] = &$this->attachmentsById[$attachment->ID];
                }
            } );
    }


    /**
     * Fetches published WordPress posts.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function fetchPosts(): \Illuminate\Database\Query\Builder
    {
        return DB::connection( $this->wpConnection )
            ->table( 'wp_posts' )
            ->where( 'post_type', 'post' )
            ->where( 'post_status', 'publish' )
            ->orderBy( 'post_date', 'asc' );
    }


    /**
     * Finds an existing article by its unique destination route.
     */
    protected function findArticlePage( string $slug ): ?Page
    {
        $page = Page::withTrashed()
            ->where( 'domain', $this->domain )
            ->where( 'path', $slug )
            ->first();

        if( $page?->trashed() ) {
            $page->restore();
        }

        return $page;
    }


    /**
     * Returns the shared footer references inherited from the blog parent.
     *
     * @return array{elements: array<int, array<string, mixed>>, elementIds: array<string>, fileIds: array<string>}
     */
    protected function findFooterReferences( Page $blogPage ): array
    {
        $elements = collect( (array) $blogPage->content )
            ->filter( fn( $item ) => ( $item->type ?? null ) === 'reference'
                && ( $item->group ?? null ) === 'footer'
                && is_string( $item->refid ?? null )
                && $item->refid !== '' )
            ->map( fn( $item ) => (array) $item )
            ->values()
            ->all();

        $elementIds = array_values( array_unique( array_column( $elements, 'refid' ) ) );
        $fileIds = $blogPage->elements()
            ->whereIn( 'cms_elements.id', $elementIds )
            ->with( 'files:id' )
            ->get()
            ->flatMap( fn( $element ) => $element->files->pluck( 'id' ) )
            ->unique()
            ->values()
            ->all();

        return [
            'elements' => $elements,
            'elementIds' => $elementIds,
            'fileIds' => $fileIds,
        ];
    }


    /**
     * Returns the first imported image file ID while preserving content order.
     *
     * @param array<string> $fileIds
     */
    protected function findPreviewFileId( array $fileIds ): ?string
    {
        $files = File::whereIn( 'id', array_unique( $fileIds ) )->get( ['id', 'mime'] )->keyBy( 'id' );

        foreach( $fileIds as $id ) {
            if( str_starts_with( (string) $files->get( $id )?->mime, 'image/' ) ) {
                return $id;
            }
        }

        return null;
    }


    /**
     * Finds a WordPress attachment matching the given image URL.
     */
    /**
     * @return array{guid: string, title: string, mime: string}|null
     */
    protected function findAttachmentByUrl( string $src ): ?array
    {
        foreach( $this->attachmentsByBasename as $basename => $attachment )
        {
            if( str_contains( $src, $basename ) ) {
                return $attachment;
            }
        }

        return null;
    }


    /**
     * Publishes the selected theme for an existing blog page.
     */
    protected function updateBlogTheme( Page $page ): void
    {
        if( $this->theme === '' || $page->theme === $this->theme ) {
            return;
        }

        $data = $page->toArray();
        $data['theme'] = $this->theme;
        $version = $page->versions()->forceCreate( [
            'lang' => $page->lang ?: $this->lang,
            'data' => $data,
            'aux' => [
                'content' => (array) $page->content,
                'config' => $page->config,
                'meta' => $page->meta,
            ],
            'editor' => $this->editor,
        ] );

        $version->files()->attach( $page->files->keys()->all() );
        $version->elements()->attach( $page->elements->keys()->all() );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );
    }


    /**
     * Finds or creates the parent blog page.
     */
    protected function getBlogPage(): Page
    {
        $optPath = $this->option( 'blog-path' );
        $blogPath = is_string( $optPath ) ? $optPath : 'blog';
        $optName = $this->option( 'blog-name' );
        $blogName = is_string( $optName ) ? $optName : 'Blog';

        $page = Page::withTrashed()
            ->where( 'domain', $this->domain )
            ->where( 'path', $blogPath )
            ->first();

        if( $page ) {
            if( $page->trashed() ) {
                $page->restore();
            }

            $this->updateBlogTheme( $page );

            $this->info( "Using existing blog page: {$page->name} (/{$blogPath})" );
            return $page;
        }

        $page = Page::forceCreate( [
            'name' => $blogName,
            'title' => $blogName,
            'path' => $blogPath,
            'domain' => $this->domain,
            'lang' => $this->lang,
            'type' => $this->type,
            'theme' => $this->theme,
            'status' => 1,
            'editor' => $this->editor,
            'content' => [
                ['id' => Utils::uid(), 'type' => $this->type, 'group' => 'main', 'data' => ['title' => $blogName]],
            ],
        ] );

        if( $root = Page::where( 'tag', 'root' )->where( 'domain', $this->domain )->first() ) {
            $page->appendToNode( $root )->save();
        }

        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => [
                'name' => $blogName,
                'title' => $blogName,
                'path' => $blogPath,
                'domain' => $this->domain,
                'type' => $this->type,
                'theme' => $this->theme,
                'status' => 1,
                'editor' => $this->editor,
            ],
            'aux' => [
                'content' => [
                    ['id' => Utils::uid(), 'type' => $this->type, 'group' => 'main', 'data' => ['title' => $blogName]],
                ],
            ],
            'editor' => $this->editor,
        ] );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        $this->info( "Created blog page: {$blogName} (/{$blogPath})" );
        return $page;
    }


    /**
     * Guesses MIME type from a URL file extension.
     */
    protected function guessMimeFromUrl( string $url ): string
    {
        $ext = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );

        return match( $ext ) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mp3' => 'audio/mpeg',
            'ogg', 'oga' => 'audio/ogg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            default => 'application/octet-stream',
        };
    }


    /**
     * Checks if HTML contains tags that cannot be fully converted to markdown.
     *
     * Simple inline formatting (bold, italic, links, code, paragraphs, line breaks,
     * lists, blockquotes) is convertible. Anything else (tables, divs, figures,
     * iframes, etc.) is not.
     */
    protected function hasUnconvertibleHtml( string $html ): bool
    {
        $convertible = 'p|br|strong|b|em|i|a|code|ul|ol|li|blockquote|span|sup|sub|del|s|mark|abbr|hr';
        $stripped = preg_replace( '/<\/?(' . $convertible . ')(\s[^>]*)?\/?>/i', '', $html );

        return (bool) preg_match( '/<[a-z][a-z0-9]*[\s>\/]/i', $stripped ?? '' );
    }


    /**
     * Converts basic HTML to Markdown for Pagible text elements.
     */
    protected function htmlToMarkdown( string $html ): string
    {
        $text = $html;

        // Blockquotes
        $text = (string) preg_replace( '/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n> $1\n", $text );

        // Lists — convert before inline formatting
        $text = (string) preg_replace_callback( '/<ol[^>]*>(.*?)<\/ol>/is', function( $m ) {
            $i = 0;
            return "\n" . preg_replace_callback( '/<li[^>]*>(.*?)<\/li>/is', function( $li ) use ( &$i ) {
                return ++$i . '. ' . trim( strip_tags( $li[1], '<strong><b><em><i><a><code>' ) ) . "\n";
            }, $m[1] );
        }, $text );

        $text = (string) preg_replace_callback( '/<ul[^>]*>(.*?)<\/ul>/is', function( $m ) {
            return "\n" . (string) preg_replace_callback( '/<li[^>]*>(.*?)<\/li>/is', function( $li ) {
                return '- ' . trim( strip_tags( $li[1], '<strong><b><em><i><a><code>' ) ) . "\n";
            }, $m[1] );
        }, $text );

        // Inline formatting — links need a separate pass due to capture group numbering
        $text = (string) preg_replace( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text );
        $text = (string) preg_replace( [
            '/<strong>(.*?)<\/strong>/is',
            '/<b>(.*?)<\/b>/is',
            '/<em>(.*?)<\/em>/is',
            '/<i>(.*?)<\/i>/is',
            '/<del>(.*?)<\/del>/is',
            '/<s>(.*?)<\/s>/is',
            '/<code>(.*?)<\/code>/is',
            '/<hr\s*\/?>/',
            '/<p[^>]*>(.*?)<\/p>/is',
            '/<br\s*\/?>/',
        ], [
            '**$1**', '**$1**', '*$1*', '*$1*', '~~$1~~', '~~$1~~', '`$1`', "\n---\n", "$1\n\n", "\n",
        ], $text );
        $text = strip_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = (string) preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }


    /**
     * Imports the featured image (post thumbnail) for a WordPress post.
     */
    protected function importFeaturedImage( int $postId ): ?string
    {
        $thumbnailId = DB::connection( $this->wpConnection )
            ->table( 'wp_postmeta' )
            ->where( 'post_id', $postId )
            ->where( 'meta_key', '_thumbnail_id' )
            ->value( 'meta_value' );

        if( !$thumbnailId || !isset( $this->attachmentsById[$thumbnailId] ) ) {
            return null;
        }

        return $this->createFileFromAttachment( $this->attachmentsById[$thumbnailId] );
    }


    /**
     * Imports an image from an HTML img tag.
     */
    protected function importImageFromHtml( string $html ): ?string
    {
        if( !preg_match( '/src=["\']([^"\']+)["\']/i', $html, $srcMatch ) ) {
            return null;
        }

        $src = $srcMatch[1];
        $alt = '';
        if( preg_match( '/alt=["\']([^"\']*)["\']/', $html, $altMatch ) ) {
            $alt = $altMatch[1];
        }

        if( $attachment = $this->findAttachmentByUrl( $src ) ) {
            return $this->createFileFromAttachment( $attachment, $alt );
        }

        return $this->createFileFromUrl( $src, $alt );
    }


    /**
     * Imports a single WordPress post as a Pagible blog article page.
     */
    protected function importPost( object $post, Page $blogPage ): bool
    {
        $slug = $post->post_name ?: Utils::slugify( $post->post_title ); // @phpstan-ignore-line property.notFound
        $title = html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ); // @phpstan-ignore-line property.notFound
        $intro = $post->post_excerpt ?: $this->extractIntro( $post->post_content ); // @phpstan-ignore-line property.notFound
        $page = $this->findArticlePage( $slug );
        $updated = $page !== null;

        $content = $this->parseContent( $post->post_content ); // @phpstan-ignore property.notFound
        $coverFileId = $this->importFeaturedImage( $post->ID ); // @phpstan-ignore property.notFound
        $previewFileId = $coverFileId ?? $this->findPreviewFileId( $content['fileIds'] );
        $footer = $this->findFooterReferences( $blogPage );

        $contentElements = array_merge(
            $this->buildContentElements( $intro, $coverFileId, $content['elements'], $previewFileId ),
            $footer['elements']
        );
        $fileIds = array_values( array_unique( array_merge(
            $this->collectFileIds( $content['fileIds'], $coverFileId ),
            $footer['fileIds']
        ) ) );
        $pageData = $this->buildPageData( $title, $slug );

        if( !$page ) {
            $page = $this->createArticlePage( $pageData, $contentElements, $blogPage );
        } elseif( $page->parent_id !== $blogPage->id ) {
            $page->appendToNode( $blogPage )->save();
        }

        $this->createArticleVersion( $page, $pageData, $contentElements, $fileIds, $footer['elementIds'] );

        $date = $post->post_date_gmt ?? null;

        if( !$date || $date === '0000-00-00 00:00:00' ) {
            $date = $post->post_date ?? null;
        }

        if( $date && $date !== '0000-00-00 00:00:00' ) {
            $page->forceFill( ['created_at' => $date] )->saveQuietly();
        }

        return $updated;
    }


    /**
     * Imports all posts under the given blog page.
     */
    protected function importPosts( \Illuminate\Database\Query\Builder $query, Page $blogPage, int $total ): void
    {
        $imported = 0;
        $updated = 0;

        $query->chunk( 100, function( $posts ) use ( $blogPage, &$imported, &$updated ) {
            foreach( $posts as $post )
            {
                try {
                    $existing = DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $post, $blogPage ) {
                        return $this->importPost( $post, $blogPage );
                    } );

                    $existing ? $updated++ : $imported++;
                    $action = $existing ? 'Updated' : 'Imported';
                    $this->info( "  {$action}: {$post->post_title}" );
                } catch( \Exception $e ) {
                    $this->error( "  Failed to import [{$post->ID}] {$post->post_title}: " . $e->getMessage() );
                }
            }
        } );

        $processed = $imported + $updated;
        $this->info( "Import complete. {$imported} imported, {$updated} updated ({$processed}/{$total} posts processed)." );
    }


    /**
     * Tests whether a media URL belongs to WordPress-managed uploads.
     */
    protected function isManagedMediaUrl( string $url ): bool
    {
        $url = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $path = parse_url( $url, PHP_URL_PATH );

        if( is_string( $path ) && preg_match( '#(?:^|/)wp-content/uploads(?:/|$)#i', $path ) === 1 ) {
            return true;
        }

        foreach( $this->mediaUrls as $base )
        {
            if( $this->matchesMediaBase( $url, $base ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Tests whether a URL is located at or below a configured media base URL.
     */
    protected function matchesMediaBase( string $url, string $base ): bool
    {
        $candidate = parse_url( $url );
        $configured = parse_url( $base );

        if( !is_array( $candidate ) || !is_array( $configured ) ) {
            return false;
        }

        $scheme = strtolower( (string) ( $candidate['scheme'] ?? '' ) );
        $baseScheme = strtolower( (string) ( $configured['scheme'] ?? '' ) );
        $host = strtolower( (string) ( $candidate['host'] ?? '' ) );
        $baseHost = strtolower( (string) ( $configured['host'] ?? '' ) );

        if( !in_array( $scheme, ['http', 'https'], true ) || $scheme !== $baseScheme || $host === '' || $host !== $baseHost ) {
            return false;
        }

        $port = (int) ( $candidate['port'] ?? ( $scheme === 'https' ? 443 : 80 ) );
        $basePort = (int) ( $configured['port'] ?? ( $baseScheme === 'https' ? 443 : 80 ) );

        if( $port !== $basePort ) {
            return false;
        }

        $path = '/' . ltrim( (string) ( $candidate['path'] ?? '/' ), '/' );
        $basePath = rtrim( '/' . ltrim( (string) ( $configured['path'] ?? '/' ), '/' ), '/' ) ?: '/';

        return $basePath === '/' || $path === $basePath || str_starts_with( $path, $basePath . '/' );
    }


    /**
     * Dispatches a single HTML block to the appropriate parser.
     *
     * @return array<string, mixed>|null
     */
    protected function parseBlock( string $block, ?string $wpType = null ): ?array
    {
        if( $wpType !== null )
        {
            // Skip decorative/layout-only Gutenberg blocks
            if( in_array( $wpType, ['separator', 'spacer', 'buttons', 'button', 'columns', 'column', 'group', 'more'] ) ) {
                return null;
            }

            // Use Gutenberg type hints to map to Pagible schema types
            if( $wpType === 'heading' ) {
                return $this->parseHeadingBlock( $block ) ?? $this->parseTextBlock( $block );
            }

            if( in_array( $wpType, ['code', 'preformatted', 'syntaxhighlighter/code'] ) ) {
                return $this->parseCodeBlock( $block ) ?? $this->parseTextBlock( $block );
            }

            if( $wpType === 'image' ) {
                return $this->parseStandaloneImageBlock( $block ) ?? $this->parseImageTextBlock( $block );
            }

            if( $wpType === 'gallery' ) {
                return $this->parseGalleryBlock( $block ) ?? $this->parseStandaloneImageBlock( $block );
            }

            if( in_array( $wpType, ['media-text', 'cover'] ) ) {
                return $this->parseImageTextBlock( $block );
            }

            if( $wpType === 'table' ) {
                return $this->parseTableBlock( $block ) ?? $this->parseTextBlock( $block );
            }

            if( in_array( $wpType, ['video', 'core-embed/youtube', 'core-embed/vimeo', 'embed'] ) ) {
                return $this->parseVideoBlock( $block ) ?? $this->parseTextBlock( $block );
            }

            if( $wpType === 'audio' ) {
                return $this->parseAudioBlock( $block );
            }

            if( in_array( $wpType, ['paragraph', 'list', 'quote', 'pullquote', 'verse', 'freeform'] ) ) {
                if( preg_match( '/<img[^>]+>/i', $block ) ) {
                    return $this->parseImageTextBlock( $block );
                }
                return $this->parseTextBlock( $block );
            }
        }

        // Fall through to existing HTML-based detection for unknown/null types
        if( $result = $this->parseHeadingBlock( $block ) ) {
            return $result;
        }

        if( $result = $this->parseCodeBlock( $block ) ) {
            return $result;
        }

        if( $result = $this->parseStandaloneImageBlock( $block ) ) {
            return $result;
        }

        if( $result = $this->parseNoticeBlock( $block ) ) {
            return $result;
        }

        if( preg_match( '/<img[^>]+>/i', $block ) ) {
            return $this->parseImageTextBlock( $block );
        }

        return $this->parseTextBlock( $block );
    }


    /**
     * Parses a <pre> tag into a code element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseCodeBlock( string $block ): ?array
    {
        if( !preg_match( '/^<pre[^>]*>(.*?)<\/pre>$/is', $block, $m ) ) {
            return null;
        }

        $language = '';
        if( preg_match( '/lang=["\']?(\w+)/i', $block, $langMatch ) ) {
            $language = strtolower( $langMatch[1] );
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'code',
            'data' => [
                'language' => $language,
                'text' => $this->cleanCodeBlock( $m[1] ),
            ],
        ]]];
    }


    /**
     * Parses a wp:columns block into an image-text element if it contains
     * one image column and one text column, otherwise processes inner content.
     *
     * @return array<string, mixed>|null
     */
    protected function parseColumnsBlock( string $block ): ?array
    {
        // Extract individual column contents using wp:column markers
        $columnPattern = '/<!--\s*wp:column(?:\s+\{[^}]*\})?\s*-->(.*?)<!--\s*\/wp:column\s*-->/is';

        if( !preg_match_all( $columnPattern, $block, $columns ) ) {
            return null;
        }

        $imageCol = null;
        $imageColIndex = null;
        $textParts = [];

        foreach( $columns[1] as $i => $colContent )
        {
            $colContent = trim( $this->stripWpComments( $colContent ) );

            if( empty( $colContent ) ) {
                continue;
            }

            // Check if this column is primarily an image
            $stripped = strip_tags( $colContent, '<img><a>' );
            if( preg_match( '/^\s*(?:<a[^>]*>\s*)?<img[^>]+>(?:\s*<\/a>)?\s*$/is', $stripped ) && $imageCol === null ) {
                $imageCol = $colContent;
                $imageColIndex = $i;
            } else {
                $textParts[] = $colContent;
            }
        }

        // If we have exactly one image column and at least one text column, create image-text
        if( $imageCol !== null && !empty( $textParts ) )
        {
            $fileId = $this->importImageFromHtml( $imageCol );
            $textHtml = implode( "\n", $textParts );
            $textHtml = (string) preg_replace( '/<(figure|div|figcaption)[^>]*>|<\/(figure|div|figcaption)>/i', '', $textHtml );
            $text = $this->htmlToMarkdown( $textHtml );

            if( $fileId && !empty( $text ) )
            {
                // Image after text = end position, image before text = start position
                $position = $imageColIndex === 0 ? 'start' : 'end';

                return [
                    'elements' => [[
                        'id' => Utils::uid(),
                        'type' => 'image-text',
                        'data' => [
                            'text' => $text,
                            'file' => ['id' => $fileId, 'type' => 'file'],
                            'position' => $position,
                        ],
                    ]],
                    'fileIds' => [$fileId],
                ];
            }
        }

        // Fallback: process all column content as individual blocks
        $elements = [];
        $fileIds = [];

        foreach( $columns[1] as $colContent )
        {
            $colContent = trim( $this->stripWpComments( $colContent ) );
            if( empty( $colContent ) ) {
                continue;
            }

            // Try to parse inner Gutenberg blocks within the column
            $innerBlocks = $this->splitGutenbergBlocks( $colContent );

            foreach( $innerBlocks as $inner )
            {
                $innerHtml = trim( $this->stripWpComments( $inner['html'] ) );
                if( empty( $innerHtml ) ) {
                    continue;
                }

                $result = $this->parseBlock( $innerHtml, $inner['type'] );
                if( $result ) {
                    array_push( $elements, ...$result['elements'] );
                    if( !empty( $result['fileIds'] ) ) {
                        array_push( $fileIds, ...$result['fileIds'] );
                    }
                }
            }
        }

        if( !empty( $elements ) ) {
            return ['elements' => $elements, 'fileIds' => $fileIds];
        }

        return null;
    }


    /**
     * Merges consecutive text elements (same type) into a single element.
     *
     * Consecutive "text" elements are joined with double newline (markdown).
     * Consecutive "html" elements are concatenated directly.
     *
     * @param array<int|string, mixed> $elements
     * @return array<int|string, mixed>
     */
    protected function mergeConsecutiveText( array $elements ): array
    {
        $merged = [];

        foreach( $elements as $el )
        {
            $prev = end( $merged );

            if( $prev && $prev['type'] === $el['type']
                && in_array( $el['type'], ['text', 'html'] )
                && isset( $prev['data']['text'] ) && isset( $el['data']['text'] ) )
            {
                $sep = $el['type'] === 'text' ? "\n\n" : '';
                $key = array_key_last( $merged ) ?? 0;
                $merged[$key]['data']['text'] .= $sep . $el['data']['text'];
            }
            else
            {
                $merged[] = $el;
            }
        }

        return $merged;
    }


    /**
     * Parses an audio block into an audio element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseAudioBlock( string $block ): ?array
    {
        if( !preg_match( '/src=["\']([^"\']+)["\']/', $block, $m ) ) {
            return null;
        }

        $fileId = $this->createFileFromUrl( $m[1] );

        if( !$fileId ) {
            return null;
        }

        return [
            'elements' => [[
                'id' => Utils::uid(),
                'type' => 'audio',
                'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
            ]],
            'fileIds' => [$fileId],
        ];
    }


    /**
     * Parses WordPress post content HTML into Pagible content elements.
     *
     * @return array<string, mixed>
     */
    protected function parseContent( string $html ): array
    {
        $html = (string) preg_replace( '/<!--more-->/', '', $html );
        $html = str_replace( "\r\n", "\n", $html );

        $elements = [];
        $fileIds = [];

        // Detect Gutenberg content and use block comments as type hints
        $isGutenberg = (bool) preg_match( '/<!--\s*wp:/', $html );

        if( $isGutenberg )
        {
            $entries = $this->splitGutenbergBlocks( $html );
            $count = count( $entries );

            for( $i = 0; $i < $count; $i++ )
            {
                $entry = $entries[$i];
                $block = trim( $this->stripWpComments( $entry['html'] ) );
                if( empty( $block ) ) {
                    continue;
                }

                // Merge aligned image with following paragraph blocks into image-text
                $align = $entry['attrs']['align'] ?? '';
                if( $entry['type'] === 'image' && ( $align === 'left' || $align === 'right' ) )
                {
                    $fileId = $this->importImageFromHtml( $block );
                    $textParts = [];

                    // Collect following paragraph/list/quote blocks as text
                    while( $i + 1 < $count && in_array( $entries[$i + 1]['type'], ['paragraph', 'list', 'quote', 'pullquote'] ) )
                    {
                        $i++;
                        $nextBlock = trim( $this->stripWpComments( $entries[$i]['html'] ) );
                        if( !empty( $nextBlock ) ) {
                            $textParts[] = $nextBlock;
                        }
                    }

                    $text = implode( "\n", $textParts );
                    $text = (string) preg_replace( '/<(figure|div|figcaption)[^>]*>|<\/(figure|div|figcaption)>/i', '', $text );
                    $text = $this->htmlToMarkdown( $text );

                    if( $fileId && !empty( $text ) ) {
                        $position = $align === 'left' ? 'start' : 'end';
                        $elements[] = [
                            'id' => Utils::uid(),
                            'type' => 'image-text',
                            'data' => [
                                'text' => $text,
                                'file' => ['id' => $fileId, 'type' => 'file'],
                                'position' => $position,
                            ],
                        ];
                        $fileIds[] = $fileId;
                        continue;
                    } elseif( $fileId ) {
                        $elements[] = [
                            'id' => Utils::uid(),
                            'type' => 'image',
                            'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
                        ];
                        $fileIds[] = $fileId;
                    }

                    // Process collected text blocks that weren't merged
                    if( !empty( $text ) ) {
                        $result = $this->parseTextBlock( $text );
                        if( $result ) {
                            array_push( $elements, ...$result['elements'] );
                        }
                    }
                    continue;
                }

                // Parse wp:columns with image+text pattern before comments are stripped
                if( $entry['type'] === 'columns' )
                {
                    $result = $this->parseColumnsBlock( $entry['html'] );
                    if( $result ) {
                        array_push( $elements, ...$result['elements'] );
                        array_push( $fileIds, ...( $result['fileIds'] ?? [] ) );
                    }
                    continue;
                }

                $result = $this->parseBlock( $block, $entry['type'] );

                if( $result ) {
                    array_push( $elements, ...$result['elements'] );
                    array_push( $fileIds, ...( $result['fileIds'] ?? [] ) );
                }
            }
        }
        else
        {
            foreach( $this->splitIntoBlocks( $html ) as $block )
            {
                $block = trim( $block );
                if( empty( $block ) ) {
                    continue;
                }

                $result = $this->parseBlock( $block );

                if( $result ) {
                    array_push( $elements, ...$result['elements'] );
                    array_push( $fileIds, ...( $result['fileIds'] ?? [] ) );
                }
            }
        }

        $elements = array_map( fn( $el ) => $el + ['group' => 'main'], $this->mergeConsecutiveText( $elements ) );

        return ['elements' => $elements, 'fileIds' => $fileIds];
    }


    /**
     * Parses a gallery block into a slideshow element with multiple images.
     *
     * @return array<string, mixed>|null
     */
    protected function parseGalleryBlock( string $block ): ?array
    {
        if( !preg_match_all( '/<img[^>]+>/i', $block, $matches ) || count( $matches[0] ) < 2 ) {
            return null;
        }

        $fileIds = [];

        foreach( $matches[0] as $imgTag )
        {
            $fileId = $this->importImageFromHtml( $imgTag );
            if( $fileId ) {
                $fileIds[] = $fileId;
            }
        }

        if( count( $fileIds ) < 2 ) {
            return null;
        }

        return [
            'elements' => [[
                'id' => Utils::uid(),
                'type' => 'slideshow',
                'data' => [
                    'title' => '',
                    'files' => array_map( fn( $id ) => ['id' => $id, 'type' => 'file'], $fileIds ),
                ],
            ]],
            'fileIds' => $fileIds,
        ];
    }


    /**
     * Parses an HTML heading tag into a heading element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseHeadingBlock( string $block ): ?array
    {
        if( !preg_match( '/^<h([1-6])[^>]*>(.*?)<\/h[1-6]>$/is', $block, $m ) ) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'heading',
            'data' => [
                'level' => (int) $m[1],
                'title' => strip_tags( $m[2] ),
            ],
        ]]];
    }


    /**
     * Parses a text block containing an inline image into an image-text element.
     *
     * @return array<string, mixed>
     */
    protected function parseImageTextBlock( string $html ): array
    {
        $elements = [];
        $fileIds = [];

        if( !preg_match( '/(?:<a[^>]*>\s*)?<img[^>]+>(?:\s*<\/a>)?/i', $html, $imgMatch ) ) {
            return ['elements' => $elements, 'fileIds' => $fileIds];
        }

        $fileId = $this->importImageFromHtml( $imgMatch[0] );
        $remaining = trim( str_replace( $imgMatch[0], '', $html ) );
        $remaining = (string) preg_replace( '/<(figure|div|figcaption)[^>]*>|<\/(figure|div|figcaption)>/i', '', $remaining );
        $text = $this->htmlToMarkdown( $remaining );

        if( $fileId && !empty( $text ) )
        {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image-text',
                'data' => [
                    'text' => $text,
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ],
            ];
            $fileIds[] = $fileId;
        }
        elseif( $fileId )
        {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image',
                'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
            ];
            $fileIds[] = $fileId;
        }
        elseif( !empty( $text ) )
        {
            return $this->parseTextBlock( $text ) ?? ['elements' => [], 'fileIds' => []];
        }

        return ['elements' => $elements, 'fileIds' => $fileIds];
    }


    /**
     * Parses a tip/note/caution div into a styled text element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseNoticeBlock( string $block ): ?array
    {
        if( !preg_match( '/^<div class="(tip|note|caution)">(.*?)<\/div>$/is', $block, $m ) ) {
            return null;
        }

        $label = $m[1] === 'caution' ? 'Warning' : ucfirst( $m[1] );

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'text',
            'data' => ['text' => "**{$label}:** " . strip_tags( $m[2] )],
        ]]];
    }


    /**
     * Parses a standalone image (or linked image) into an image element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseStandaloneImageBlock( string $block ): ?array
    {
        $stripped = strip_tags( $block, '<img><a>' );
        $stripped = trim( $stripped );

        $isLinkedImage = preg_match( '/^<a[^>]*>\s*<img[^>]+>\s*<\/a>$/is', $stripped );
        $isBareImage = preg_match( '/^<img[^>]+\/>?$/is', $stripped );

        if( !$isLinkedImage && !$isBareImage ) {
            return null;
        }

        $fileId = $this->importImageFromHtml( $block );

        if( !$fileId ) {
            return null;
        }

        return [
            'elements' => [[
                'id' => Utils::uid(),
                'type' => 'image',
                'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
            ]],
            'fileIds' => [$fileId],
        ];
    }


    /**
     * Parses a video block into a video element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseVideoBlock( string $block ): ?array
    {
        $url = null;

        // Try <video> tag, then iframe/embed src attribute
        if( preg_match( '/<video[^>]+src=["\']([^"\']+)["\']/', $block, $m ) ) {
            $url = $m[1];
        } elseif( preg_match( '/<iframe[^>]+src=["\']([^"\']+)["\']/', $block, $m ) ) {
            $url = $m[1];
        }

        // Try plain-text URL (YouTube/Vimeo embeds have URL as text content)
        if( !$url && preg_match( '/https?:\/\/(?:(?:www\.)?youtube\.com\/watch\?v=|youtu\.be\/|(?:www\.)?vimeo\.com\/)[^\s<"]+/i', $block, $m ) ) {
            $url = $m[0];
        }

        if( $url )
        {
            $fileId = $this->createFileFromUrl( $url );

            if( $fileId ) {
                return [
                    'elements' => [[
                        'id' => Utils::uid(),
                        'type' => 'video',
                        'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
                    ]],
                    'fileIds' => [$fileId],
                ];
            }
        }

        return null;
    }


    /**
     * Parses an HTML table into a table element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseTableBlock( string $block ): ?array
    {
        if( !preg_match( '/<table[^>]*>(.*?)<\/table>/is', $block, $m ) ) {
            return null;
        }

        $rows = [];
        $header = '';

        if( preg_match( '/<thead[^>]*>(.*?)<\/thead>/is', $m[1], $thead ) ) {
            $header = 'row';
            if( preg_match_all( '/<t[hd][^>]*>(.*?)<\/t[hd]>/is', $thead[1], $cells ) ) {
                $rows[] = array_map( fn( $c ) => strip_tags( $c ), $cells[1] );
            }
        }

        if( preg_match( '/<tbody[^>]*>(.*?)<\/tbody>/is', $m[1], $tbody ) ) {
            $bodyHtml = $tbody[1];
        } else {
            $bodyHtml = $m[1];
        }

        if( preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/is', $bodyHtml, $trs ) ) {
            foreach( $trs[1] as $tr ) {
                if( preg_match_all( '/<t[hd][^>]*>(.*?)<\/t[hd]>/is', $tr, $cells ) ) {
                    $rows[] = array_map( fn( $c ) => strip_tags( $c ), $cells[1] );
                }
            }
        }

        if( empty( $rows ) ) {
            return null;
        }

        $title = '';
        if( preg_match( '/<caption[^>]*>(.*?)<\/caption>/is', $m[1], $cap ) ) {
            $title = strip_tags( $cap[1] );
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'table',
            'data' => [
                'title' => $title,
                'header' => $header,
                'table' => $rows,
            ],
        ]]];
    }


    /**
     * Parses a text/HTML block into a text (markdown) or html element.
     *
     * If the HTML can be fully converted to markdown, uses the "text" schema
     * (markdown field). Otherwise falls back to the "html" schema.
     *
     * @return array<string, mixed>|null
     */
    protected function parseTextBlock( string $block ): ?array
    {
        $block = trim( $block );

        if( empty( $block ) ) {
            return null;
        }

        $markdown = $this->htmlToMarkdown( $block );

        if( !empty( $markdown ) && !$this->hasUnconvertibleHtml( $block ) ) {
            return ['elements' => [[
                'id' => Utils::uid(),
                'type' => 'text',
                'data' => ['text' => $markdown],
            ]]];
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'data' => ['text' => Utils::html( $block )],
        ]]];
    }


    /**
     * Prints a dry run summary.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    protected function printDryRun( \Illuminate\Database\Query\Builder $query ): void
    {
        foreach( $query->cursor() as $post ) {
            $this->line( "  [{$post->ID}] {$post->post_title} ({$post->post_name})" );
        }
        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Sets up multi-tenancy if a tenant option is provided.
     */
    /**
     * Rewrites a WordPress media URL using the configured base URL.
     */
    protected function rewriteMediaUrl( string $url ): string
    {
        $base = $this->mediaUrls[0] ?? '';

        if( $base === '' ) {
            return $url;
        }

        $path = parse_url( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), PHP_URL_PATH );

        if( is_string( $path ) && preg_match( '#(?:^|/)wp-content/uploads/(.+)$#i', $path, $m ) ) {
            return $base . '/' . $m[1];
        }

        return $url;
    }


    protected function setupTenant(): void
    {
        if( $tenant = $this->option( 'tenant' ) )
        {
            \Aimeos\Cms\Tenancy::$callback = function() use ( $tenant ) {
                return $tenant;
            };
        }
    }


    /**
     * Splits Gutenberg content into blocks with their wp type hints.
     *
     * Parses `<!-- wp:type -->...<!-- /wp:type -->` and self-closing `<!-- wp:type /-->` comments.
     * Nested blocks are flattened — only top-level block types are used as hints.
     *
     * @return array<array{type: string|null, attrs: array<string, mixed>, html: string}>
     */
    protected function splitGutenbergBlocks( string $html ): array
    {
        $blocks = [];
        $pattern = '/<!--\s*wp:([a-z][a-z0-9\-]*(?:\/[a-z][a-z0-9\-]*)?)(\s+\{[^}]*\})?\s*(\/)?-->/i';
        $offset = 0;
        $length = strlen( $html );

        while( $offset < $length )
        {
            if( !preg_match( $pattern, $html, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
                // Remaining content after last block
                $remaining = trim( substr( $html, $offset ) );
                if( !empty( $remaining ) ) {
                    $blocks[] = ['type' => null, 'attrs' => [], 'html' => $remaining];
                }
                break;
            }

            $matchPos = (int) $m[0][1];

            // Content before this block comment (untyped)
            if( $matchPos > $offset ) {
                $before = trim( substr( $html, $offset, $matchPos - $offset ) );
                if( !empty( $before ) ) {
                    $blocks[] = ['type' => null, 'attrs' => [], 'html' => $before];
                }
            }

            $type = $m[1][0];
            $attrs = !empty( $m[2][0] ) ? (array) json_decode( trim( $m[2][0] ), true ) : [];
            $selfClosing = !empty( $m[3][0] );

            if( $selfClosing ) {
                // Self-closing: <!-- wp:type /-->
                $offset = $matchPos + strlen( $m[0][0] );
                continue; // No content to extract
            }

            // Find matching closing comment, tracking depth for nested blocks
            $closePattern = '/<!--\s*(?:(wp:' . preg_quote( $type, '/' ) . ')(?:\s+\{[^}]*\})?\s*-->|(\/?wp:' . preg_quote( $type, '/' ) . ')\s*-->)/i';
            $searchPos = $matchPos + strlen( $m[0][0] );
            $depth = 1;
            $closeEnd = null;
            $contentEnd = $searchPos;
            $contentStart = $searchPos;

            while( $depth > 0 && preg_match( '/<!--\s*(\/)?wp:' . preg_quote( $type, '/' ) . '(?:\s+\{[^}]*\})?\s*(\/)?-->/i', $html, $cm, PREG_OFFSET_CAPTURE, $searchPos ) )
            {
                $isClosing = !empty( $cm[1][0] );
                $isSelfClosing = !empty( $cm[2][0] );

                if( $isSelfClosing ) {
                    $searchPos = (int) $cm[0][1] + strlen( $cm[0][0] );
                    continue;
                }

                if( $isClosing ) {
                    $depth--;
                    if( $depth === 0 ) {
                        $closeEnd = (int) $cm[0][1] + strlen( $cm[0][0] );
                        $contentEnd = (int) $cm[0][1];
                    }
                } else {
                    $depth++;
                }

                $searchPos = (int) $cm[0][1] + strlen( $cm[0][0] );
            }

            if( $closeEnd !== null ) {
                $content = trim( substr( $html, $contentStart, $contentEnd - $contentStart ) );
                if( !empty( $content ) ) {
                    $blocks[] = ['type' => $type, 'attrs' => $attrs, 'html' => $content];
                }
                $offset = $closeEnd;
            } else {
                // No matching close found — skip the opening comment
                $offset = $matchPos + strlen( $m[0][0] );
            }
        }

        return $blocks;
    }


    /**
     * Strips all WordPress block comments from HTML.
     */
    protected function stripWpComments( string $html ): string
    {
        return trim( (string) preg_replace( '/<!--\s*\/?wp:[^>]*-->/i', '', $html ) );
    }


    /**
     * Splits HTML content into logical blocks at heading and pre/div boundaries.
     *
     * @return string[]
     */
    protected function splitIntoBlocks( string $html ): array
    {
        $pattern = '/(<h[1-6][^>]*>.*?<\/h[1-6]>|<pre[^>]*>.*?<\/pre>|<div\s+class="(?:tip|note|caution)"[^>]*>.*?<\/div>)/is';
        $parts = preg_split( $pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

        $blocks = [];

        foreach( $parts as $part )
        {
            $part = trim( $part );
            if( empty( $part ) ) {
                continue;
            }

            if( preg_match( '/^<(h[1-6]|pre|div\s+class="(?:tip|note|caution)")/i', $part ) ) {
                $blocks[] = $part;
                continue;
            }

            $subParts = preg_split( '/(\n\s*\n|(?:<a[^>]*>\s*<img[^>]+>\s*<\/a>)|(?:<img[^>]+\/?>))/is', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

            foreach( $subParts as $sub ) {
                $sub = trim( $sub );
                if( !empty( $sub ) ) {
                    $blocks[] = $sub;
                }
            }
        }

        return $blocks;
    }
}
