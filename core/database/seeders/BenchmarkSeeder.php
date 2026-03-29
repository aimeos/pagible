<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Database\Seeders;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Utils;


class BenchmarkSeeder
{
    private string $tenantId;
    private string $editor;
    private string $domain;
    private int $chunk;
    private ?\Closure $onProgress = null;


    /**
     * Generate benchmark data for a single language.
     *
     * @param string $lang Language code
     * @param string $domain Domain name
     * @param string $editor Editor name
     * @param int $pages Total number of pages to create
     * @param int $chunk Rows per bulk insert batch
     * @param \Closure|null $onProgress Called with row count after each bulk insert
     */
    public function run( string $lang, string $domain = '', string $editor = 'benchmark', int $pages = 10000, int $chunk = 500, ?\Closure $onProgress = null ): void
    {
        $this->tenantId = \Aimeos\Cms\Tenancy::value();
        $this->onProgress = $onProgress;
        $this->editor = $editor;
        $this->domain = $domain;
        $this->chunk = $chunk;

        $conn = config( 'cms.db', 'sqlite' );

        Page::withoutSyncingToSearch( function() use ( $lang, $pages, $conn ) {
            Element::withoutSyncingToSearch( function() use ( $lang, $pages, $conn ) {
                File::withoutSyncingToSearch( function() use ( $lang, $pages, $conn ) {
                    DB::connection( $conn )->transaction( function() use ( $lang, $pages ) {
                        $this->seedAll( $lang, $pages );
                    } );
                } );
            } );
        } );
    }


    /**
     * Seed all data within a single transaction.
     */
    protected function seedAll( string $lang, int $totalPages ): void
    {
        $now = now()->format( 'Y-m-d H:i:s' );
        $nowMs = now()->format( 'Y-m-d H:i:s.v' );

        $fileCount = max( 1, intdiv( $totalPages, 10 ) );
        $fileIds = $this->createFiles( $lang, $fileCount, $now, $nowMs );
        $elementId = $this->createElement( $lang, $now, $nowMs );

        $rows = $this->buildPageTree( $lang, $totalPages, $fileIds, $elementId, $now, $nowMs );

        $this->insertRows( $rows );
        $this->clearPageCache( $rows['pages'] );
    }


    /**
     * Build page tree rows with nested set values.
     *
     * @param array<int, string> $fileIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function buildPageTree( string $lang, int $totalPages, array $fileIds, string $elementId, string $now, string $nowMs ): array
    {
        $level2Count = 100; // 10 L1 × 10 L2
        $level3PerL2 = max( 0, intdiv( $totalPages - 1 - 10 - $level2Count, $level2Count ) );
        $actualTotal = 1 + 10 + $level2Count + ( $level3PerL2 * $level2Count );
        $fileCount = count( $fileIds );

        $pages = [];
        $versions = [];
        $pivotPageFile = [];
        $pivotPageElement = [];
        $pivotVersionFile = [];
        $pivotVersionElement = [];

        $lft = 1;
        $pageIndex = 0;
        $fileIndex = 0;

        // Root page
        $rootId = ( new Page )->newUniqueId();
        $rootVersionId = ( new Version )->newUniqueId();
        $rootContent = $this->pageContent( $fileIds[$fileIndex % $fileCount], $elementId, 0 );
        $rootMeta = $this->metaDescription( 0 );
        $rootData = [
            'lang' => $lang,
            'name' => "Home ({$lang})",
            'title' => "Home ({$lang})",
            'path' => '',
            'tag' => 'root',
            'domain' => $this->domain,
            'status' => 1,
            'editor' => $this->editor,
        ];

        $rootRgt = $lft + ( $actualTotal * 2 ) - 1;

        $pages[] = $this->pageRow( $rootId, null, $rootVersionId, $lang, $rootData, $rootContent, $rootMeta, $lft, $rootRgt, 0, $now );
        $versions[] = $this->versionRow( $rootVersionId, $rootId, Page::class, $lang, $rootData, $rootContent, $rootMeta, $nowMs );
        $pivotPageFile[] = ['page_id' => $rootId, 'file_id' => $fileIds[$fileIndex % $fileCount]];
        $pivotPageElement[] = ['page_id' => $rootId, 'element_id' => $elementId];
        $pivotVersionFile[] = ['version_id' => $rootVersionId, 'file_id' => $fileIds[$fileIndex % $fileCount]];
        $pivotVersionElement[] = ['version_id' => $rootVersionId, 'element_id' => $elementId];

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
                'lang' => $lang, 'name' => "Category {$i}", 'title' => "Category {$i} Title",
                'path' => "category-{$i}", 'status' => 1, 'editor' => $this->editor,
            ];
            $l1Content = $this->pageContent( $l1Fid, $elementId, $pageIndex );
            $l1Meta = $this->metaDescription( $pageIndex );

            $pages[] = $this->pageRow( $l1Id, $rootId, $l1VersionId, $lang, $l1Data, $l1Content, $l1Meta, $l1Lft, $l1Rgt, 1, $now );
            $versions[] = $this->versionRow( $l1VersionId, $l1Id, Page::class, $lang, $l1Data, $l1Content, $l1Meta, $nowMs );
            $pivotPageFile[] = ['page_id' => $l1Id, 'file_id' => $l1Fid];
            $pivotPageElement[] = ['page_id' => $l1Id, 'element_id' => $elementId];
            $pivotVersionFile[] = ['version_id' => $l1VersionId, 'file_id' => $l1Fid];
            $pivotVersionElement[] = ['version_id' => $l1VersionId, 'element_id' => $elementId];

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
                    'lang' => $lang, 'name' => "Subcategory {$i}-{$j}", 'title' => "Subcategory {$i}-{$j} Title",
                    'path' => "subcategory-{$i}-{$j}", 'status' => 1, 'editor' => $this->editor,
                ];
                $l2Content = $this->pageContent( $l2Fid, $elementId, $pageIndex );
                $l2Meta = $this->metaDescription( $pageIndex );

                $pages[] = $this->pageRow( $l2Id, $l1Id, $l2VersionId, $lang, $l2Data, $l2Content, $l2Meta, $l2Lft, $l2Rgt, 2, $now );
                $versions[] = $this->versionRow( $l2VersionId, $l2Id, Page::class, $lang, $l2Data, $l2Content, $l2Meta, $nowMs );
                $pivotPageFile[] = ['page_id' => $l2Id, 'file_id' => $l2Fid];
                $pivotPageElement[] = ['page_id' => $l2Id, 'element_id' => $elementId];
                $pivotVersionFile[] = ['version_id' => $l2VersionId, 'file_id' => $l2Fid];
                $pivotVersionElement[] = ['version_id' => $l2VersionId, 'element_id' => $elementId];

                $lft++;
                $fileIndex++;
                $pageIndex++;

                for( $k = 0; $k < $level3PerL2; $k++ )
                {
                    $l3Id = ( new Page )->newUniqueId();
                    $l3VersionId = ( new Version )->newUniqueId();
                    $l3Fid = $fileIds[$fileIndex % $fileCount];

                    $l3Data = [
                        'lang' => $lang, 'name' => "Page {$i}-{$j}-{$k}", 'title' => "Page {$i}-{$j}-{$k} Title",
                        'path' => "page-{$i}-{$j}-{$k}", 'status' => 1, 'editor' => $this->editor,
                    ];
                    $l3Content = $this->pageContent( $l3Fid, $elementId, $pageIndex );
                    $l3Meta = $this->metaDescription( $pageIndex );

                    $pages[] = $this->pageRow( $l3Id, $l2Id, $l3VersionId, $lang, $l3Data, $l3Content, $l3Meta, $lft, $lft + 1, 3, $now );
                    $versions[] = $this->versionRow( $l3VersionId, $l3Id, Page::class, $lang, $l3Data, $l3Content, $l3Meta, $nowMs );
                    $pivotPageFile[] = ['page_id' => $l3Id, 'file_id' => $l3Fid];
                    $pivotPageElement[] = ['page_id' => $l3Id, 'element_id' => $elementId];
                    $pivotVersionFile[] = ['version_id' => $l3VersionId, 'file_id' => $l3Fid];
                    $pivotVersionElement[] = ['version_id' => $l3VersionId, 'element_id' => $elementId];

                    $lft += 2;
                    $fileIndex++;
                    $pageIndex++;
                }

                $lft++;
            }

            $lft++;
        }

        return compact( 'pages', 'versions', 'pivotPageFile', 'pivotPageElement', 'pivotVersionFile', 'pivotVersionElement' );
    }


    /**
     * Bulk insert page, version, and pivot rows.
     *
     * @param array<string, array<int, array<string, mixed>>> $rows
     */
    protected function insertRows( array $rows ): void
    {
        $conn = config( 'cms.db', 'sqlite' );

        $tables = [
            'cms_pages' => $rows['pages'],
            'cms_versions' => $rows['versions'],
            'cms_page_file' => $rows['pivotPageFile'],
            'cms_page_element' => $rows['pivotPageElement'],
            'cms_version_file' => $rows['pivotVersionFile'],
            'cms_version_element' => $rows['pivotVersionElement'],
        ];

        foreach( $tables as $table => $data )
        {
            foreach( array_chunk( $data, $this->chunk ) as $batch )
            {
                DB::connection( $conn )->table( $table )->insert( $batch );

                if( $this->onProgress ) {
                    ( $this->onProgress )( count( $batch ) );
                }
            }
        }
    }


    /**
     * Clear page cache for seeded pages.
     *
     * @param array<int, array<string, mixed>> $pageRows
     */
    protected function clearPageCache( array $pageRows ): void
    {
        foreach( $pageRows as $row ) {
            Cache::forget( Page::key( $row['path'], $row['domain'] ) );
        }
    }


    /**
     * Create files in bulk and return their IDs.
     *
     * @return array<int, string>
     */
    protected function createFiles( string $lang, int $count, string $now, string $nowMs ): array
    {
        $conn = config( 'cms.db', 'sqlite' );
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
                'lang' => $lang,
                'name' => $name,
                'path' => 'https://placehold.co/1500x1000',
                'previews' => json_encode( ['500' => 'https://placehold.co/500x333', '1000' => 'https://placehold.co/1000x666'] ),
                'description' => '{}',
                'transcription' => '{}',
                'editor' => $this->editor,
                'latest_id' => $versionId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $versionRows[] = [
                'id' => $versionId,
                'tenant_id' => $this->tenantId,
                'versionable_id' => $id,
                'versionable_type' => File::class,
                'lang' => $lang,
                'data' => json_encode( [
                    'mime' => 'image/png',
                    'lang' => $lang,
                    'name' => $name,
                    'path' => 'https://placehold.co/1500x1000',
                    'previews' => ['500' => 'https://placehold.co/500x333', '1000' => 'https://placehold.co/1000x666'],
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
     * Create a shared element and return its ID.
     */
    protected function createElement( string $lang, string $now, string $nowMs ): string
    {
        $conn = config( 'cms.db', 'sqlite' );
        $id = ( new Element )->newUniqueId();
        $versionId = ( new Version )->newUniqueId();
        $text = "Benchmark footer content ({$lang})";

        DB::connection( $conn )->table( 'cms_elements' )->insert( [
            'id' => $id,
            'tenant_id' => $this->tenantId,
            'type' => 'text',
            'lang' => $lang,
            'name' => "Footer ({$lang})",
            'data' => json_encode( ['type' => 'text', 'data' => ['text' => $text]] ),
            'editor' => $this->editor,
            'latest_id' => $versionId,
            'created_at' => $now,
            'updated_at' => $now,
        ] );

        DB::connection( $conn )->table( 'cms_versions' )->insert( [
            'id' => $versionId,
            'tenant_id' => $this->tenantId,
            'versionable_id' => $id,
            'versionable_type' => Element::class,
            'lang' => $lang,
            'data' => json_encode( [
                'lang' => $lang,
                'type' => 'text',
                'name' => "Footer ({$lang})",
                'data' => ['text' => $text],
            ] ),
            'aux' => '{}',
            'published' => true,
            'editor' => $this->editor,
            'created_at' => $nowMs,
        ] );

        return $id;
    }


    /**
     * Build a page row for bulk insert.
     */
    protected function pageRow(
        string $id, ?string $parentId, string $versionId, string $lang,
        array $data, array $content, array $meta,
        int $lft, int $rgt, int $depth, string $now
    ): array
    {
        return [
            'id' => $id,
            'tenant_id' => $this->tenantId,
            'related_id' => null,
            'tag' => $data['tag'] ?? '',
            'lang' => $lang,
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
        ];
    }


    /**
     * Build a version row for bulk insert.
     */
    protected function versionRow(
        string $id, string $versionableId, string $versionableType, string $lang,
        array $data, array $content, array $meta, string $nowMs
    ): array
    {
        return [
            'id' => $id,
            'tenant_id' => $this->tenantId,
            'versionable_id' => $versionableId,
            'versionable_type' => $versionableType,
            'lang' => $lang,
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
                'id' => Utils::uid(),
                'type' => 'meta-tags',
                'group' => 'basic',
                'data' => ['description' => "Benchmark page {$index} description. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore."],
            ],
        ];
    }
}
