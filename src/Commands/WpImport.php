<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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
        {--media-url= : Base URL for WordPress uploads (replaces wp-content/uploads path)}
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
    protected string $mediaUrl;
    protected string $editor;
    /** @var Collection<int|string, mixed> */
    protected Collection $attachments;


    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->wpConnection = (string) $this->option( 'connection' ); // @phpstan-ignore cast.string
        $this->domain = (string) ($this->option( 'domain' ) ?: ''); // @phpstan-ignore cast.string
        $this->lang = (string) $this->option( 'lang' ); // @phpstan-ignore cast.string
        $this->type = (string) $this->option( 'type' ); // @phpstan-ignore cast.string
        $this->mediaUrl = rtrim( (string) ($this->option( 'media-url' ) ?: ''), '/' ); // @phpstan-ignore cast.string
        $this->editor = (string) $this->option( 'editor' ); // @phpstan-ignore cast.string

        $this->setupTenant();

        if( !$this->check() ) {
            return;
        }

        $posts = $this->fetchPosts();

        if( $posts->isEmpty() ) {
            $this->warn( 'No published WordPress posts found.' );
            return;
        }

        $this->info( "Found {$posts->count()} published WordPress posts." );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun( $posts );
            return;
        }

        $this->attachments = $this->fetchAttachments();
        $blogPage = $this->getBlogPage();

        $this->importPosts( $posts, $blogPage );
    }


    /**
     * Builds the content elements array with article element first.
     *
     * @param array<int|string, mixed> $bodyElements
     * @return array<int|string, mixed>
     */
    protected function buildContentElements( string $intro, ?string $coverFileId, array $bodyElements ): array
    {
        $articleData = ['text' => $intro];

        if( $coverFileId ) {
            $articleData['file'] = ['id' => $coverFileId, 'type' => 'file'];
        }

        return array_merge(
            [['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'data' => $articleData]],
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
     */
    protected function createArticleVersion( Page $page, array $pageData, array $contentElements, array $fileIds ): void
    {
        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => $pageData,
            'aux' => ['content' => $contentElements],
            'editor' => $this->editor,
        ] );

        if( !empty( $fileIds ) ) {
            $version->files()->attach( $fileIds );
        }

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );
    }


    /**
     * Creates a File record with a published version.
     */
    protected function createFile( string $mime, string $name, string $path ): string
    {
        $path = $this->rewriteMediaUrl( $path );

        $file = File::forceCreate( [
            'mime' => $mime,
            'name' => $name,
            'path' => $path,
            'editor' => $this->editor,
        ] );

        $version = $file->versions()->forceCreate( [
            'data' => ['mime' => $mime, 'name' => $name, 'path' => $path, 'previews' => []],
            'editor' => $this->editor,
        ] );

        $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $file->publish( $version );

        return $file->id;
    }


    /**
     * Creates a Pagible File record from a WordPress attachment.
     */
    protected function createFileFromAttachment( object $attachment, string $alt = '' ): string
    {
        $url = $attachment->guid; // @phpstan-ignore property.notFound
        $name = $alt ?: $attachment->post_title ?: basename( parse_url( $url, PHP_URL_PATH ) ?: 'image' ); // @phpstan-ignore property.notFound
        $mime = $attachment->post_mime_type ?: $this->guessMimeFromUrl( $url ); // @phpstan-ignore property.notFound

        return $this->createFile( $mime, $name, $url );
    }


    /**
     * Creates a Pagible File record from a URL.
     */
    protected function createFileFromUrl( string $url, string $alt = '' ): ?string
    {
        if( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return null;
        }

        $name = $alt ?: basename( parse_url( $url, PHP_URL_PATH ) ?: 'image' );
        $mime = $this->guessMimeFromUrl( $url );

        return $this->createFile( $mime, $name, $url );
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
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchAttachments(): Collection
    {
        return DB::connection( $this->wpConnection )
            ->table( 'wp_posts' )
            ->where( 'post_type', 'attachment' )
            ->whereIn( 'post_mime_type', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'] )
            ->get()
            ->keyBy( 'ID' );
    }


    /**
     * Fetches published WordPress posts.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchPosts(): Collection
    {
        return DB::connection( $this->wpConnection ) // @phpstan-ignore return.type
            ->table( 'wp_posts' )
            ->where( 'post_type', 'post' )
            ->where( 'post_status', 'publish' )
            ->orderBy( 'post_date', 'asc' )
            ->get();
    }


    /**
     * Finds a WordPress attachment matching the given image URL.
     */
    protected function findAttachmentByUrl( string $src ): ?object
    {
        foreach( $this->attachments as $attachment )
        {
            if( str_contains( $src, basename( $attachment->guid ) ) ) {
                return $attachment;
            }
        }

        return null;
    }


    /**
     * Finds or creates the parent blog page.
     */
    protected function getBlogPage(): Page
    {
        $blogPath = (string) $this->option( 'blog-path' ); // @phpstan-ignore cast.string
        $blogName = (string) $this->option( 'blog-name' ); // @phpstan-ignore cast.string

        $page = Page::where( 'path', $blogPath )->first();

        if( $page ) {
            $this->info( "Using existing blog page: {$page->name} (/{$blogPath})" );
            return $page;
        }

        $page = Page::forceCreate( [
            'name' => $blogName,
            'title' => $blogName,
            'path' => $blogPath,
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => 1,
            'editor' => $this->editor,
            'content' => [
                ['id' => Utils::uid(), 'type' => $this->type, 'group' => 'main', 'data' => ['title' => $blogName]],
            ],
        ] );

        if( $root = Page::where( 'tag', 'root' )->first() ) {
            $page->appendToNode( $root )->save();
        }

        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => [
                'name' => $blogName,
                'title' => $blogName,
                'path' => $blogPath,
                'domain' => $this->domain,
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

        // Inline formatting
        $text = (string) preg_replace( '/<strong>(.*?)<\/strong>/is', '**$1**', $text );
        $text = (string) preg_replace( '/<b>(.*?)<\/b>/is', '**$1**', $text );
        $text = (string) preg_replace( '/<em>(.*?)<\/em>/is', '*$1*', $text );
        $text = (string) preg_replace( '/<i>(.*?)<\/i>/is', '*$1*', $text );
        $text = (string) preg_replace( '/<del>(.*?)<\/del>/is', '~~$1~~', $text );
        $text = (string) preg_replace( '/<s>(.*?)<\/s>/is', '~~$1~~', $text );
        $text = (string) preg_replace( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text );
        $text = (string) preg_replace( '/<code>(.*?)<\/code>/is', '`$1`', $text );
        $text = (string) preg_replace( '/<hr\s*\/?>/', "\n---\n", $text );
        $text = (string) preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $text );
        $text = (string) preg_replace( '/<br\s*\/?>/', "\n", $text );
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

        if( !$thumbnailId || !isset( $this->attachments[$thumbnailId] ) ) {
            return null;
        }

        return $this->createFileFromAttachment( $this->attachments[$thumbnailId] );
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
    protected function importPost( object $post, Page $blogPage ): void
    {
        $slug = $post->post_name ?: Utils::slugify( $post->post_title ); // @phpstan-ignore-line property.notFound
        $title = html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ); // @phpstan-ignore-line property.notFound
        $intro = $post->post_excerpt ?: $this->extractIntro( $post->post_content ); // @phpstan-ignore-line property.notFound

        $content = $this->parseContent( $post->post_content ); // @phpstan-ignore property.notFound
        $coverFileId = $this->importFeaturedImage( $post->ID ); // @phpstan-ignore property.notFound

        $contentElements = $this->buildContentElements( $intro, $coverFileId, $content['elements'] );
        $fileIds = $this->collectFileIds( $content['fileIds'], $coverFileId );
        $pageData = $this->buildPageData( $title, $slug );

        $page = $this->createArticlePage( $pageData, $contentElements, $blogPage );
        $this->createArticleVersion( $page, $pageData, $contentElements, $fileIds );

        if( $post->post_date && $post->post_date !== '0000-00-00 00:00:00' ) { // @phpstan-ignore property.notFound
            $page->update( ['created_at' => $post->post_date] );
        }
    }


    /**
     * Imports all posts under the given blog page.
     *
     * @param Collection<int|string, mixed> $posts
     */
    protected function importPosts( Collection $posts, Page $blogPage ): void
    {
        $imported = 0;

        foreach( $posts as $post )
        {
            try {
                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $post, $blogPage ) {
                    $this->importPost( $post, $blogPage );
                } );

                $imported++;
                $this->info( "  Imported: {$post->post_title}" );
            } catch( \Exception $e ) {
                $this->error( "  Failed to import [{$post->ID}] {$post->post_title}: " . $e->getMessage() );
            }
        }

        $this->info( "Import complete. {$imported}/{$posts->count()} posts imported." );
    }


    /**
     * Dispatches a single HTML block to the appropriate parser.
     *
     * @return array<string, mixed>|null
     */
    protected function parseBlock( string $block, ?string $wpType = null ): ?array
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
                    $elements = array_merge( $elements, $result['elements'] );
                    $fileIds = array_merge( $fileIds, $result['fileIds'] ?? [] );
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
                if( $entry['type'] === 'image' && in_array( $align, ['left', 'right'] ) )
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
                            $elements = array_merge( $elements, $result['elements'] );
                        }
                    }
                    continue;
                }

                // Parse wp:columns with image+text pattern before comments are stripped
                if( $entry['type'] === 'columns' )
                {
                    $result = $this->parseColumnsBlock( $entry['html'] );
                    if( $result ) {
                        $elements = array_merge( $elements, $result['elements'] );
                        $fileIds = array_merge( $fileIds, $result['fileIds'] ?? [] );
                    }
                    continue;
                }

                $result = $this->parseBlock( $block, $entry['type'] );

                if( $result ) {
                    $elements = array_merge( $elements, $result['elements'] );
                    $fileIds = array_merge( $fileIds, $result['fileIds'] ?? [] );
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
                    $elements = array_merge( $elements, $result['elements'] );
                    $fileIds = array_merge( $fileIds, $result['fileIds'] ?? [] );
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
            'data' => ['text' => $block],
        ]]];
    }


    /**
     * Prints a dry run summary.
     *
     * @param Collection<int|string, mixed> $posts
     */
    protected function printDryRun( Collection $posts ): void
    {
        foreach( $posts as $post ) {
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
        if( empty( $this->mediaUrl ) ) {
            return $url;
        }

        // Replace everything up to and including wp-content/uploads/
        if( preg_match( '#^(https?://[^/]+)?/.+?/wp-content/uploads/(.+)$#i', $url, $m ) ) {
            return $this->mediaUrl . '/' . $m[2];
        }

        // Handle relative paths starting with wp-content/uploads/
        if( preg_match( '#^/?wp-content/uploads/(.+)$#i', $url, $m ) ) {
            return $this->mediaUrl . '/' . $m[1];
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
