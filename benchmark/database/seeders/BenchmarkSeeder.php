<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Hashes;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Utils;


class BenchmarkSeeder
{
    /** @var string Language of the translated page variants */
    public const TRANSLATION = 'de';

    private string $tenantId;
    private string $editor;
    private string $domain;
    /** @var int<1, max> */
    private int $chunk;
    private ?\Closure $onProgress = null;
    private int $translated = 0;
    private int $pageCount = 0;


    /**
     * Generate benchmark data for a single language.
     *
     * @param string $domain Domain name
     * @param string $editor Editor name
     * @param int $pages Total number of pages to create
     * @param int $chunk Rows per bulk insert batch
     * @param \Closure|null $onProgress Called with row count after each bulk insert
     */
    public function run( string $domain = '', string $editor = 'benchmark', int $pages = 10000, int $chunk = 75, ?\Closure $onProgress = null ): void
    {
        $this->tenantId = \Aimeos\Cms\Tenancy::value();
        $this->onProgress = $onProgress;
        $this->editor = $editor;
        $this->domain = $domain;
        $this->chunk = max( 1, $chunk );

        $conn = config( 'cms.db', 'sqlite' );

        Page::withoutSyncingToSearch( function() use ( $pages, $conn ) {
            Element::withoutSyncingToSearch( function() use ( $pages, $conn ) {
                File::withoutSyncingToSearch( function() use ( $pages, $conn ) {
                    DB::connection( $conn )->transaction( function() use ( $pages ) {
                        $this->seedAll( $pages );
                    } );
                } );
            } );
        } );

        // outside of the transaction because MySQL commits implicitly
        $this->analyze();
    }


    /**
     * Updates the table statistics like in databases which are in use for some time.
     *
     * Without statistics, bulk inserted data can lead to execution plans no live database
     * uses, e.g. SQLite then sorts all page variants instead of reading the pages in tree order.
     */
    protected function analyze(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $grammar = $db->getQueryGrammar();

        $tables = [
            'cms_pages', 'cms_page_variants', 'cms_versions', 'cms_elements', 'cms_files',
            'cms_page_file', 'cms_page_element', 'cms_version_file', 'cms_version_element',
        ];

        foreach( $tables as $table )
        {
            $name = $grammar->wrapTable( $table );

            match( $db->getDriverName() ) {
                'mysql', 'mariadb' => $db->select( 'ANALYZE TABLE ' . $name ),
                'sqlsrv' => $db->statement( 'UPDATE STATISTICS ' . $name ),
                default => $db->statement( 'ANALYZE ' . $name ),
            };
        }
    }


    /**
     * Seed all data within a single transaction.
     */
    protected function seedAll( int $totalPages ): void
    {
        $now = now()->format( 'Y-m-d H:i:s' );
        $nowMs = now()->format( 'Y-m-d H:i:s.v' );

        $fileCount = max( 2, intdiv( $totalPages, 10 ) );
        $elementCount = max( 2, intdiv( $totalPages, 50 ) );
        $fileIds = $this->createFiles( $fileCount, $now, $nowMs );
        $elementIds = $this->createElements( $elementCount, $now, $nowMs );

        $this->buildPageTree( $totalPages, $fileIds, $elementIds, $now, $nowMs );
    }


    /**
     * Build page tree rows with nested set values, flushing in batches.
     *
     * @param array<int, string> $fileIds
     * @param array<int, string> $elementIds
     */
    protected function buildPageTree( int $totalPages, array $fileIds, array $elementIds, string $now, string $nowMs ): void
    {
        $level2Count = 100; // 10 L1 × 10 L2
        $level3PerL2 = max( 0, intdiv( $totalPages - 1 - 10 - $level2Count, $level2Count ) );
        $actualTotal = 1 + 10 + $level2Count + ( $level3PerL2 * $level2Count );
        $fileCount = count( $fileIds );
        $elementCount = count( $elementIds );

        $pages = [];
        $versions = [];
        $pivotPageFile = [];
        $pivotPageElement = [];
        $pivotVersionFile = [];
        $pivotVersionElement = [];

        $midIndex = (int) floor( $actualTotal / 2 );
        $lft = 1;
        $pageIndex = 0;
        $fileIndex = 0;

        $flush = function() use ( &$pages, &$versions, &$pivotPageFile, &$pivotPageElement, &$pivotVersionFile, &$pivotVersionElement ) {
            $this->insertRows( compact( 'pages', 'versions', 'pivotPageFile', 'pivotPageElement', 'pivotVersionFile', 'pivotVersionElement' ) );
            $pages = $versions = $pivotPageFile = $pivotPageElement = $pivotVersionFile = $pivotVersionElement = [];
        };

        // Root page
        $rootId = ( new Page )->newUniqueId();
        $rootVersionId = ( new Version )->newUniqueId();
        $rootContent = $this->pageContent( $fileIds[$fileIndex % $fileCount], $elementIds[$pageIndex % $elementCount], 0 );
        $rootMeta = $this->metaDescription( 0 );
        $rootData = [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Home',
            'path' => '',
            'tag' => 'root',
            'domain' => $this->domain,
            'status' => 1,
            'editor' => $this->editor,
        ];

        $rootRgt = $lft + ( $actualTotal * 2 ) - 1;

        $pages[] = $this->pageRow( $rootId, null, $rootVersionId, $rootData, $rootContent, $rootMeta, $lft, $rootRgt, 0, $now );
        $versions[] = $this->versionRow( $rootVersionId, $rootId, PageVariant::class, $rootData, $rootContent, $rootMeta, $nowMs );
        $pivotPageFile[] = ['variant_id' => $rootId, 'file_id' => $fileIds[$fileIndex % $fileCount]];
        $pivotPageElement[] = ['variant_id' => $rootId, 'element_id' => $elementIds[$pageIndex % $elementCount]];
        $pivotVersionFile[] = ['version_id' => $rootVersionId, 'file_id' => $fileIds[$fileIndex % $fileCount]];
        $pivotVersionElement[] = ['version_id' => $rootVersionId, 'element_id' => $elementIds[$pageIndex % $elementCount]];

        $lft++;
        $fileIndex++;
        $pageIndex++;

        // Level 1–3 pages
        for( $i = 0; $i < 10; $i++ )
        {
            $l1Id = ( new Page )->newUniqueId();
            $l1VersionId = ( new Version )->newUniqueId();
            $l1Fid = $fileIds[$fileIndex % $fileCount];

            $l1Children = 10 + ( 10 * $level3PerL2 );
            $l1Lft = $lft;
            $l1Rgt = $lft + ( ( $l1Children + 1 ) * 2 ) - 1;

            $l1Data = [
                'lang' => 'en', 'name' => "Category {$i}", 'title' => "Category {$i} Title",
                'path' => "category-{$i}", 'status' => 1, 'editor' => $this->editor,
            ];
            $l1Content = $this->pageContent( $l1Fid, $elementIds[$pageIndex % $elementCount], $pageIndex );
            $l1Meta = $this->metaDescription( $pageIndex );

            $l1Row = $this->pageRow( $l1Id, $rootId, $l1VersionId, $l1Data, $l1Content, $l1Meta, $l1Lft, $l1Rgt, 1, $now );

            if( $pageIndex === $midIndex ) {
                $l1Row['deleted_at'] = $now;
            }

            $pages[] = $l1Row;
            $versions[] = $this->versionRow( $l1VersionId, $l1Id, PageVariant::class, $l1Data, $l1Content, $l1Meta, $nowMs );
            $pivotPageFile[] = ['variant_id' => $l1Id, 'file_id' => $l1Fid];
            $pivotPageElement[] = ['variant_id' => $l1Id, 'element_id' => $elementIds[$pageIndex % $elementCount]];
            $pivotVersionFile[] = ['version_id' => $l1VersionId, 'file_id' => $l1Fid];
            $pivotVersionElement[] = ['version_id' => $l1VersionId, 'element_id' => $elementIds[$pageIndex % $elementCount]];

            $lft++;
            $fileIndex++;
            $pageIndex++;

            for( $j = 0; $j < 10; $j++ )
            {
                $l2Id = ( new Page )->newUniqueId();
                $l2VersionId = ( new Version )->newUniqueId();
                $l2Fid = $fileIds[$fileIndex % $fileCount];

                $l2Lft = $lft;
                $l2Rgt = $lft + ( ( $level3PerL2 + 1 ) * 2 ) - 1;

                $l2Data = [
                    'lang' => 'en', 'name' => "Subcategory {$i}-{$j}", 'title' => "Subcategory {$i}-{$j} Title",
                    'path' => "subcategory-{$i}-{$j}", 'status' => 1, 'editor' => $this->editor,
                ];
                $l2Content = $this->pageContent( $l2Fid, $elementIds[$pageIndex % $elementCount], $pageIndex );
                $l2Meta = $this->metaDescription( $pageIndex );

                $l2Row = $this->pageRow( $l2Id, $l1Id, $l2VersionId, $l2Data, $l2Content, $l2Meta, $l2Lft, $l2Rgt, 2, $now );

                if( $pageIndex === $midIndex ) {
                    $l2Row['deleted_at'] = $now;
                }

                $pages[] = $l2Row;
                $versions[] = $this->versionRow( $l2VersionId, $l2Id, PageVariant::class, $l2Data, $l2Content, $l2Meta, $nowMs );
                $pivotPageFile[] = ['variant_id' => $l2Id, 'file_id' => $l2Fid];
                $pivotPageElement[] = ['variant_id' => $l2Id, 'element_id' => $elementIds[$pageIndex % $elementCount]];
                $pivotVersionFile[] = ['version_id' => $l2VersionId, 'file_id' => $l2Fid];
                $pivotVersionElement[] = ['version_id' => $l2VersionId, 'element_id' => $elementIds[$pageIndex % $elementCount]];

                $lft++;
                $fileIndex++;
                $pageIndex++;

                for( $k = 0; $k < $level3PerL2; $k++ )
                {
                    $l3Id = ( new Page )->newUniqueId();
                    $l3VersionId = ( new Version )->newUniqueId();
                    $l3Fid = $fileIds[$fileIndex % $fileCount];

                    $l3Data = [
                        'lang' => 'en', 'name' => "Page {$i}-{$j}-{$k}", 'title' => "Page {$i}-{$j}-{$k} Title",
                        'path' => "page-{$i}-{$j}-{$k}", 'status' => 1, 'editor' => $this->editor,
                    ];
                    $l3Content = $this->pageContent( $l3Fid, $elementIds[$pageIndex % $elementCount], $pageIndex );
                    $l3Meta = $this->metaDescription( $pageIndex );

                    $l3Row = $this->pageRow( $l3Id, $l2Id, $l3VersionId, $l3Data, $l3Content, $l3Meta, $lft, $lft + 1, 3, $now );

                    if( $pageIndex === $midIndex ) {
                        $l3Row['deleted_at'] = $now;
                    }

                    $pages[] = $l3Row;
                    $versions[] = $this->versionRow( $l3VersionId, $l3Id, PageVariant::class, $l3Data, $l3Content, $l3Meta, $nowMs );
                    $pivotPageFile[] = ['variant_id' => $l3Id, 'file_id' => $l3Fid];
                    $pivotPageElement[] = ['variant_id' => $l3Id, 'element_id' => $elementIds[$pageIndex % $elementCount]];
                    $pivotVersionFile[] = ['version_id' => $l3VersionId, 'file_id' => $l3Fid];
                    $pivotVersionElement[] = ['version_id' => $l3VersionId, 'element_id' => $elementIds[$pageIndex % $elementCount]];

                    $lft += 2;
                    $fileIndex++;
                    $pageIndex++;

                    if( count( $pages ) >= $this->chunk ) {
                        $flush();
                    }
                }

                $lft++;
            }

            $lft++;
        }

        $flush();
    }


    /**
     * Bulk insert page, version, and pivot rows.
     *
     * @param array<string, array<int, array<string, mixed>>> $rows
     */
    protected function insertRows( array $rows ): void
    {
        $conn = config( 'cms.db', 'sqlite' );

        // pages are split into the page structure and the source language variant
        if( !empty( $rows['pages'] ) )
        {
            Resource::insertPages( $rows['pages'] );

            if( $this->onProgress ) {
                ( $this->onProgress )( count( $rows['pages'] ) );
            }
        }

        $rows = $this->translations( $rows );

        $tables = [
            'cms_versions' => $rows['versions'],
            'cms_page_file' => $rows['pivotPageFile'],
            'cms_page_element' => $rows['pivotPageElement'],
            'cms_version_file' => $rows['pivotVersionFile'],
            'cms_version_element' => $rows['pivotVersionElement'],
        ];

        foreach( $tables as $table => $data )
        {
            if( !empty( $data ) )
            {
                DB::connection( $conn )->table( $table )->insert( $data );

                if( $this->onProgress ) {
                    ( $this->onProgress )( count( $data ) );
                }
            }
        }
    }


    /**
     * Adds translated variants, their versions and references for every second page.
     *
     * One of four translations is disabled, so visitors see the source variant of these
     * pages and of all pages without translation while editors see all translations.
     *
     * @param array<string, array<int, array<string, mixed>>> $rows Rows of the source variants
     * @return array<string, array<int, array<string, mixed>>> Rows including the translations
     */
    protected function translations( array $rows ): array
    {
        $lang = self::TRANSLATION;
        $variants = [];
        $versions = array_column( $rows['versions'], null, 'versionable_id' );

        foreach( $rows['pages'] as $page )
        {
            if( $this->pageCount++ % 2 ) {
                continue;
            }

            [, $variant] = Resource::splitPage( $page );

            $sourceId = $page['variant_id'];
            $variantId = ( new PageVariant )->newUniqueId();
            $versionId = ( new Version )->newUniqueId();
            $path = trim( $lang . '/' . $page['path'], '/' );
            $version = $versions[$sourceId];

            $data = array_replace( (array) json_decode( $version['data'], true ), ['lang' => $lang, 'path' => $path] );
            $aux = (array) json_decode( $version['aux'], true );

            $variants[] = array_replace( $variant, [
                'id' => $variantId,
                'page_id' => $page['id'],
                'tenant_id' => $this->tenantId,
                'lang' => $lang,
                'path' => $path,
                'status' => $this->translated++ % 4 === 3 ? 0 : 1,
                'latest_id' => $versionId,
                // up to date translations, so publishing the source checks if they are stale
                'hashes' => json_encode( Hashes::page( $data, $aux['content'] ?? [], $aux['meta'] ?? [], $aux['config'] ?? [] ) ),
            ] );

            $rows['versions'][] = array_replace( $version, [
                'id' => $versionId, 'versionable_id' => $variantId, 'lang' => $lang, 'data' => json_encode( $data ),
            ] );

            foreach( ['pivotPageFile', 'pivotPageElement'] as $key )
            {
                foreach( $rows[$key] as $pivot )
                {
                    if( $pivot['variant_id'] === $sourceId ) {
                        $rows[$key][] = array_replace( $pivot, ['variant_id' => $variantId] );
                    }
                }
            }

            foreach( ['pivotVersionFile', 'pivotVersionElement'] as $key )
            {
                foreach( $rows[$key] as $pivot )
                {
                    if( $pivot['version_id'] === $version['id'] ) {
                        $rows[$key][] = array_replace( $pivot, ['version_id' => $versionId] );
                    }
                }
            }
        }

        foreach( array_chunk( $variants, $this->chunk ) as $chunk )
        {
            PageVariant::withoutGlobalScopes()->toBase()->insert( $chunk );

            if( $this->onProgress ) {
                ( $this->onProgress )( count( $chunk ) );
            }
        }

        return $rows;
    }


    /**
     * Create files in bulk and return their IDs.
     *
     * @return array<int, string>
     */
    protected function createFiles( int $count, string $now, string $nowMs ): array
    {
        $conn = config( 'cms.db', 'sqlite' );
        $imagePath = realpath( __DIR__ . '/../../assets/image.png' );
        $fileRows = [];
        $versionRows = [];
        $ids = [];

        for( $i = 0; $i < $count; $i++ )
        {
            $id = ( new File )->newUniqueId();
            $versionId = ( new Version )->newUniqueId();
            $name = "Benchmark image {$i}";

            $fileRows[] = [
                'id' => $id,
                'tenant_id' => $this->tenantId,
                'mime' => 'image/png',
                'lang' => 'en',
                'name' => $name,
                'path' => $imagePath,
                'previews' => json_encode( ['500' => $imagePath, '1000' => $imagePath] ),
                'description' => '{}',
                'transcription' => '{}',
                'editor' => $this->editor,
                'latest_id' => $versionId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => $i === $count - 1 ? $now : null,
            ];

            $versionRows[] = [
                'id' => $versionId,
                'tenant_id' => $this->tenantId,
                'versionable_id' => $id,
                'versionable_type' => File::class,
                'lang' => 'en',
                'data' => json_encode( [
                    'mime' => 'image/png',
                    'lang' => 'en',
                    'name' => $name,
                    'path' => $imagePath,
                    'previews' => ['500' => $imagePath, '1000' => $imagePath],
                ] ),
                'aux' => '{}',
                'published' => true,
                'editor' => $this->editor,
                'created_at' => $nowMs,
            ];

            $ids[] = $id;
        }

        foreach( array_chunk( $fileRows, $this->chunk ) as $batch )
        {
            DB::connection( $conn )->table( 'cms_files' )->insert( $batch );

            if( $this->onProgress ) {
                ( $this->onProgress )( count( $batch ) );
            }
        }

        foreach( array_chunk( $versionRows, $this->chunk ) as $batch )
        {
            DB::connection( $conn )->table( 'cms_versions' )->insert( $batch );

            if( $this->onProgress ) {
                ( $this->onProgress )( count( $batch ) );
            }
        }

        return $ids;
    }


    /**
     * Create elements in bulk and return their IDs.
     *
     * @return array<int, string>
     */
    protected function createElements( int $count, string $now, string $nowMs ): array
    {
        $conn = config( 'cms.db', 'sqlite' );
        $elementRows = [];
        $versionRows = [];
        $ids = [];

        for( $i = 0; $i < $count; $i++ )
        {
            $id = ( new Element )->newUniqueId();
            $versionId = ( new Version )->newUniqueId();
            $name = "Benchmark element {$i}";
            $text = "Benchmark footer content {$i}";

            $elementRows[] = [
                'id' => $id,
                'tenant_id' => $this->tenantId,
                'type' => 'text',
                'lang' => 'en',
                'name' => $name,
                'data' => json_encode( ['type' => 'text', 'data' => ['text' => $text]] ),
                'editor' => $this->editor,
                'latest_id' => $versionId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => $i === $count - 1 ? $now : null,
            ];

            $versionRows[] = [
                'id' => $versionId,
                'tenant_id' => $this->tenantId,
                'versionable_id' => $id,
                'versionable_type' => Element::class,
                'lang' => 'en',
                'data' => json_encode( [
                    'lang' => 'en',
                    'type' => 'text',
                    'name' => $name,
                    'data' => ['text' => $text],
                ] ),
                'aux' => '{}',
                'published' => true,
                'editor' => $this->editor,
                'created_at' => $nowMs,
            ];

            $ids[] = $id;
        }

        foreach( array_chunk( $elementRows, $this->chunk ) as $batch )
        {
            DB::connection( $conn )->table( 'cms_elements' )->insert( $batch );

            if( $this->onProgress ) {
                ( $this->onProgress )( count( $batch ) );
            }
        }

        foreach( array_chunk( $versionRows, $this->chunk ) as $batch )
        {
            DB::connection( $conn )->table( 'cms_versions' )->insert( $batch );

            if( $this->onProgress ) {
                ( $this->onProgress )( count( $batch ) );
            }
        }

        return $ids;
    }


    /**
     * Build a page row for bulk insert.
     *
     * @param array<string, mixed> $data Page data
     * @param array<int, mixed> $content Content elements
     * @param array<string, mixed> $meta Meta data
     * @return array<string, mixed> Row for bulk insert
     */
    protected function pageRow(
        string $id, ?string $parentId, string $versionId,
        array $data, array $content, array $meta,
        int $lft, int $rgt, int $depth, string $now
    ): array
    {
        return [
            'id' => $id,
            'variant_id' => $id, // versions and pivots reference the variant by the page ID
            'tenant_id' => $this->tenantId,
            'tag' => $data['tag'] ?? '',
            'lang' => 'en',
            'path' => $data['path'],
            'domain' => $data['domain'] ?? $this->domain,
            'to' => '',
            'name' => $data['name'],
            'title' => $data['title'],
            'type' => '',
            'theme' => '',
            'meta' => json_encode( $meta ),
            'config' => '{}',
            'content' => json_encode( $content ),
            'status' => 1,
            'cache' => 5,
            'editor' => $this->editor,
            'parent_id' => $parentId,
            'latest_id' => $versionId,
            '_lft' => $lft,
            '_rgt' => $rgt,
            'depth' => $depth,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }


    /**
     * Build a version row for bulk insert.
     *
     * @param array<string, mixed> $data Version data
     * @param array<int, mixed> $content Content elements
     * @param array<string, mixed> $meta Meta data
     * @return array<string, mixed> Row for bulk insert
     */
    protected function versionRow(
        string $id, string $versionableId, string $versionableType,
        array $data, array $content, array $meta, string $nowMs
    ): array
    {
        return [
            'id' => $id,
            'tenant_id' => $this->tenantId,
            'versionable_id' => $versionableId,
            'versionable_type' => $versionableType,
            'lang' => 'en',
            'data' => json_encode( $data ),
            'aux' => json_encode( ['content' => $content, 'meta' => $meta] ),
            'published' => true,
            'editor' => $this->editor,
            'created_at' => $nowMs,
        ];
    }


    /**
     * Generate content elements for a page.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pageContent( string $fileId, string $elementId, int $index ): array
    {
        return [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => "Lorem ipsum page {$index} heading", 'level' => 1]],
            ['id' => Utils::uid(), 'type' => 'image', 'group' => 'main', 'data' => ['file' => ['id' => $fileId, 'type' => 'file']]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua for page {$index}. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat."]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => "Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur page {$index}. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum."]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => "Benchmark content block for page {$index}. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur."]],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];
    }


    /**
     * Generate meta description for a page.
     *
     * @return array<string, mixed>
     */
    protected function metaDescription( int $index ): array
    {
        return [
            'meta-tags' => [
                'type' => 'meta-tags',
                'data' => ['description' => "Benchmark page {$index} description. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore."],
                'files' => [],
            ],
        ];
    }
}
