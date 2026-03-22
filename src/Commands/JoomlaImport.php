<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class JoomlaImport extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:joomla-import
        {--connection=joomla : Database connection name for the Joomla database}
        {--domain= : Domain name for the imported pages}
        {--lang=en : Language code for the imported pages}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=joomla-import : Editor name for imported records}
        {--file-base= : Base URL for Joomla files (e.g. https://example.com)}
        {--prefix=#__ : Joomla database table prefix}
        {--menu=mainmenu : Menu type to use for page hierarchy}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports Joomla articles into Pagible CMS pages';

    protected string $jConnection;
    protected string $domain;
    protected string $lang;
    protected string $editor;
    protected string $fileBase;
    protected string $prefix;
    /** @var Collection<string, string> */
    protected Collection $createdFiles;


    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->jConnection = (string) $this->option( 'connection' ); // @phpstan-ignore cast.string
        $this->domain = (string) ($this->option( 'domain' ) ?: ''); // @phpstan-ignore cast.string
        $this->lang = (string) $this->option( 'lang' ); // @phpstan-ignore cast.string
        $this->editor = (string) $this->option( 'editor' ); // @phpstan-ignore cast.string
        $this->fileBase = rtrim( (string) ($this->option( 'file-base' ) ?: ''), '/' ); // @phpstan-ignore cast.string
        $this->prefix = (string) $this->option( 'prefix' ); // @phpstan-ignore cast.string
        $this->createdFiles = Collection::make();

        $this->setupTenant();

        if( !$this->check() ) {
            return;
        }

        $articles = $this->fetchArticles();

        if( $articles->isEmpty() ) {
            $this->warn( 'No Joomla articles found.' );
            return;
        }

        $this->info( "Found {$articles->count()} Joomla articles." );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun( $articles );
            return;
        }

        $categories = $this->fetchCategories();
        $menuItems = $this->fetchMenuItems();
        $root = $this->getOrCreateRoot();

        $this->importArticles( $articles, $categories, $menuItems, $root );
    }


    /**
     * Builds content elements from article introtext, fulltext, and images.
     *
     * @param \stdClass $article
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function buildContent( \stdClass $article ): array
    {
        $elements = [];
        $fileIds = [];

        $images = $this->decodeImages( $article->images ?? '' );
        $introImageId = $this->importArticleImage( $images, 'intro' );
        $fulltextImageId = $this->importArticleImage( $images, 'fulltext' );

        if( $introImageId ) {
            $introCaption = $images['image_intro_caption'] ?? '';
            if( !empty( $introCaption ) ) {
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'image-text',
                    'group' => 'main',
                    'data' => [
                        'text' => $introCaption,
                        'file' => ['id' => $introImageId, 'type' => 'file'],
                    ],
                ];
            } else {
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'image',
                    'group' => 'main',
                    'data' => ['file' => ['id' => $introImageId, 'type' => 'file']],
                ];
            }
            $fileIds[] = $introImageId;
        }

        if( !empty( $article->introtext ) ) {
            $parsed = $this->parseHtml( $article->introtext );
            $elements = array_merge( $elements, $parsed['elements'] );
            $fileIds = array_merge( $fileIds, $parsed['fileIds'] );
        }

        if( $fulltextImageId && $fulltextImageId !== $introImageId ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image',
                'group' => 'main',
                'data' => ['file' => ['id' => $fulltextImageId, 'type' => 'file']],
            ];
            $fileIds[] = $fulltextImageId;
        }

        if( !empty( $article->fulltext ) ) {
            $parsed = $this->parseHtml( $article->fulltext );
            $elements = array_merge( $elements, $parsed['elements'] );
            $fileIds = array_merge( $fileIds, $parsed['fileIds'] );
        }

        return ['elements' => $elements, 'fileIds' => array_unique( $fileIds )];
    }


    /**
     * Builds the page data array.
     *
     * @param \stdClass $article
     * @return array<string, mixed>
     */
    protected function buildPageData( \stdClass $article, string $slug ): array
    {
        $metadesc = $article->metadesc ?? '';

        return [
            'name' => $article->title,
            'title' => $article->title,
            'path' => $slug,
            'tag' => 'page',
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => $article->state == 1 ? 1 : 0,
            'editor' => $this->editor,
        ];
    }


    /**
     * Tests the Joomla database connection.
     */
    protected function check(): bool
    {
        try {
            DB::connection( $this->jConnection )->getPdo();
            return true;
        } catch( \Exception $e ) {
            $this->error( "Cannot connect to Joomla database using connection \"{$this->jConnection}\"." );
            $this->error( "Add a \"{$this->jConnection}\" connection to config/database.php, e.g.:" );
            $this->line( "  '{$this->jConnection}' => [" );
            $this->line( "      'driver' => 'mysql'," );
            $this->line( "      'host' => env('JOOMLA_DB_HOST', '127.0.0.1')," );
            $this->line( "      'database' => env('JOOMLA_DB_DATABASE', 'joomla')," );
            $this->line( "      'username' => env('JOOMLA_DB_USERNAME', 'root')," );
            $this->line( "      'password' => env('JOOMLA_DB_PASSWORD', '')," );
            $this->line( "      'prefix' => '',  // set your Joomla table prefix here" );
            $this->line( "  ]" );
            return false;
        }
    }


    /**
     * Creates a File record with a published version.
     */
    protected function createFile( string $mime, string $name, string $path ): string
    {
        if( $existing = $this->createdFiles->get( $path ) ) {
            return $existing;
        }

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

        $this->createdFiles->put( $path, $file->id );

        return $file->id;
    }


    /**
     * Creates a Pagible page with content and attaches files.
     *
     * @param array<string, mixed> $pageData
     * @param array<int, array<string, mixed>> $contentElements
     */
    protected function createPage( array $pageData, array $contentElements, Page $parent ): Page
    {
        $page = Page::forceCreate( $pageData + ['content' => $contentElements] );
        $page->appendToNode( $parent )->save();

        return $page;
    }


    /**
     * Creates a version for a page and publishes it.
     *
     * @param array<string, mixed> $pageData
     * @param array<int, array<string, mixed>> $contentElements
     * @param array<int, string> $fileIds
     */
    protected function createVersion( Page $page, array $pageData, array $contentElements, array $fileIds ): void
    {
        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => $pageData + ['content' => $contentElements],
            'editor' => $this->editor,
        ] );

        if( !empty( $fileIds ) ) {
            $version->files()->attach( $fileIds );
        }

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );
    }


    /**
     * Decodes the JSON images column from a Joomla article.
     *
     * @return array<string, mixed>
     */
    protected function decodeImages( string $json ): array
    {
        if( empty( $json ) ) {
            return [];
        }

        return json_decode( $json, true ) ?: [];
    }


    /**
     * Extracts an article ID from a Joomla menu item link.
     */
    protected function extractArticleId( string $link ): ?int
    {
        if( preg_match( '/option=com_content.*view=article.*id=(\d+)/', $link, $m ) ) {
            return (int) $m[1];
        }

        return null;
    }


    /**
     * Fetches published Joomla articles.
     *
     * @return Collection<int, \stdClass>
     */
    protected function fetchArticles(): Collection
    {
        return DB::connection( $this->jConnection )
            ->table( $this->tableName( 'content' ) )
            ->where( 'state', 1 )
            ->orderBy( 'ordering', 'asc' )
            ->orderBy( 'created', 'asc' )
            ->get();
    }


    /**
     * Fetches published categories keyed by id.
     *
     * @return Collection<array-key, \stdClass>
     */
    protected function fetchCategories(): Collection
    {
        return DB::connection( $this->jConnection )
            ->table( $this->tableName( 'categories' ) )
            ->where( 'extension', 'com_content' )
            ->where( 'published', 1 )
            ->orderBy( 'lft', 'asc' )
            ->get()
            ->keyBy( 'id' );
    }


    /**
     * Fetches published menu items for the configured menu type.
     *
     * @return Collection<int, \stdClass>
     */
    protected function fetchMenuItems(): Collection
    {
        $menuType = (string) $this->option( 'menu' ); // @phpstan-ignore cast.string

        return DB::connection( $this->jConnection )
            ->table( $this->tableName( 'menu' ) )
            ->where( 'menutype', $menuType )
            ->where( 'published', 1 )
            ->where( 'client_id', 0 )
            ->where( 'level', '>', 0 )
            ->orderBy( 'lft', 'asc' )
            ->get();
    }


    /**
     * Gets or creates the root page.
     */
    protected function getOrCreateRoot(): Page
    {
        $page = Page::where( 'tag', 'root' )->first();

        if( $page ) {
            $this->info( "Using existing root page: {$page->name}" );
            return $page;
        }

        $page = Page::forceCreate( [
            'name' => 'Home',
            'title' => 'Home',
            'path' => '',
            'tag' => 'root',
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => 1,
            'editor' => $this->editor,
            'content' => [],
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => [
                'name' => 'Home',
                'title' => 'Home',
                'path' => '',
                'tag' => 'root',
                'domain' => $this->domain,
                'status' => 1,
                'editor' => $this->editor,
                'content' => [],
            ],
            'editor' => $this->editor,
        ] );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        $this->info( 'Created root page.' );
        return $page;
    }


    /**
     * Guesses MIME type from file extension.
     */
    protected function guessMimeFromExtension( string $ext ): string
    {
        return match( strtolower( $ext ) ) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }


    /**
     * Converts basic HTML to Markdown.
     */
    protected function htmlToMarkdown( string $html ): string
    {
        $text = $html;

        $text = (string) preg_replace( '/<strong>(.*?)<\/strong>/is', '**$1**', $text );
        $text = (string) preg_replace( '/<b>(.*?)<\/b>/is', '**$1**', $text );
        $text = (string) preg_replace( '/<em>(.*?)<\/em>/is', '*$1*', $text );
        $text = (string) preg_replace( '/<i>(.*?)<\/i>/is', '*$1*', $text );
        $text = (string) preg_replace( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text );
        $text = (string) preg_replace( '/<code>(.*?)<\/code>/is', '`$1`', $text );
        $text = (string) preg_replace( '/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $text );
        $text = (string) preg_replace( '/<\/?(ul|ol)[^>]*>/is', "\n", $text );
        $text = (string) preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $text );
        $text = (string) preg_replace( '/<br\s*\/?>/', "\n", $text );
        $text = strip_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = (string) preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }


    /**
     * Imports a single article as a Pagible page.
     */
    protected function importArticle( \stdClass $article, Page $parent ): Page
    {
        $slug = $article->alias ?: Utils::slugify( $article->title );
        $pageData = $this->buildPageData( $article, $slug );
        $content = $this->buildContent( $article );

        $page = $this->createPage( $pageData, $content['elements'], $parent );
        $this->createVersion( $page, $pageData, $content['elements'], $content['fileIds'] );

        if( $article->created && $article->created !== '0000-00-00 00:00:00' ) {
            $page->update( ['created_at' => $article->created] );
        }

        return $page;
    }


    /**
     * Imports an article image (intro or fulltext) from the images JSON.
     *
     * @param array<string, mixed> $images
     */
    protected function importArticleImage( array $images, string $type ): ?string
    {
        $path = $images["image_{$type}"] ?? '';

        if( empty( $path ) ) {
            return null;
        }

        $alt = $images["image_{$type}_alt"] ?? '';
        $name = $alt ?: basename( $path );
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $mime = $this->guessMimeFromExtension( $ext );
        $fullPath = $this->resolveFilePath( $path );

        return $this->createFile( $mime, $name, $fullPath );
    }


    /**
     * Imports all articles using menu hierarchy when available.
     *
     * @param Collection<int, \stdClass> $articles
     * @param Collection<array-key, \stdClass> $categories
     * @param Collection<int, \stdClass> $menuItems
     */
    protected function importArticles( Collection $articles, Collection $categories, Collection $menuItems, Page $root ): void
    {
        $articlesById = $articles->keyBy( 'id' );
        $createdPages = Collection::make();
        $imported = 0;

        $menuMap = $this->buildMenuMap( $menuItems );
        $menuRoots = $menuMap->get( 'roots', Collection::make() );
        $categoryPages = Collection::make();

        foreach( $menuRoots as $menuItem )
        {
            $this->importMenuItem( $menuItem, $root, $menuMap, $articlesById, $categories, $createdPages, $categoryPages, $imported );
        }

        foreach( $articlesById as $id => $article )
        {
            if( $createdPages->has( $id ) ) {
                continue;
            }

            $parent = $this->parentForArticle( $article, $categories, $categoryPages, $root );

            try {
                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $article, $parent, &$createdPages, &$imported )
                {
                    $page = $this->importArticle( $article, $parent );
                    $createdPages->put( $article->id, $page );
                    $imported++;
                    $this->info( "  Imported: {$article->title} (/{$article->alias})" );
                } );
            } catch( \Exception $e ) {
                $this->error( "  Failed to import [{$article->id}] {$article->title}: " . $e->getMessage() );
            }
        }

        $this->info( "Import complete. {$imported}/{$articles->count()} articles imported." );
    }


    /**
     * Imports an image referenced inline in HTML content.
     */
    protected function importImageFromHtml( string $imgTag ): ?string
    {
        if( !preg_match( '/src=["\']([^"\']+)["\']/i', $imgTag, $srcMatch ) ) {
            return null;
        }

        $src = $srcMatch[1];
        $alt = '';
        if( preg_match( '/alt=["\']([^"\']*)["\']/', $imgTag, $altMatch ) ) {
            $alt = $altMatch[1];
        }

        $name = $alt ?: basename( parse_url( $src, PHP_URL_PATH ) ?: 'image' );
        $mime = $this->guessMimeFromExtension( pathinfo( $name, PATHINFO_EXTENSION ) );
        $path = $this->resolveFilePath( $src );

        return $this->createFile( $mime, $name, $path );
    }


    /**
     * Imports a menu item and its children recursively.
     *
     * @param Collection<string, mixed> $menuMap
     * @param Collection<array-key, mixed> $articlesById
     * @param Collection<array-key, \stdClass> $categories
     * @param Collection<array-key, mixed> $createdPages
     * @param Collection<array-key, mixed> $categoryPages
     */
    protected function importMenuItem( \stdClass $menuItem, Page $parent, Collection $menuMap, Collection $articlesById, Collection $categories, Collection &$createdPages, Collection &$categoryPages, int &$imported ): void
    {
        $articleId = $this->extractArticleId( $menuItem->link ?? '' );
        $currentPage = $parent;

        if( $articleId && $articlesById->has( $articleId ) && !$createdPages->has( $articleId ) )
        {
            $article = $articlesById->get( $articleId );

            try {
                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $article, $parent, &$createdPages, &$currentPage, &$imported )
                {
                    $page = $this->importArticle( $article, $parent );
                    $createdPages->put( $article->id, $page );
                    $currentPage = $page;
                    $imported++;
                    $this->info( "  Imported: {$article->title} (/{$article->alias})" );
                } );
            } catch( \Exception $e ) {
                $this->error( "  Failed to import [{$articleId}] {$article->title}: " . $e->getMessage() );
            }
        }
        elseif( in_array( $menuItem->type ?? '', ['heading', 'separator'] ) )
        {
            try {
                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $menuItem, $parent, &$currentPage )
                {
                    $currentPage = $this->importMenuHeading( $menuItem, $parent );
                } );
            } catch( \Exception $e ) {
                $this->error( "  Failed to import menu heading [{$menuItem->id}] {$menuItem->title}: " . $e->getMessage() );
            }
        }

        $children = $menuMap->get( 'children:' . $menuItem->id, Collection::make() );

        foreach( $children as $child ) {
            $this->importMenuItem( $child, $currentPage, $menuMap, $articlesById, $categories, $createdPages, $categoryPages, $imported );
        }
    }


    /**
     * Creates a page for a menu heading/separator item.
     */
    protected function importMenuHeading( \stdClass $menuItem, Page $parent ): Page
    {
        $slug = $menuItem->alias ?: Utils::slugify( $menuItem->title );

        $pageData = [
            'name' => $menuItem->title,
            'title' => $menuItem->title,
            'path' => $slug,
            'tag' => 'page',
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => 1,
            'editor' => $this->editor,
        ];

        $page = Page::forceCreate( $pageData + ['content' => []] );
        $page->appendToNode( $parent )->save();

        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => $pageData + ['content' => []],
            'editor' => $this->editor,
        ] );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds a menu map with roots and parent-children relationships.
     *
     * @param Collection<int, \stdClass> $menuItems
     * @return Collection<string, mixed>
     */
    protected function buildMenuMap( Collection $menuItems ): Collection
    {
        $map = Collection::make();
        $roots = Collection::make();
        $byId = $menuItems->keyBy( 'id' );

        foreach( $menuItems as $item )
        {
            $parentId = $item->parent_id ?? 1;

            if( $parentId <= 1 || !$byId->has( $parentId ) ) {
                $roots->push( $item );
            } else {
                $key = 'children:' . $parentId;
                $children = $map->get( $key, Collection::make() );
                $children->push( $item );
                $map->put( $key, $children );
            }
        }

        $map->put( 'roots', $roots );

        return $map;
    }


    /**
     * Dispatches a single HTML block to the appropriate parser.
     *
     * @return array<string, mixed>|null
     */
    protected function parseBlock( string $block ): ?array
    {
        if( $result = $this->parseHeadingBlock( $block ) ) {
            return $result;
        }

        if( $result = $this->parseCodeBlock( $block ) ) {
            return $result;
        }

        if( $result = $this->parseStandaloneImageBlock( $block ) ) {
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
        if( preg_match( '/class=["\'][^"\']*language-(\w+)/i', $block, $langMatch ) ) {
            $language = strtolower( $langMatch[1] );
        }

        $code = (string) preg_replace( '/<\/?code[^>]*>/i', '', $m[1] );
        $code = strip_tags( $code );
        $code = html_entity_decode( $code, ENT_QUOTES, 'UTF-8' );

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'code',
            'group' => 'main',
            'data' => [
                'language' => $language,
                'text' => trim( $code ),
            ],
        ]]];
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
            'group' => 'main',
            'data' => [
                'level' => (int) $m[1],
                'title' => strip_tags( $m[2] ),
            ],
        ]]];
    }


    /**
     * Parses Joomla article HTML into Pagible content elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function parseHtml( string $html ): array
    {
        $html = str_replace( "\r\n", "\n", $html );
        $elements = [];
        $fileIds = [];

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

        return ['elements' => $elements, 'fileIds' => $fileIds];
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

        if( !preg_match( '/<(?:a[^>]*>\s*)?<img[^>]+>(?:\s*<\/a>)?/i', $html, $imgMatch ) ) {
            return $this->parseTextBlock( $html ) ?: ['elements' => [], 'fileIds' => []];
        }

        $fileId = $this->importImageFromHtml( $imgMatch[0] );
        $text = trim( str_replace( $imgMatch[0], '', $html ) );

        if( $fileId && !empty( $text ) ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image-text',
                'group' => 'main',
                'data' => [
                    'text' => $text,
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ],
            ];
            $fileIds[] = $fileId;
        } elseif( $fileId ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image',
                'group' => 'main',
                'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
            ];
            $fileIds[] = $fileId;
        } elseif( !empty( $text ) ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'html',
                'group' => 'main',
                'data' => ['text' => $text],
            ];
        }

        return ['elements' => $elements, 'fileIds' => $fileIds];
    }


    /**
     * Parses a standalone image into an image element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseStandaloneImageBlock( string $block ): ?array
    {
        $isLinkedImage = preg_match( '/^<a[^>]*>\s*<img[^>]+>\s*<\/a>$/is', $block );
        $isBareImage = preg_match( '/^<img[^>]+\/>?$/is', $block );
        $isFigure = preg_match( '/^<figure[^>]*>\s*<img[^>]+>.*?<\/figure>$/is', $block );

        if( !$isLinkedImage && !$isBareImage && !$isFigure ) {
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
                'group' => 'main',
                'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
            ]],
            'fileIds' => [$fileId],
        ];
    }


    /**
     * Parses a plain text/HTML block into an html element.
     *
     * @return array<string, mixed>|null
     */
    protected function parseTextBlock( string $block ): ?array
    {
        $block = trim( $block );

        if( empty( $block ) ) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => $block],
        ]]];
    }


    /**
     * Determines the parent page for an article based on its category.
     *
     * @param Collection<int|string, mixed> $categories
     * @param Collection<int|string, mixed> $categoryPages
     */
    protected function parentForArticle( \stdClass $article, Collection $categories, Collection &$categoryPages, Page $root ): Page
    {
        $catId = $article->catid ?? 0;
        $category = $categories->get( $catId );

        if( !$category || $category->level <= 1 ) {
            return $root;
        }

        if( $categoryPages->has( $catId ) ) {
            return $categoryPages->get( $catId );
        }

        $slug = $category->alias ?: Utils::slugify( $category->title );

        $parentPage = $root;
        if( $category->parent_id && $categories->has( $category->parent_id ) ) {
            $parentPage = $this->parentForCategory( $category->parent_id, $categories, $categoryPages, $root );
        }

        $pageData = [
            'name' => $category->title,
            'title' => $category->title,
            'path' => $slug,
            'tag' => 'page',
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => 1,
            'editor' => $this->editor,
        ];

        $page = Page::forceCreate( $pageData + ['content' => []] );
        $page->appendToNode( $parentPage )->save();

        $version = $page->versions()->forceCreate( [
            'lang' => $this->lang,
            'data' => $pageData + ['content' => []],
            'editor' => $this->editor,
        ] );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        $categoryPages->put( $catId, $page );

        return $page;
    }


    /**
     * Recursively creates parent category pages.
     *
     * @param Collection<int|string, mixed> $categories
     * @param Collection<int|string, mixed> $categoryPages
     */
    protected function parentForCategory( int $catId, Collection $categories, Collection &$categoryPages, Page $root ): Page
    {
        if( $categoryPages->has( $catId ) ) {
            return $categoryPages->get( $catId );
        }

        $category = $categories->get( $catId );

        if( !$category || $category->level <= 1 ) {
            return $root;
        }

        return $this->parentForArticle( (object) ['catid' => $catId, 'title' => '', 'alias' => ''], $categories, $categoryPages, $root );
    }


    /**
     * Prints a dry run summary.
     *
     * @param Collection<int, \stdClass> $articles
     */
    protected function printDryRun( Collection $articles ): void
    {
        foreach( $articles as $article ) {
            $featured = $article->featured ? ' [featured]' : '';
            $this->line( "  [{$article->id}] {$article->title} ({$article->alias}){$featured}" );
        }
        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Resolves a Joomla relative file path to a full URL.
     */
    protected function resolveFilePath( string $path ): string
    {
        if( filter_var( $path, FILTER_VALIDATE_URL ) ) {
            return $path;
        }

        $path = ltrim( $path, '/' );

        if( $this->fileBase ) {
            return $this->fileBase . '/' . $path;
        }

        return $path;
    }


    /**
     * Sets up multi-tenancy if a tenant option is provided.
     */
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
     * Splits HTML content into logical blocks.
     *
     * @return string[]
     */
    protected function splitIntoBlocks( string $html ): array
    {
        $pattern = '/(<h[1-6][^>]*>.*?<\/h[1-6]>|<pre[^>]*>.*?<\/pre>|<figure[^>]*>.*?<\/figure>)/is';
        $parts = preg_split( $pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

        $blocks = [];

        foreach( $parts as $part )
        {
            $part = trim( $part );
            if( empty( $part ) ) {
                continue;
            }

            if( preg_match( '/^<(h[1-6]|pre|figure)/i', $part ) ) {
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


    /**
     * Returns the prefixed table name.
     */
    protected function tableName( string $name ): string
    {
        return str_replace( '#__', '', $this->prefix ) . $name;
    }
}
