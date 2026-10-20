<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Facades\Variants;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Database\Seeders\BenchmarkSeeder;


class BenchmarkCore extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:core
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--tries=100 : Number of iterations per benchmark}
        {--unseed : Remove benchmark data and exit}
        {--force : Force the operation to run in production}';

    protected $description = 'Run core model benchmarks';

    /** @var array<int, string> Columns of the page tree benchmarks */
    private const TREE_COLUMNS = ['id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config', 'latest_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH];


    public function handle(): int
    {
        if( !( $options = $this->settings() ) ) {
            return self::FAILURE;
        }

        ['domain' => $domain, 'tenant' => $tenant, 'tries' => $tries] = $options;

        if( $options['unseed'] )
        {
            $this->unseed( config( 'cms.db', 'sqlite' ), $tenant, $domain );
            return self::SUCCESS;
        }

        [
            'root' => $root, 'page' => $page, 'parent' => $moveParent, 'element' => $element, 'file' => $file,
            'trashed' => ['page' => $trashedPage, 'element' => $trashedElement, 'file' => $trashedFile],
        ] = $this->fixtures( $domain );

        // the page update benchmark replaces the latest version by rolled back ones
        $draft = $page->latest ?? throw new \RuntimeException( 'No latest version' );

        $this->header();


        /**
         * Page operations
         */

        $this->benchmark( 'Page create', function() use ( $root ) {
            $node = new Page( ['source' => 'en'] );
            $node->appendToNode( Page::findOrFail( $root->id ) )->save();

            $p = $node->variants()->create( [
                'lang' => 'en', 'name' => 'Bench page', 'title' => 'Bench',
                'path' => 'bench-' . Utils::uid(), 'status' => 1, 'editor' => 'benchmark',
            ] )->view;
            $version = $p->versions()->forceCreate( [
                'lang' => 'en', 'data' => ['name' => 'Bench page'], 'published' => false, 'editor' => 'benchmark',
            ] );
            $p->publish( $version );
        }, tries: $tries );

        $this->benchmark( 'Page read', function() use ( $page ) {
            PageView::with( 'files', 'elements.files' )->find( $page->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page list', function() {
            PageView::with( 'files', 'elements.files' )->orderBy( NestedSet::LFT )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page update', function() use ( $page ) {
            $this->draft( $page );
        }, tries: $tries );

        $this->benchmark( 'Page move', function() use ( $page, $moveParent ) {
            $page->page->appendToNode( Page::findOrFail( $moveParent->id ) )->save();
        }, tries: $tries );

        $this->benchmark( 'Page publish', function() use ( $page, $draft ) {
            // the rolled back publication leaves the draft published in memory
            $draft->published = false;
            $page->publish( $draft );
        }, tries: $tries );

        $this->benchmark( 'Page delete', function() use ( $page ) {
            $page->page->delete();
            $page->page->deleted_at = null;
            $page->page->syncOriginal();
        }, tries: $tries );

        $this->benchmark( 'Page restore', function() use ( $trashedPage ) {
            $trashedPage->page->restore();
            $trashedPage->page->deleted_at = now();
            $trashedPage->page->syncOriginal();
        }, tries: $tries );

        $this->benchmark( 'Page purge', function() use ( $page ) {
            $page->page->forceDelete();
            $page->page->exists = true;
        }, tries: $tries );

        $this->benchmark( 'Page tree', function() use ( $root ) {
            PageView::select( self::TREE_COLUMNS )
                ->where( 'parent_id', $root->id )->with( ['children', 'latest'] )->get();
        }, readOnly: true, tries: $tries );


        /**
         * Language variant operations
         */

        // data seeded before translations were added has no language variants
        if( PageVariant::where( 'lang', BenchmarkSeeder::TRANSLATION )->exists() ) {
            $this->variants( $root, $domain, $tries );
        }


        /**
         * Element operations
         */

        $this->benchmark( 'Element create', function() {
            $el = Element::forceCreate( [
                'lang' => 'en', 'type' => 'text', 'name' => 'Bench element',
                'data' => ['type' => 'text', 'data' => ['text' => 'Bench']], 'editor' => 'benchmark',
            ] );
            $version = $el->versions()->forceCreate( [
                'lang' => 'en', 'data' => ['type' => 'text', 'name' => 'Bench element'], 'published' => false, 'editor' => 'benchmark',
            ] );
            $el->publish( $version );
        }, tries: $tries );

        $this->benchmark( 'Element read', function() use ( $element ) {
            Element::with( 'files' )->find( $element->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Element list', function() {
            Element::with( 'files' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Element update', function() use ( $element ) {
            $this->draft( $element );
        }, tries: $tries );

        $this->benchmark( 'Element delete', function() use ( $element ) {
            $element->delete();
            $element->deleted_at = null;
        }, tries: $tries );

        $this->benchmark( 'Element restore', function() use ( $trashedElement ) {
            $trashedElement->restore();
            $trashedElement->deleted_at = now();
            $trashedElement->syncOriginal();
        }, tries: $tries );


        /**
         * File operations
         */

        $imagePath = realpath( __DIR__ . '/../../assets/image.png' );
        $this->benchmark( 'File create', function() use ( $imagePath ) {
            $f = File::forceCreate( [
                'mime' => 'image/png', 'lang' => 'en', 'name' => 'Bench file',
                'path' => $imagePath, 'editor' => 'benchmark',
            ] );
            $version = $f->versions()->forceCreate( [
                'lang' => 'en', 'data' => ['mime' => 'image/png', 'name' => 'Bench file', 'path' => $imagePath, 'previews' => []],
                'published' => false, 'editor' => 'benchmark',
            ] );
            $f->publish( $version );
        }, tries: $tries );

        $this->benchmark( 'File read', function() use ( $file ) {
            File::with( 'latest' )->find( $file->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'File list', function() {
            File::with( 'latest' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'File mime', function() {
            File::with( 'latest' )->where( 'mime', 'image/jpeg' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'File update', function() use ( $file ) {
            $this->draft( $file );
        }, tries: $tries );

        $this->benchmark( 'File delete', function() use ( $file ) {
            $file->delete();
            $file->deleted_at = null;
        }, tries: $tries );

        $this->benchmark( 'File restore', function() use ( $trashedFile ) {
            $trashedFile->restore();
            $trashedFile->deleted_at = now();
            $trashedFile->syncOriginal();
        }, tries: $tries );


        /**
         * Version operations
         */

        $this->benchmark( 'Version list', function() use ( $page ) {
            $page->versions()->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Version prune', function() use ( $page ) {
            $page->removeVersions();
        }, tries: $tries );


        $this->line( '' );

        return self::SUCCESS;
    }


    /**
     * Runs the language variant benchmarks, the source language queries are the baseline.
     *
     * @param PageView $root Root page of the benchmark pages
     * @param string $domain Domain name
     * @param int $tries Number of iterations per benchmark
     */
    protected function variants( PageView $root, string $domain, int $tries ): void
    {
        $lang = BenchmarkSeeder::TRANSLATION;
        ['translated' => $translated, 'missing' => $missing] = $this->translations( $lang );

        // translations are only allowed into configured languages
        $locales = config( 'cms.locales' );
        config( ['cms.locales' => array_unique( [...(array) $locales, $lang] )] );

        try {
            Permission::system( fn() => $this->variantBenchmarks( $root, $domain, $tries, $lang, $translated, $missing ) );
        } finally {
            config( ['cms.locales' => $locales] );
        }
    }


    /**
     * Runs the language variant benchmarks for the configured translation language.
     *
     * @param PageView $root Root page of the benchmark pages
     * @param string $domain Domain name
     * @param int $tries Number of iterations per benchmark
     * @param string $lang Language code of the translations
     * @param PageView $translated Page with a variant in the translation language
     * @param PageView $missing Page without a variant in the translation language
     */
    protected function variantBenchmarks( PageView $root, string $domain, int $tries, string $lang, PageView $translated, PageView $missing ): void
    {

        $this->benchmark( 'Variant lang', function() use ( $translated, $lang ) {
            PageView::language( $lang )->with( 'files', 'elements.files' )->find( $translated->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Variant fallback', function() use ( $missing, $lang ) {
            PageView::fallback( $lang )->with( 'files', 'elements.files' )->find( $missing->id );
        }, readOnly: true, tries: $tries );

        $sourcePath = PageView::findOrFail( $translated->id )->path;

        $this->benchmark( 'Source path', function() use ( $sourcePath, $domain ) {
            PageView::where( 'domain', $domain )->where( 'path', $sourcePath )->first();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Variant path', function() use ( $translated, $domain, $lang ) {
            PageView::visible( $lang )->where( 'domain', $domain )->where( 'path', $translated->path )->first();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Source list', function() {
            PageView::orderBy( NestedSet::LFT )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Fallback list', function() use ( $lang ) {
            PageView::fallback( $lang )->orderBy( NestedSet::LFT )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Visible list', function() use ( $lang ) {
            PageView::visible( $lang )->orderBy( NestedSet::LFT )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Source count', function() {
            PageView::count();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Fallback count', function() use ( $lang ) {
            PageView::fallback( $lang )->count();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Visible count', function() use ( $lang ) {
            PageView::visible( $lang )->count();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Fallback tree', function() use ( $root, $lang ) {
            PageView::fallback( $lang )->select( self::TREE_COLUMNS )
                ->where( 'parent_id', $root->id )->with( ['children', 'latest'] )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Visible tree', function() use ( $root, $lang ) {
            PageView::visible( $lang )->select( self::TREE_COLUMNS )
                ->where( 'parent_id', $root->id )->with( ['children', 'latest'] )->get();
        }, readOnly: true, tries: $tries );

        // paginated lists count the pages too, only page column filters use the cheaper count
        $this->benchmark( 'Fallback paginate', function() use ( $lang ) {
            PageView::fallback( $lang )->orderBy( NestedSet::LFT )->paginate( 100, page: 10 );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Visible paginate', function() use ( $lang ) {
            PageView::visible( $lang )->whereIn( 'status', [1, 2] )->orderBy( NestedSet::LFT )->paginate( 100, page: 10 );
        }, readOnly: true, tries: $tries );

        // lists filtered by variant columns fetch one more page instead of counting all pages
        $this->benchmark( 'Visible simple', function() use ( $lang ) {
            PageView::visible( $lang )->whereIn( 'status', [1, 2] )->orderBy( NestedSet::LFT )->simplePaginate( 100, page: 10 );
        }, readOnly: true, tries: $tries );

        // regional languages join the variants of their parent language too
        $regional = $lang . '-CH';

        $this->benchmark( 'Chain list', function() use ( $regional ) {
            PageView::fallback( $regional )->orderBy( NestedSet::LFT )->skip( 500 )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Chain paginate', function() use ( $regional ) {
            PageView::fallback( $regional )->orderBy( NestedSet::LFT )->paginate( 100, page: 10 );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Chain tree', function() use ( $root, $regional ) {
            PageView::visible( $regional )->select( self::TREE_COLUMNS )
                ->where( 'parent_id', $root->id )->with( ['children', 'latest'] )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Variant add', function() use ( $missing, $lang ) {
            Variants::add( (string) $missing->id, $lang );
        }, tries: $tries );

        $this->benchmark( 'Variant translate', function() use ( $missing, $lang ) {
            Variants::translate( (string) $missing->id, $lang, translate: fn( array $texts ) => $texts );
        }, tries: $tries );

        $this->benchmark( 'Variant source', function() use ( $translated, $lang ) {
            Variants::setSource( (string) $translated->id, $lang );
        }, tries: $tries );

        $this->benchmark( 'Variant delete', function() use ( $translated, $lang ) {
            Variants::drop( (string) $translated->id, $lang );
        }, tries: $tries );
    }


    /**
     * Returns the pages used by the language variant benchmarks.
     *
     * @param string $lang Language of the translated variants
     * @return array{translated: PageView, missing: PageView} Page with an enabled translation and one without translation
     */
    protected function translations( string $lang ): array
    {
        return [
            'translated' => PageView::visible( $lang )->where( 'lang', $lang )->where( 'tag', '!=', 'root' )
                ->orderBy( NestedSet::DEPTH, 'desc' )->firstOrFail(),
            'missing' => PageView::fallback( $lang )->where( 'lang', '!=', $lang )->where( 'tag', '!=', 'root' )
                ->orderBy( NestedSet::DEPTH, 'desc' )->firstOrFail(),
        ];
    }


    /**
     * Remove the cached responses of all benchmark pages of the domain.
     */
    public static function invalidate( string $domain ): void
    {
        $paths = array_values( PageView::withTrashed()
            ->where( 'editor', 'benchmark' )
            ->where( 'domain', $domain )
            ->pluck( 'path' )
            ->map( fn( $path ) => (string) $path )
            ->all() );

        if( $paths ) {
            PageInvalidated::dispatch( $domain, $paths );
        }
    }


    /**
     * Remove all benchmark data for the tenant, respecting FK constraints.
     */
    protected function unseed( string $conn, string $tenant, string $domain ): void
    {
        self::invalidate( $domain );

        // Break circular page↔version FK by clearing latest_id first
        DB::connection( $conn )->table( 'cms_page_variants' )
            ->where( 'tenant_id', $tenant )
            ->where( 'editor', 'benchmark' )
            ->update( ['latest_id' => null] );

        $variants = DB::connection( $conn )->table( 'cms_page_variants' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->get( ['id', 'page_id'] );
        $versionIds = DB::connection( $conn )->table( 'cms_versions' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->pluck( 'id' );

        // Delete pivot tables (no tenant_id column)
        foreach( $variants->pluck( 'id' )->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_page_file' )->whereIn( 'variant_id', $chunk )->delete();
            DB::connection( $conn )->table( 'cms_page_element' )->whereIn( 'variant_id', $chunk )->delete();
        }

        foreach( $versionIds->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_version_file' )->whereIn( 'version_id', $chunk )->delete();
            DB::connection( $conn )->table( 'cms_version_element' )->whereIn( 'version_id', $chunk )->delete();
        }

        // Delete main tables
        $tables = ['cms_versions', 'cms_elements', 'cms_files', 'cms_page_variants'];

        foreach( $tables as $table )
        {
            DB::connection( $conn )->table( $table )
                ->where( 'tenant_id', $tenant )
                ->where( 'editor', 'benchmark' )
                ->delete();
        }

        // Pages have no editor column, they are identified by their variants
        foreach( $variants->pluck( 'page_id' )->unique()->chunk( 500 ) as $chunk ) {
            DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $tenant )->whereIn( 'id', $chunk )->delete();
        }
    }
}
