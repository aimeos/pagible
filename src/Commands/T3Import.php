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


class T3Import extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:t3-import
        {--connection=typo3 : Database connection name for the TYPO3 database}
        {--domain=* : Domain for imported pages, optionally with root page UID (e.g. --domain=example.com or --domain=example.com:1 --domain=example.de:2)}
        {--lang=en : Language code for the imported pages}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=t3-import : Editor name for imported records}
        {--file-base= : Base URL for TYPO3 files (e.g. https://example.com/fileadmin)}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports TYPO3 pages into Pagible CMS pages';

    protected string $t3Connection;
    protected string $domain;
    protected string $lang;
    protected string $editor;
    protected string $fileBase;
    /** @var Collection<int|string, mixed> */
    protected Collection $sysFiles;
    /** @var Collection<int|string, mixed> */
    protected Collection $fileRefs;
    /** @var Collection<int|string, mixed> */
    protected Collection $createdFiles;
    /** @var array<int, string> */
    protected array $domainMap = [];


    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->t3Connection = (string) $this->option( 'connection' ); // @phpstan-ignore cast.string
        $this->domain = $this->parseDomainOption();
        $this->lang = (string) $this->option( 'lang' ); // @phpstan-ignore cast.string
        $this->editor = (string) $this->option( 'editor' ); // @phpstan-ignore cast.string
        $this->fileBase = rtrim( (string) ($this->option( 'file-base' ) ?: ''), '/' ); // @phpstan-ignore cast.string
        $this->createdFiles = Collection::make();

        $this->setupTenant();

        if( !$this->check() ) {
            return;
        }

        $pages = $this->fetchPages();

        if( $pages->isEmpty() ) {
            $this->warn( 'No TYPO3 pages found.' );
            return;
        }

        $this->info( "Found {$pages->count()} TYPO3 pages." );

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun( $pages );
            return;
        }

        $this->sysFiles = $this->fetchSysFiles();
        $this->fileRefs = $this->fetchFileReferences();
        $contentElements = $this->fetchContentElements();

        $this->importPages( $pages, $contentElements );
    }


    /**
     * Builds content elements array from TYPO3 tt_content records.
     *
     * @param Collection<int|string, mixed> $records
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}
     */
    protected function buildContent( Collection $records ): array
    {
        $elements = [];
        $fileIds = [];

        foreach( $records as $record )
        {
            $result = $this->convertContentElement( $record );

            if( $result ) {
                $elements = array_merge( $elements, $result['elements'] );
                $fileIds = array_merge( $fileIds, $result['fileIds'] ?? [] );
            }
        }

        return ['elements' => $elements, 'fileIds' => array_unique( $fileIds )];
    }


    /**
     * Builds the page data array.
     *
     * @return array<string, mixed>
     */
    protected function buildPageData( object $t3Page, string $slug, string $domain ): array
    {
        return [
            /** @phpstan-ignore property.notFound */
            'name' => $t3Page->title,
            /** @phpstan-ignore property.notFound, property.notFound */
            'title' => $t3Page->seo_title ?: $t3Page->title,
            'path' => $slug,
            'tag' => $t3Page->is_siteroot ? 'root' : 'page', // @phpstan-ignore property.notFound
            'domain' => $domain,
            'lang' => $this->lang,
            'status' => $t3Page->hidden ? 0 : 1, // @phpstan-ignore property.notFound
            'editor' => $this->editor,
        ];
    }


    /**
     * Tests the TYPO3 database connection.
     */
    protected function check(): bool
    {
        try {
            DB::connection( $this->t3Connection )->getPdo();
            return true;
        } catch( \Exception $e ) {
            $this->error( "Cannot connect to TYPO3 database using connection \"{$this->t3Connection}\"." );
            $this->error( "Add a \"{$this->t3Connection}\" connection to config/database.php, e.g.:" );
            $this->line( "  '{$this->t3Connection}' => [" );
            $this->line( "      'driver' => 'mysql'," );
            $this->line( "      'host' => env('T3_DB_HOST', '127.0.0.1')," );
            $this->line( "      'database' => env('T3_DB_DATABASE', 'typo3')," );
            $this->line( "      'username' => env('T3_DB_USERNAME', 'root')," );
            $this->line( "      'password' => env('T3_DB_PASSWORD', '')," );
            $this->line( "  ]" );
            return false;
        }
    }


    /**
     * Converts a TYPO3 tt_content record into Pagible content elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: string[]}|null
     */
    protected function convertContentElement( object $record ): ?array
    {
        return match( $record->CType ) { // @phpstan-ignore property.notFound
            'header' => $this->convertHeader( $record ),
            'text' => $this->convertText( $record ),
            'textpic', 'textmedia' => $this->convertTextpic( $record ),
            'image' => $this->convertImage( $record ),
            'html' => $this->convertHtml( $record ),
            'accordion' => $this->convertAccordion( $record ),
            default => $this->convertDefault( $record ),
        };
    }


    /**
     * Converts an accordion content element into a questions element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function convertAccordion( object $record ): ?array
    {
        $items = [];

        if( !empty( $record->header ) ) {
            $title = $record->header;
        } else {
            $title = '';
        }

        if( !empty( $record->bodytext ) ) {
            $items[] = [
                'title' => $record->header ?: 'Item', // @phpstan-ignore property.notFound
                'text' => $this->htmlToMarkdown( $record->bodytext ),
            ];
        }

        if( empty( $items ) ) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'questions',
            'group' => 'main',
            'data' => [
                'title' => $title,
                'items' => $items,
            ],
        ]]];
    }


    /**
     * Converts a default/unknown CType with bodytext into an html element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function convertDefault( object $record ): ?array
    {
        $elements = [];

        if( !empty( $record->header ) && ( $record->header_layout ?? '0' ) !== '100' ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'heading',
                'group' => 'main',
                'data' => [
                    'level' => $this->headerLevel( $record->header_layout ), // @phpstan-ignore property.notFound
                    'title' => $record->header,
                ],
            ];
        }

        if( !empty( $record->bodytext ) ) {
            $text = trim( $record->bodytext );
            if( !empty( $text ) ) {
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'html',
                    'group' => 'main',
                    'data' => ['text' => $text],
                ];
            }
        }

        if( empty( $elements ) ) {
            return null;
        }

        return ['elements' => $elements];
    }


    /**
     * Converts a header content element into a heading element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function convertHeader( object $record ): ?array
    {
        if( empty( $record->header ) ) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'heading',
            'group' => 'main',
            'data' => [
                'level' => $this->headerLevel( $record->header_layout ), // @phpstan-ignore property.notFound
                'title' => $record->header,
            ],
        ]]];
    }


    /**
     * Converts an html content element into a Pagible html element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function convertHtml( object $record ): ?array
    {
        if( empty( $record->bodytext ) ) {
            return null;
        }

        return ['elements' => [[
            'id' => Utils::uid(),
            'type' => 'html',
            'group' => 'main',
            'data' => ['text' => $record->bodytext],
        ]]];
    }


    /**
     * Converts an image content element into a Pagible image element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertImage( object $record ): ?array
    {
        $fileId = $this->importFileForContent( $record->uid ); // @phpstan-ignore property.notFound

        if( !$fileId ) {
            return null;
        }

        $elements = [];

        if( !empty( $record->header ) && ( $record->header_layout ?? '0' ) !== '100' ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'heading',
                'group' => 'main',
                'data' => [
                    'level' => $this->headerLevel( $record->header_layout ), // @phpstan-ignore property.notFound
                    'title' => $record->header,
                ],
            ];
        }

        $elements[] = [
            'id' => Utils::uid(),
            'type' => 'image',
            'group' => 'main',
            'data' => ['file' => ['id' => $fileId, 'type' => 'file']],
        ];

        return ['elements' => $elements, 'fileIds' => [$fileId]];
    }


    /**
     * Converts a text content element into heading + html elements.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function convertText( object $record ): ?array
    {
        $elements = [];

        if( !empty( $record->header ) && ( $record->header_layout ?? '0' ) !== '100' ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'heading',
                'group' => 'main',
                'data' => [
                    'level' => $this->headerLevel( $record->header_layout ), // @phpstan-ignore property.notFound
                    'title' => $record->header,
                ],
            ];
        }

        if( !empty( $record->bodytext ) ) {
            $text = trim( $record->bodytext );
            if( !empty( $text ) ) {
                $elements[] = [
                    'id' => Utils::uid(),
                    'type' => 'html',
                    'group' => 'main',
                    'data' => ['text' => $text],
                ];
            }
        }

        if( empty( $elements ) ) {
            return null;
        }

        return ['elements' => $elements];
    }


    /**
     * Converts a textpic/textmedia content element into an image-text element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: string[]}|null
     */
    protected function convertTextpic( object $record ): ?array
    {
        $elements = [];
        $fileIds = [];

        if( !empty( $record->header ) && ( $record->header_layout ?? '0' ) !== '100' ) {
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'heading',
                'group' => 'main',
                'data' => [
                    'level' => $this->headerLevel( $record->header_layout ), // @phpstan-ignore property.notFound
                    'title' => $record->header,
                ],
            ];
        }

        $fileId = $this->importFileForContent( $record->uid ); // @phpstan-ignore property.notFound
        $text = !empty( $record->bodytext ) ? trim( $record->bodytext ) : '';

        if( $fileId && !empty( $text ) ) {
            $position = $this->imagePosition( $record->imageorient ?? 0 );
            $elements[] = [
                'id' => Utils::uid(),
                'type' => 'image-text',
                'group' => 'main',
                'data' => [
                    'text' => $text,
                    'file' => ['id' => $fileId, 'type' => 'file'],
                    'position' => $position,
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

        if( empty( $elements ) ) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => $fileIds];
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
     * Creates a Pagible page with content, version, and search index.
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
     * @param string[] $fileIds
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
     * Fetches active tt_content records grouped by page ID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchContentElements(): Collection
    {
        return DB::connection( $this->t3Connection )
            ->table( 'tt_content' )
            ->where( 'deleted', 0 )
            ->where( 'hidden', 0 )
            ->where( 'sys_language_uid', 0 )
            ->orderBy( 'sorting', 'asc' )
            ->get()
            ->groupBy( 'pid' );
    }


    /**
     * Fetches sys_file_reference records grouped by content element UID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchFileReferences(): Collection
    {
        return DB::connection( $this->t3Connection )
            ->table( 'sys_file_reference' )
            ->where( 'deleted', 0 )
            ->where( 'hidden', 0 )
            ->where( 'tablenames', 'tt_content' )
            ->whereIn( 'fieldname', ['image', 'media', 'assets'] )
            ->orderBy( 'sorting_foreign', 'asc' )
            ->get()
            ->groupBy( 'uid_foreign' );
    }


    /**
     * Fetches non-deleted TYPO3 pages in default language.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchPages(): Collection
    {
        /** @var Collection<int|string, mixed> */
        return DB::connection( $this->t3Connection )
            ->table( 'pages' )
            ->where( 'deleted', 0 )
            ->where( 'sys_language_uid', 0 )
            ->where( 't3ver_wsid', 0 )
            ->whereIn( 'doktype', [1, 4] )
            ->orderBy( 'sorting', 'asc' )
            ->get();
    }


    /**
     * Fetches sys_file records keyed by UID.
     *
     * @return Collection<int|string, mixed>
     */
    protected function fetchSysFiles(): Collection
    {
        return DB::connection( $this->t3Connection )
            ->table( 'sys_file' )
            ->get()
            ->keyBy( 'uid' );
    }


    /**
     * Gets or creates the root page for a domain.
     *
     * @param Collection<int|string, mixed> $contentElements
     */
    protected function getRootPage( object $t3Root, string $domain, Collection $contentElements ): Page
    {
        $page = Page::where( 'tag', 'root' )->where( 'domain', $domain )->first();

        if( $page ) {
            $this->info( "Using existing root page: {$page->name} ({$domain})" );
            return $page;
        }

        $slug = $this->slugFromPath( $t3Root->slug ); // @phpstan-ignore property.notFound
        $pageData = $this->buildPageData( $t3Root, $slug, $domain );
        $pageData['tag'] = 'root';
        $records = $contentElements->get( $t3Root->uid, Collection::make() ); // @phpstan-ignore property.notFound
        $content = $this->buildContent( $records );

        $page = Page::forceCreate( $pageData + ['content' => $content['elements']] );

        $this->createVersion( $page, $pageData, $content['elements'], $content['fileIds'] );

        $this->info( "Created root page: {$t3Root->title} ({$domain})" ); // @phpstan-ignore property.notFound
        return $page;
    }


    /**
     * Maps TYPO3 header_layout to heading level.
     */
    protected function headerLevel( ?string $layout ): int
    {
        return match( $layout ) {
            '1' => 1,
            '2' => 2,
            '3' => 3,
            '4' => 4,
            '5' => 5,
            default => 2,
        };
    }


    /**
     * Converts basic HTML to Markdown.
     */
    protected function htmlToMarkdown( string $html ): string
    {
        $text = $html;

        $text = (string) preg_replace( '/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/is', "\n" . '$2' . "\n", $text );
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
     * Maps TYPO3 imageorient to Pagible position.
     */
    protected function imagePosition( int $orient ): string
    {
        return match( $orient ) {
            0, 1, 2 => 'auto',
            17, 18 => 'start',
            25, 26 => 'end',
            default => 'auto',
        };
    }


    /**
     * Imports the first file reference for a tt_content record.
     */
    protected function importFileForContent( int $contentUid ): ?string
    {
        $refs = $this->fileRefs->get( $contentUid );

        if( !$refs || $refs->isEmpty() ) {
            return null;
        }

        $ref = $refs->first();
        $sysFile = $this->sysFiles->get( $ref->uid_local );

        if( !$sysFile ) {
            return null;
        }

        $name = $ref->title ?: $ref->alternative ?: $sysFile->name;
        $mime = $sysFile->mime_type ?: $this->guessMimeFromExtension( $sysFile->extension );
        $path = $this->resolveFilePath( $sysFile->identifier );

        return $this->createFile( $mime, $name, $path );
    }


    /**
     * Imports all pages recursively following the TYPO3 page hierarchy.
     *
     * @param Collection<int|string, mixed> $pages
     * @param Collection<int|string, mixed> $contentElements
     */
    protected function importPages( Collection $pages, Collection $contentElements ): void
    {
        $pageMap = $pages->groupBy( 'pid' );
        $createdPages = Collection::make();
        $imported = 0;

        $importChildren = function( int $parentUid, Page $parentPage, string $domain ) use ( &$importChildren, $pageMap, $contentElements, &$createdPages, &$imported )
        {
            $children = $pageMap->get( $parentUid, Collection::make() );

            foreach( $children as $t3Page )
            {
                if( $t3Page->doktype == 4 ) {
                    continue;
                }

                try {
                    DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $t3Page, $parentPage, $domain, $contentElements, &$createdPages, &$imported )
                    {
                        $slug = $this->slugFromPath( $t3Page->slug );
                        $pageData = $this->buildPageData( $t3Page, $slug, $domain );
                        $records = $contentElements->get( $t3Page->uid, Collection::make() );
                        $content = $this->buildContent( $records );

                        $page = $this->createPage( $pageData, $content['elements'], $parentPage );
                        $this->createVersion( $page, $pageData, $content['elements'], $content['fileIds'] );

                        if( $t3Page->crdate > 0 ) {
                            $page->update( ['created_at' => date( 'Y-m-d H:i:s', $t3Page->crdate )] );
                        }

                        $createdPages->put( $t3Page->uid, $page );
                        $imported++;
                        $this->info( "  Imported: {$t3Page->title} (/{$slug}) [{$domain}]" );
                    } );
                } catch( \Exception $e ) {
                    $this->error( "  Failed to import [{$t3Page->uid}] {$t3Page->title}: " . $e->getMessage() );
                    continue;
                }

                if( $createdPages->has( $t3Page->uid ) ) {
                    /** @var Page $childParent */
                    $childParent = $createdPages->get( $t3Page->uid );
                    $importChildren( $t3Page->uid, $childParent, $domain );
                }
            }
        };

        $rootPages = $pageMap->get( 0, Collection::make() );

        foreach( $rootPages as $t3Root )
        {
            $domain = $this->domainMap[$t3Root->uid] ?? $this->domain;
            $root = $this->getRootPage( $t3Root, $domain, $contentElements );
            $this->info( "Importing tree: {$t3Root->title} ({$domain})" );
            $importChildren( $t3Root->uid, $root, $domain );
        }

        $this->info( "Import complete. {$imported} pages imported." );
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
     * Prints a dry run summary of page hierarchy.
     *
     * @param Collection<int|string, mixed> $pages
     */
    protected function printDryRun( Collection $pages ): void
    {
        $pageMap = $pages->groupBy( 'pid' );

        $printTree = function( int $pid, int $depth ) use ( &$printTree, $pageMap )
        {
            $children = $pageMap->get( $pid, Collection::make() );

            foreach( $children as $page )
            {
                $indent = str_repeat( '  ', $depth );
                $type = $page->doktype == 4 ? ' [shortcut]' : '';
                $hidden = $page->hidden ? ' [hidden]' : '';
                $this->line( "{$indent}[{$page->uid}] {$page->title} ({$page->slug}){$type}{$hidden}" );
                $printTree( $page->uid, $depth + 1 );
            }
        };

        $printTree( 0, 0 );
        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Resolves a TYPO3 file identifier to a full URL or path.
     */
    protected function resolveFilePath( string $identifier ): string
    {
        $identifier = ltrim( $identifier, '/' );

        if( $this->fileBase ) {
            return $this->fileBase . '/' . $identifier;
        }

        return $identifier;
    }


    /**
     * Parses --domain options into a default domain and UID-to-domain map.
     *
     * Accepts plain domains (e.g. --domain=example.com) as default,
     * or domains with root page UID (e.g. --domain=example.com:1) for specific trees.
     */
    protected function parseDomainOption(): string
    {
        $default = '';

        foreach( (array) $this->option( 'domain' ) as $entry )
        {
            $parts = explode( ':', (string) $entry, 2 );

            if( !empty( $parts[1] ) ) {
                $this->domainMap[(int) $parts[1]] = $parts[0];
            } else {
                $default = $parts[0];
            }
        }

        return $default;
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
     * Extracts a clean slug from a TYPO3 page slug/path.
     */
    protected function slugFromPath( ?string $path ): string
    {
        return trim( $path ?? '', '/' );
    }
}
