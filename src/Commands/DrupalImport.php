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


class DrupalImport extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:drupal-import
        {--connection=drupal : Database connection name for the Drupal database}
        {--domain= : Domain name for the imported pages}
        {--lang=en : Language code for the imported pages}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=drupal-import : Editor name for imported records}
        {--file-base= : Base URL for Drupal files (e.g. https://example.com/sites/default/files)}
        {--types=page,article : Comma-separated list of Drupal content types to import}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports Drupal nodes into Pagible CMS pages';

    protected string $drupalConnection;
    protected string $domain;
    protected string $lang;
    protected string $editor;
    protected string $fileBase;
    /** @var Collection<int|string, mixed> */
    protected Collection $files;
    /** @var Collection<int|string, mixed> */
    protected Collection $createdFiles;


    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->drupalConnection = is_string( $opt = $this->option( 'connection' ) ) ? $opt : 'drupal';
        $this->domain = is_string( $opt = $this->option( 'domain' ) ) ? $opt : '';
        $this->lang = is_string( $opt = $this->option( 'lang' ) ) ? $opt : 'en';
        $this->editor = is_string( $opt = $this->option( 'editor' ) ) ? $opt : 'drupal-import';
        $this->fileBase = rtrim( is_string( $opt = $this->option( 'file-base' ) ) ? $opt : '', '/' );
        $this->createdFiles = Collection::make();

        $this->setupTenant();

        if( !$this->check() ) {
            return;
        }

        $nodes = $this->fetchNodes();

        if( $nodes->isEmpty() ) {
            $this->warn( 'No Drupal nodes found.' );
            return;
        }

        $this->info( "Found {$nodes->count()} Drupal nodes." );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun( $nodes );
            return;
        }

        $this->files = $this->fetchFiles();

        $bodies = $this->fetchBodies();
        $aliases = $this->fetchAliases();
        $images = $this->fetchFieldImages();
        $root = $this->getOrCreateRoot();
        $menuTree = $this->fetchMenuTree();

        $this->importNodes( $nodes, $bodies, $aliases, $images, $menuTree, $root );
    }


    /**
     * Builds content elements from body HTML and an optional image.
     *
     * @param object{body_value?: string}|null $body
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<string>}
     */
    protected function buildContent( ?object $body, ?string $imageFileId ): array
    {
        $elements = [];
        $fileIds = [];

        if( $imageFileId ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image',
                'group' => 'main',
                'data' => ['file' => ['id' => $imageFileId, 'type' => 'file']],
            ];
            $fileIds[] = $imageFileId;
        }

        if( $body && !empty( $body->body_value ) ) {
            $parsed = $this->parseBody( $body->body_value );
            $elements = array_merge( $elements, $parsed['elements'] );
            $fileIds = array_merge( $fileIds, $parsed['fileIds'] );
        }

        return ['elements' => $elements, 'fileIds' => array_unique( $fileIds )];
    }


    /**
     * Builds the page data array.
     *
     * @param object{title: string, status: int} $node
     * @return array<string, mixed>
     */
    protected function buildPageData( object $node, string $slug ): array
    {
        return [
            'name' => $node->title,
            'title' => $node->title,
            'path' => $slug,
            'tag' => 'page',
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => (int) $node->status,
            'editor' => $this->editor,
        ];
    }


    /**
     * Tests the Drupal database connection.
     */
    protected function check(): bool
    {
        try {
            DB::connection( $this->drupalConnection )->getPdo();
            return true;
        } catch( \Exception $e ) {
            $this->error( "Cannot connect to Drupal database using connection \"{$this->drupalConnection}\"." );
            $this->error( "Add a \"{$this->drupalConnection}\" connection to config/database.php, e.g.:" );
            $this->line( "  '{$this->drupalConnection}' => [" );
            $this->line( "      'driver' => 'mysql'," );
            $this->line( "      'host' => env('DRUPAL_DB_HOST', '127.0.0.1')," );
            $this->line( "      'database' => env('DRUPAL_DB_DATABASE', 'drupal')," );
            $this->line( "      'username' => env('DRUPAL_DB_USERNAME', 'root')," );
            $this->line( "      'password' => env('DRUPAL_DB_PASSWORD', '')," );
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
     * @param array<string> $fileIds
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
     * Fetches URL aliases keyed by node path (e.g. "/node/42").
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchAliases(): Collection
    {
        $conn = DB::connection( $this->drupalConnection );

        if( $this->tableExists( 'path_alias' ) ) {
            return $conn->table( 'path_alias' )
                ->where( 'status', 1 )
                ->where( 'path', 'like', '/node/%' )
                ->get()
                ->keyBy( 'path' );
        }

        if( $this->tableExists( 'url_alias' ) ) {
            return $conn->table( 'url_alias' )
                ->where( 'source', 'like', 'node/%' )
                ->get()
                ->map( function( \stdClass $row ) {
                    $row->path = '/' . ltrim( $row->source, '/' );
                    return $row;
                } )
                ->keyBy( 'path' );
        }

        return Collection::make();
    }


    /**
     * Fetches node body fields keyed by entity_id.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchBodies(): Collection
    {
        if( !$this->tableExists( 'node__body' ) ) {
            return Collection::make();
        }

        return DB::connection( $this->drupalConnection )
            ->table( 'node__body' )
            ->where( 'deleted', 0 )
            ->get()
            ->keyBy( 'entity_id' );
    }


    /**
     * Fetches image field references keyed by entity_id.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchFieldImages(): Collection
    {
        $conn = DB::connection( $this->drupalConnection );

        if( $this->tableExists( 'node__field_image' ) ) {
            return $conn->table( 'node__field_image' )
                ->where( 'deleted', 0 )
                ->where( 'delta', 0 )
                ->get()
                ->keyBy( 'entity_id' );
        }

        return Collection::make();
    }


    /**
     * Fetches file_managed records keyed by fid.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchFiles(): Collection
    {
        return DB::connection( $this->drupalConnection )
            ->table( 'file_managed' )
            ->where( 'status', 1 )
            ->get()
            ->keyBy( 'fid' );
    }


    /**
     * Fetches the main menu tree for hierarchical import.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchMenuTree(): Collection
    {
        if( !$this->tableExists( 'menu_link_content_data' ) ) {
            return Collection::make();
        }

        return DB::connection( $this->drupalConnection ) // @phpstan-ignore return.type
            ->table( 'menu_link_content_data' )
            ->where( 'menu_name', 'main' )
            ->where( 'enabled', 1 )
            ->orderBy( 'weight', 'asc' )
            ->get();
    }


    /**
     * Fetches published Drupal nodes of the configured content types.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchNodes(): Collection
    {
        $opt = $this->option( 'types' );
        $types = array_map( 'trim', explode( ',', is_string( $opt ) ? $opt : 'page,article' ) );

        return DB::connection( $this->drupalConnection ) // @phpstan-ignore return.type
            ->table( 'node_field_data' )
            ->where( 'default_langcode', 1 )
            ->where( 'status', 1 )
            ->whereIn( 'type', $types )
            ->orderBy( 'created', 'asc' )
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
     * Imports a Drupal file_managed record into a Pagible File.
     */
    protected function importDrupalFile( int $fid, string $alt = '' ): ?string
    {
        $file = $this->files->get( $fid );

        if( !$file ) {
            return null;
        }

        /** @var object{filename: string, filemime: string, uri: string} $file */
        $name = $alt ?: $file->filename;
        $mime = $file->filemime ?: $this->guessMimeFromExtension( pathinfo( $file->filename, PATHINFO_EXTENSION ) );
        $path = $this->resolveFileUri( $file->uri );

        return $this->createFile( $mime, $name, $path );
    }


    /**
     * Imports an image referenced inline in body HTML.
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

        $path = $this->resolveInlineImageSrc( $src );

        return $this->createFile( $mime, $name, $path );
    }


    /**
     * Imports a single Drupal node as a Pagible page.
     *
     * @param object{title: string, status: int, nid: int, created: int, type: string} $node
     * @param object{field_image_alt?: string, field_image_target_id: int}|null $image
     */
    protected function importNode( object $node, ?object $body, ?object $image, string $slug, Page $parent ): Page
    {
        $imageFileId = null;

        if( $image ) {
            $alt = $image->field_image_alt ?? '';
            $imageFileId = $this->importDrupalFile( $image->field_image_target_id, $alt );
        }

        $content = $this->buildContent( $body, $imageFileId );
        $pageData = $this->buildPageData( $node, $slug );

        $page = $this->createPage( $pageData, $content['elements'], $parent );
        $this->createVersion( $page, $pageData, $content['elements'], $content['fileIds'] );

        if( $node->created > 0 ) {
            $page->update( ['created_at' => date( 'Y-m-d H:i:s', $node->created )] );
        }

        return $page;
    }


    /**
     * Imports all nodes, using menu hierarchy when available.
     *
     * @param Collection<int|string, mixed> $nodes
     * @param Collection<int|string, mixed> $bodies
     * @param Collection<int|string, mixed> $aliases
     * @param Collection<int|string, mixed> $images
     * @param Collection<int|string, mixed> $menuTree
     */
    protected function importNodes( Collection $nodes, Collection $bodies, Collection $aliases, Collection $images, Collection $menuTree, Page $root ): void
    {
        $imported = 0;
        $nodesById = $nodes->keyBy( 'nid' );
        $menuByNid = $this->buildMenuMap( $menuTree );
        $createdPages = Collection::make();

        $importNode = function( int $nid, Page $parent ) use ( &$importNode, $nodesById, $bodies, $aliases, $images, $menuByNid, &$createdPages, &$imported )
        {
            $node = $nodesById->get( $nid );

            if( !$node || $createdPages->has( $nid ) ) {
                return;
            }

            /** @var object{nid: int, title: string, status: int, created: int, type: string} $node */

            try {
                DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $node, $parent, $bodies, $aliases, $images, &$createdPages, &$imported )
                {
                    $body = $bodies->get( $node->nid );
                    $image = $images->get( $node->nid );
                    $slug = $this->slugForNode( $node, $aliases );

                    $page = $this->importNode( $node, $body, $image, $slug, $parent );

                    $createdPages->put( $node->nid, $page );
                    $imported++;
                    $this->info( "  Imported: {$node->title} (/{$slug})" );
                } );
            } catch( \Exception $e ) {
                $this->error( "  Failed to import [{$node->nid}] {$node->title}: " . $e->getMessage() );
                return;
            }

            $children = $menuByNid->get( 'children:' . $nid, Collection::make() );
            /** @var Page $parentPage */
            $parentPage = $createdPages->get( $nid );
            foreach( $children as $childNid ) {
                $importNode( $childNid, $parentPage );
            }
        };

        $menuRoots = $menuByNid->get( 'roots', Collection::make() );

        if( $menuRoots->isNotEmpty() ) {
            foreach( $menuRoots as $nid ) {
                $importNode( $nid, $root );
            }
        }

        foreach( $nodesById as $nid => $node ) {
            if( !$createdPages->has( $nid ) ) {
                $importNode( $nid, $root );
            }
        }

        $this->info( "Import complete. {$imported}/{$nodes->count()} nodes imported." );
    }


    /**
     * Builds a menu map with root nodes and parent-children relationships.
     *
     * @param Collection<int|string, mixed> $menuTree
     * @return Collection<int|string, mixed>
     */
    protected function buildMenuMap( Collection $menuTree ): Collection
    {
        $map = Collection::make();
        $roots = Collection::make();

        foreach( $menuTree as $link )
        {
            /** @var object{parent?: string, uuid: string, link__uri?: string} $link */
            $nid = $this->nodeIdFromLink( $link );

            if( !$nid ) {
                continue;
            }

            $parentNid = null;
            if( !empty( $link->parent ) ) {
                $parentLink = $menuTree->first( fn( $l ) => 'menu_link_content:' . $l->uuid === $link->parent );
                if( $parentLink ) {
                    $parentNid = $this->nodeIdFromLink( $parentLink );
                }
            }

            if( $parentNid ) {
                $key = 'children:' . $parentNid;
                $children = $map->get( $key, Collection::make() );
                $children->push( $nid );
                $map->put( $key, $children );
            } else {
                $roots->push( $nid );
            }
        }

        $map->put( 'roots', $roots );

        return $map;
    }


    /**
     * Extracts a node ID from a menu link's link__uri field.
     *
     * @param object{link__uri?: string} $link
     */
    protected function nodeIdFromLink( object $link ): ?int
    {
        $uri = $link->link__uri ?? '';

        if( preg_match( '/(?:entity:node\/|internal:\/node\/)(\d+)/', $uri, $m ) ) {
            return (int) $m[1];
        }

        return null;
    }


    /**
     * Parses Drupal body HTML into Pagible content elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<string>}
     */
    protected function parseBody( string $html ): array
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
     * Parses a <pre> or <code> block into a code element.
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
     * Parses a text block containing an inline image into an image-text element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<string>}
     */
    protected function parseImageTextBlock( string $html ): array
    {
        $elements = [];
        $fileIds = [];

        if( !preg_match( '/<(?:a[^>]*>\s*)?<img[^>]+>(?:\s*<\/a>)?/i', $html, $imgMatch ) ) {
            $result = $this->parseTextBlock( $html );
            return ['elements' => $result['elements'] ?? [], 'fileIds' => []];
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
     * Prints a dry run summary.
     *
     * @param Collection<int|string, mixed> $nodes
     */
    protected function printDryRun( Collection $nodes ): void
    {
        foreach( $nodes as $node ) {
            /** @var object{type: string, status: int, nid: int, title: string} $node */
            $type = $node->type ?? 'unknown';
            $status = $node->status ? '' : ' [unpublished]';
            $this->line( "  [{$node->nid}] ({$type}) {$node->title}{$status}" );
        }
        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Resolves a Drupal file URI (public://path) to a full URL or path.
     */
    protected function resolveFileUri( string $uri ): string
    {
        $path = (string) preg_replace( '/^public:\/\//', '', $uri );
        $path = (string) preg_replace( '/^private:\/\//', '', $path );

        if( $this->fileBase ) {
            return $this->fileBase . '/' . $path;
        }

        return $path;
    }


    /**
     * Resolves an inline image src to a full URL.
     */
    protected function resolveInlineImageSrc( string $src ): string
    {
        if( filter_var( $src, FILTER_VALIDATE_URL ) ) {
            return $src;
        }

        if( $this->fileBase && str_starts_with( $src, '/sites/' ) ) {
            $baseParts = parse_url( $this->fileBase );
            $scheme = $baseParts['scheme'] ?? 'https';
            $host = $baseParts['host'] ?? '';
            return $scheme . '://' . $host . $src;
        }

        if( $this->fileBase ) {
            return $this->fileBase . '/' . ltrim( $src, '/' );
        }

        return ltrim( $src, '/' );
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
     * Generates a slug for a Drupal node from its URL alias or title.
     *
     * @param object{nid: int, title: string} $node
     * @param Collection<int|string, mixed> $aliases
     */
    protected function slugForNode( object $node, Collection $aliases ): string
    {
        $aliasKey = '/node/' . $node->nid;
        $alias = $aliases->get( $aliasKey );

        if( $alias ) {
            /** @var object{alias?: string} $alias */
            $path = trim( $alias->alias ?? '', '/' );
            $parts = explode( '/', $path );
            return end( $parts );
        }

        return Utils::slugify( $node->title );
    }


    /**
     * Splits HTML content into logical blocks.
     *
     * @return string[]
     */
    protected function splitIntoBlocks( string $html ): array
    {
        $pattern = '/(<h[1-6][^>]*>.*?<\/h[1-6]>|<pre[^>]*>.*?<\/pre>|<figure[^>]*>.*?<\/figure>|<drupal-media[^>]*>.*?<\/drupal-media>)/is';
        $parts = preg_split( $pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

        $blocks = [];

        foreach( $parts as $part )
        {
            $part = trim( $part );
            if( empty( $part ) ) {
                continue;
            }

            if( preg_match( '/^<(h[1-6]|pre|figure|drupal-media)/i', $part ) ) {
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
     * Checks if a table exists in the Drupal database.
     */
    protected function tableExists( string $table ): bool
    {
        return DB::connection( $this->drupalConnection )
            ->getSchemaBuilder()
            ->hasTable( $table );
    }
}
