<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Import\Database;
use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Tally;
use Aimeos\Cms\Import\Variants;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Aimeos\Cms\DB;


abstract class BaseImport extends Command
{
    use CommitsInBatches;

    /** Empty date of MySQL databases used by Joomla and WordPress */
    protected const ZERO_DATE = '0000-00-00 00:00:00';
    /** Maximum number of pages and files kept by prefetchPages() and prefetchFiles() */
    protected const PREFETCH_SIZE = DB::IN_SIZE * 4;
    /** Maximum number of error messages of failed imports kept for the summary */
    protected const FAILURES_SIZE = 100;
    /** Maximum number of files downloaded before the records using them are imported */
    protected const DOWNLOAD_SIZE = 50;
    /** Page attributes kept by slim() */
    protected const SLIM_KEYS = ['id', 'variant_id', 'parent_id', 'tenant_id', 'lang', 'domain', 'path'];

    protected string $connection;
    protected string $domain;
    protected string $editor;
    protected string $fileBase;
    protected string $lang;
    /** @var Collection<string, string> File IDs by source path */
    protected Collection $createdFiles;
    /** @var Collection<int|string, string> Packed IDs, language and URL of the imported pages by source record ID */
    protected Collection $createdPages;
    /** TRUE to keep the imported pages for looking them up by the ID of their source record */
    protected bool $keepPages = true;
    /** FALSE to keep only the source record IDs of the imported pages if createdPage() and createdPath() aren't used */
    protected bool $slimPages = true;
    /** @var array<string, bool> IDs of the pages created or updated by this import */
    protected array $usedPages = [];
    /** @var array<string, PageView|null> Pages fetched at once by URL path without slashes, NULL if no page uses the URL, each one is used once */
    protected array $prefetchedPages = [];
    /** Node of the parent page the last page was moved below */
    protected ?Page $parentNode = null;
    /** TRUE if the root page was created by this import */
    protected bool $newRoot = false;
    /** Counts the saved pages of the imported records */
    protected Tally $tally;
    protected int $failed = 0;
    /** @var array<int, string> Labels and error messages of the first failed imports */
    protected array $failures = [];
    /** @var array<string, string> IDs of the files of previous imports by source path */
    protected array $reusedFiles = [];
    /** @var array<string, string> Error messages of the files which couldn't be downloaded by URL */
    protected array $failedFiles = [];
    /** @var array<string, bool> IDs of the files created or named by this import, kept for the whole import because files used again are only renamed once */
    protected array $namedFiles = [];
    /** @var array<string, File|null> Files of previous imports fetched at once by source path, NULL if there's none, each one is used once */
    protected array $prefetchedFiles = [];
    /** @var array<string, array{resource: resource, mime: string}> Files downloaded before the records using them are imported */
    protected array $downloads = [];
    /** @var array<string, array<string, PageView>> Variants of the pages by page ID and language fetched at once, each page is used once */
    protected array $prefetchedVariants = [];
    /** @var array{for: PageView, latest: string|null, page: PageView, content: array<int, array<string, mixed>>, hashes: array<string, string>}|null Source variant of the page whose translations are saved */
    protected ?array $variantSource = null;
    /** @var int|null Timestamp of the --since option, records changed before are kept as imported before */
    protected ?int $since = null;
    /** @var array<int|string, int> Last modification timestamps of the source records by ID */
    protected array $changed = [];


    /**
     * Reads the common options, sets up the tenant and checks the source database connection.
     *
     * @param string $label Name of the source system
     * @param string $prefix Prefix of the database environment variables, the upper case label if empty
     * @return bool TRUE if the source database is available, FALSE if not
     */
    protected function init( string $label, string $prefix = '' ): bool
    {
        $this->connection = $this->stringOption( 'connection' );
        $this->domain = $this->stringOption( 'domain' );
        $this->lang = $this->stringOption( 'lang' );
        $this->editor = $this->stringOption( 'editor' );
        $this->fileBase = rtrim( $this->stringOption( 'file-base' ), '/' );
        $this->createdFiles = Collection::make();
        $this->createdPages = Collection::make();
        $this->reusedFiles = $this->failedFiles = $this->prefetchedFiles = $this->namedFiles = [];
        $this->forgetDownloads();
        $this->prefetchedVariants = [];
        $this->usedPages = [];
        $this->newRoot = false;
        $this->parentNode = null;
        $this->tally = new Tally();
        $this->failed = 0;
        $this->failures = [];

        if( $tenant = $this->stringOption( 'tenant' ) ) {
            Tenancy::set( $tenant );
        }

        if( ( $reason = Database::check( $this->connection ) ) === null ) {
            return true;
        }

        $this->connectionHelp( $label, $prefix ?: strtoupper( $label ), strtolower( $label ), $reason );
        return false;
    }


    /**
     * Sets the time of the --since option.
     *
     * @return bool TRUE if the option is valid or not given, FALSE if not
     */
    protected function sinceOption(): bool
    {
        $this->since = null;
        $this->changed = [];

        if( ( $since = $this->stringOption( 'since' ) ) === '' ) {
            return true;
        }

        if( ( $time = strtotime( $since ) ) === false ) {
            $this->error( "Invalid date for --since: {$since}" );
            return false;
        }

        $this->since = $time;
        return true;
    }


    /**
     * Tests if the source record wasn't changed since the date of the --since option.
     *
     * Records without known modification time are changed.
     *
     * @param int|string $id ID of the source record
     * @return bool TRUE if the page of the record is kept as imported before
     */
    protected function unchangedSince( int|string $id ): bool
    {
        return $this->since !== null && ( $this->changed[$id] ?? PHP_INT_MAX ) < $this->since;
    }


    /**
     * Shows the error and an example of the source database connection.
     *
     * @param string $label Name of the source CMS, e.g. "WordPress"
     * @param string $prefix Prefix of the environment variables, e.g. "WP"
     * @param string $db Default database name
     * @param string $reason Error message of the failed connection
     */
    protected function connectionHelp( string $label, string $prefix, string $db, string $reason ): void
    {
        $conn = $this->connection;

        $this->error( "Cannot connect to {$label} database using connection \"{$conn}\": {$reason}" );
        $this->error( "Add a \"{$conn}\" connection to config/database.php, e.g.:" );
        $this->line( "  '{$conn}' => [" );
        $this->line( "      'driver' => 'mysql'," );
        $this->line( "      'host' => env('{$prefix}_DB_HOST', '127.0.0.1')," );
        $this->line( "      'database' => env('{$prefix}_DB_DATABASE', '{$db}')," );
        $this->line( "      'username' => env('{$prefix}_DB_USERNAME', 'root')," );
        $this->line( "      'password' => env('{$prefix}_DB_PASSWORD', '')," );
        $this->line( "  ]" );
    }


    /**
     * Keeps the lists of the created and reused files small after the batch is committed.
     */
    protected function committing(): void
    {
        // committed files are found by their path again, only the created ones are needed for pruning
        if( !$this->keepsFiles() ) {
            $this->forgetFiles();
        } else {
            $this->reusedFiles = Database::limit( $this->reusedFiles, static::PREFETCH_SIZE );
        }
    }


    /**
     * Tests if all files created or used by this import must be kept until the end, e.g. to prune the unused ones.
     */
    protected function keepsFiles(): bool
    {
        return false;
    }


    /**
     * Keeps only the last created and reused files.
     *
     * The other files are found by their path again, so the lists don't grow during large imports.
     * Must only be called outside the transactions of the records.
     */
    protected function forgetFiles(): void
    {
        if( $this->createdFiles->count() > static::PREFETCH_SIZE ) {
            $this->createdFiles = $this->createdFiles->slice( $this->createdFiles->count() - static::PREFETCH_SIZE );
        }

        $this->reusedFiles = Database::limit( $this->reusedFiles, static::PREFETCH_SIZE );
    }


    /**
     * Adds the file of a previous import so it's used instead of creating it again.
     *
     * @param string $path Source URL or path of the file
     */
    protected function reuseFile( string $path, File $file ): void
    {
        $this->reusedFiles[$path] = (string) $file->id;
    }


    /**
     * Returns the ID of the file created for the path by this import or reused from a previous one.
     *
     * @param string $path Source URL or path of the file
     * @return string|null File ID or NULL if no file was created for the path
     */
    protected function knownFile( string $path ): ?string
    {
        return $this->createdFiles->get( $path ) ?: $this->reusedFiles[$path] ?? null;
    }


    /**
     * Fetches the files of previous imports for the source paths at once, so they aren't looked up one by one.
     *
     * The files are looked up by their path like in createFile().
     *
     * Files prefetched before for the paths are kept as the newest ones, so they are discarded last.
     *
     * @param iterable<string> $paths Source URLs or paths of the files
     * @param bool $limit TRUE discards the oldest prefetched files if there are too many, FALSE only if there are far too many,
     *  e.g. while the files prefetched for the upcoming records are still used
     */
    protected function prefetchFiles( iterable $paths, bool $limit = true ): void
    {
        $missing = $kept = [];

        foreach( $paths as $path )
        {
            if( $path === '' || $this->createdFiles->has( $path ) || isset( $this->reusedFiles[$path] ) ) {
                continue;
            }

            if( array_key_exists( $path, $this->prefetchedFiles ) )
            {
                $kept[$path] = $this->prefetchedFiles[$path];
                unset( $this->prefetchedFiles[$path] );
            }
            elseif( !array_key_exists( $path, $kept ) )
            {
                $missing[$path] = null;
            }
        }

        $this->prefetchedFiles += $kept;

        if( $missing === [] ) {
            return;
        }

        $found = $missing;

        foreach( array_chunk( array_keys( $missing ), DB::IN_SIZE ) as $chunk )
        {
            // the first file with the path is used like by createFile()
            foreach( File::whereIn( 'path', $chunk )->get() as $file ) {
                $found[(string) $file->path] ??= $file;
            }
        }

        // files prefetched for upcoming records are only discarded if there are far too many
        $size = $limit ? static::PREFETCH_SIZE : static::PREFETCH_SIZE * 4;
        $this->prefetchedFiles = Database::limit( array_replace( $this->prefetchedFiles, $found ), $size, count( $found ) + count( $kept ) );
    }


    /**
     * Returns the prefetched file of the path once or looks it up if it wasn't prefetched.
     *
     * Prefetched files are used once because a rolled back batch can undo the changes of the file.
     *
     * @param string $path Source URL or path of the file
     * @param \Closure(): ?File $fn Looks up the file if it wasn't prefetched
     */
    protected function prefetchedFile( string $path, \Closure $fn ): ?File
    {
        if( !array_key_exists( $path, $this->prefetchedFiles ) ) {
            return $fn();
        }

        $file = $this->prefetchedFiles[$path];
        unset( $this->prefetchedFiles[$path] );

        return $file;
    }


    /**
     * Downloads the files before the records using them are imported, so the batch transaction isn't kept open during the downloads.
     *
     * All needed files and the first DOWNLOAD_SIZE files of the others which aren't downloaded yet
     * are fetched. The batch is committed before because the database locks of the imported records
     * would be kept during the downloads otherwise. Files downloaded before which aren't in the lists
     * are removed.
     *
     * @param iterable<string> $urls URLs of the files which are downloaded when they are created in the order of their use
     * @param iterable<string> $needed URLs of the files used by the next record which are all downloaded
     */
    protected function download( iterable $urls, iterable $needed = [] ): void
    {
        $missing = $requested = [];

        foreach( $needed as $url )
        {
            $requested[$url] = true;

            if( $this->downloadable( $url ) ) {
                $missing[$url] = true;
            }
        }

        // the files of the next records are only downloaded ahead up to the limit
        $limit = count( $missing ) + static::DOWNLOAD_SIZE;

        foreach( $urls as $url )
        {
            $requested[$url] = true;

            if( count( $missing ) < $limit && !isset( $missing[$url] ) && $this->downloadable( $url ) ) {
                $missing[$url] = true;
            }
        }

        foreach( array_diff_key( $this->downloads, $requested ) as $url => $fetched )
        {
            fclose( $fetched['resource'] );
            unset( $this->downloads[$url] );
        }

        if( $missing === [] ) {
            return;
        }

        $this->commitNow();

        foreach( array_keys( $missing ) as $url )
        {
            try {
                $this->downloads[$url] = Files::fetch( $url );
            } catch( \Exception $e ) {
                // the error is reported by downloadOnce() for each record using the file
                $this->failedFiles[$url] = $e->getMessage();
            }
        }

        $this->failedFiles = Database::limit( $this->failedFiles, static::PREFETCH_SIZE );
    }


    /**
     * Tests if the file must be downloaded because it's not downloaded yet, known, failed before or found by prefetchFiles().
     *
     * @param string $url URL of the file
     */
    protected function downloadable( string $url ): bool
    {
        return !isset( $this->downloads[$url] ) && !isset( $this->failedFiles[$url] ) && !$this->createdFiles->has( $url )
            && !isset( $this->reusedFiles[$url] ) && !isset( $this->prefetchedFiles[$url] );
    }


    /**
     * Returns the file downloaded before by download() once.
     *
     * @param string $url URL of the file
     * @return array{resource: resource, mime: string}|null Temporary file and its MIME type or NULL if it wasn't downloaded before
     */
    protected function downloaded( string $url ): ?array
    {
        $fetched = $this->downloads[$url] ?? null;
        unset( $this->downloads[$url] );

        return $fetched;
    }


    /**
     * Removes the files downloaded before which weren't used.
     */
    protected function forgetDownloads(): void
    {
        foreach( $this->downloads as $fetched ) {
            fclose( $fetched['resource'] );
        }

        $this->downloads = [];
    }


    /**
     * Creates the file and remembers a failed download, so files used several times are only downloaded once.
     *
     * @template T
     * @param string $path Source URL or path of the file
     * @param bool $download TRUE if the file is downloaded
     * @param \Closure(): T $fn Creates the file
     * @return T Result of the callback
     * @throws \RuntimeException If the download of the file failed before
     */
    protected function downloadOnce( string $path, bool $download, \Closure $fn ): mixed
    {
        if( isset( $this->failedFiles[$path] ) ) {
            throw new \RuntimeException( $this->failedFiles[$path] );
        }

        try {
            return $fn();
        } catch( \Exception $e ) {
            if( $download ) {
                $this->failedFiles[$path] = $e->getMessage();
                // files whose failure was forgotten are downloaded again when they are used
                $this->failedFiles = Database::limit( $this->failedFiles, static::PREFETCH_SIZE );
            }

            throw $e;
        }
    }


    /**
     * Creates a File record with a published version or returns the existing one with the same path.
     *
     * Files of previous imports are reused so repeated imports don't create duplicates. Their name
     * is updated if it changed in the source and the file wasn't changed in Pagible since. Only the
     * first use of a file per import updates the name, so files used with different names don't
     * get a new version on every import.
     *
     * @param bool $rename FALSE to use the name only for new files
     */
    protected function createFile( string $mime, string $name, string $path, bool $rename = true ): string
    {
        if( $existing = $this->knownFile( $path ) ) {
            return $existing;
        }

        if( $file = $this->prefetchedFile( $path, fn() => File::where( 'path', $path )->first() ) )
        {
            $id = (string) $file->id;

            // the file may be used again after it's not in the list of created files any more
            if( $rename && $name !== '' && !isset( $this->namedFiles[$id] ) && $file->name !== $name && $file->editor === $this->editor ) {
                Files::rename( $file, $name, $this->editor );
            }
        }
        else
        {
            $id = Files::create( $mime, $name, $path, $this->editor, false )->id ?? '';
        }

        $this->namedFiles[$id] = true;
        $this->createdFiles->put( $path, $id );

        return $id;
    }


    /**
     * Creates the page below the parent and publishes its first version.
     *
     * @param array<string, mixed> $pageData Page data from pageData()
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>} $content Content elements, file IDs, shared element IDs and meta data
     * @param PageView|null $parent Parent page or NULL for a root page
     * @param string|null $created Creation date of the source record
     */
    protected function createPage( array $pageData, array $content, ?PageView $parent = null, ?string $created = null ): PageView
    {
        if( $parent ) {
            // consecutive pages are mostly created below the same parent, so its tree node is loaded once
            $parent->setRelation( 'page', $this->parentNode( $parent ) );
        }

        $page = Pages::create( $pageData, $content['elements'], $parent, $created );
        // a page prefetched for the URL before is outdated
        unset( $this->prefetchedPages[trim( (string) ( $pageData['path'] ?? '' ), '/' )] );
        $this->createVersion( $page, $pageData, $content['elements'], $content['fileIds'], $content['meta'] ?? [], $content['elementIds'] ?? [] );

        return $page;
    }


    /**
     * Creates and publishes a page version with its files.
     *
     * @param array<string, mixed> $pageData
     * @param array<int, array<string, mixed>> $contentElements
     * @param array<int, string> $fileIds
     * @param array<string, mixed> $meta Meta data keyed by type
     * @param array<int, string> $elementIds IDs of the referenced shared elements
     */
    protected function createVersion( PageView $page, array $pageData, array $contentElements, array $fileIds, array $meta = [], array $elementIds = [] ): void
    {
        $aux = ['content' => $contentElements] + ( $meta ? ['meta' => $meta] : [] );
        Pages::publish( $page, $pageData, $aux, $fileIds, $elementIds, (string) ( $pageData['lang'] ?? $this->lang ), $this->editor );
    }


    /**
     * Creates the page below the parent or updates the one of a previous import with the same URL.
     *
     * Pages of previous imports are restored if they are trashed, moved below the parent and get
     * no new version if nothing changed. Pages saved before by this import aren't overwritten.
     *
     * @param string $slug URL path of the page
     * @param array<string, mixed> $pageData Page data from pageData()
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>} $content Content elements, file IDs, shared element IDs and meta data
     * @param PageView $parent Parent page
     * @param string|null $created Creation date of the source record for new pages
     * @return array{page: PageView, status: Status} Saved page and its status
     * @throws \RuntimeException If the URL is used by a page which wasn't imported, was changed since or was saved by this import
     */
    protected function savePage( string $slug, array $pageData, array $content, PageView $parent, ?string $created = null ): array
    {
        if( !( $page = $this->previousPage( $slug ) ) )
        {
            $page = $this->createPage( $pageData, $content, $parent, $created );
            $this->usedPages[$page->id ?? ''] = true;

            return ['page' => $page, 'status' => Status::Imported];
        }

        $this->moveBelow( $page, $parent );

        return ['page' => $page, 'status' => $this->updateVersion( $page, $pageData, $content )];
    }


    /**
     * Returns the page imported before for the record which wasn't changed since the date of the --since option.
     *
     * The page is only moved below the parent page, records without page imported before are imported.
     *
     * @param int|string $id ID of the source record
     * @param string $slug URL path of the page
     * @return array{page: PageView, status: Status}|null Kept page and its status or NULL if the record must be imported
     * @throws \RuntimeException If the URL is used by a page which wasn't imported, was changed since or was saved by this import
     */
    protected function keepPage( int|string $id, string $slug, PageView $parent ): ?array
    {
        if( !$this->unchangedSince( $id ) || !( $page = $this->previousPage( $slug ) ) ) {
            return null;
        }

        $this->moveBelow( $page, $parent );

        return ['page' => $page, 'status' => Status::Unchanged];
    }


    /**
     * Moves the page below the parent page if it's not already one of its children.
     */
    protected function moveBelow( PageView $page, PageView $parent ): void
    {
        if( $page->parent_id !== $parent->id ) {
            $page->page->appendToNode( $this->parentNode( $parent ) )->save();
        }
    }


    /**
     * Returns the tree node of the parent page, which is reused for consecutive pages below the same parent.
     *
     * The nested set refreshes the bounds of the node before it inserts or moves a page below.
     */
    protected function parentNode( PageView $parent ): Page
    {
        if( !( $node = $this->parentNode ) || $node->id !== $parent->id ) {
            $node = $this->parentNode = Page::findOrFail( $parent->id );
        }

        return $node;
    }


    /**
     * Returns the page which uses the URL path in the domain of the imported pages including trashed ones.
     *
     * @param string $path URL path with or without leading slash
     */
    protected function pageByPath( string $path ): ?PageView
    {
        $path = trim( $path, '/' );

        if( array_key_exists( $path, $this->prefetchedPages ) )
        {
            $page = $this->prefetchedPages[$path];
            // the page is outdated after the record using it was saved
            unset( $this->prefetchedPages[$path] );

            return $page;
        }

        return PageView::withTrashed()->where( 'domain', $this->domain )->whereIn( 'path', [$path, '/' . $path] )->first();
    }


    /**
     * Fetches the pages which use the URL paths in the domain of the imported pages at once for pageByPath().
     *
     * @param array<int, string> $paths URL paths with or without leading slash
     */
    protected function prefetchPages( array $paths ): void
    {
        $paths = array_values( array_unique( array_map( fn( $path ) => trim( $path, '/' ), $paths ) ) );

        if( $paths === [] ) {
            return;
        }

        // pages of previous imports may use URL paths with leading slash
        $pages = Pages::findAll( ['domain' => $this->domain], 'path', array_merge( $paths, array_map( fn( $path ) => '/' . $path, $paths ) ) );

        foreach( $paths as $path ) {
            $this->prefetchedPages[$path] = $pages[$path] ?? $pages['/' . $path] ?? null;
        }

        $this->prefetchedPages = Database::limit( $this->prefetchedPages, static::PREFETCH_SIZE, count( $paths ) );
    }


    /**
     * Returns TRUE if the page was saved by an import with the same editor and language and wasn't changed since.
     */
    protected function isImported( PageView $page ): bool
    {
        return $page->editor === $this->editor && $page->lang === $this->lang;
    }


    /**
     * Returns the page imported by a previous run which uses the URL path.
     *
     * @param string $path URL path of the page
     * @return PageView|null Page restored if it was trashed or NULL if no page uses the URL
     * @throws \RuntimeException If the URL is used by a page which wasn't imported, was changed since or was saved by this import
     */
    protected function previousPage( string $path ): ?PageView
    {
        if( !( $page = $this->pageByPath( $path ) ) ) {
            return null;
        }

        if( !$this->isImported( $page ) || $page->to !== '' ) {
            throw new \RuntimeException( "URL /{$path} is used by a page which wasn't imported or was changed since" );
        }

        // records with the same URL must not overwrite each other
        if( isset( $this->usedPages[$page->id ?? ''] ) ) {
            throw new \RuntimeException( "URL /{$path} is used by another page of this import" );
        }

        $this->usedPages[$page->id ?? ''] = true;
        return $this->restore( $page );
    }


    /**
     * Publishes a new version of the page imported by a previous run if its data, content or meta data changed.
     *
     * @param array<string, mixed> $pageData Page data
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>} $content Content elements, file IDs, shared element IDs and meta data
     * @return Status Updated or unchanged
     */
    protected function updateVersion( PageView $page, array $pageData, array $content ): Status
    {
        $meta = $content['meta'] ?? [];

        if( Pages::unchanged( $page, $pageData, ['content' => $content['elements'], 'meta' => $meta] ) ) {
            return Status::Unchanged;
        }

        $this->createVersion( $page, $pageData, $content['elements'], $content['fileIds'], $meta, $content['elementIds'] ?? [] );
        return Status::Updated;
    }


    /**
     * Restores the page if it's trashed.
     */
    protected function restore( PageView $page ): PageView
    {
        return $page->trashed() ? Pages::find( ['id' => $page->id, 'variant_id' => $page->variant_id] ) ?? $page : $page;
    }


    /**
     * Remembers the imported page, counts it and reports the import.
     *
     * @param int|string $id ID of the source record
     * @param Status $status Status of the saved page
     * @param string $note Additional information appended to the output, e.g. the domain
     */
    protected function imported( int|string $id, PageView $page, string $title, string $slug, Status $status = Status::Imported, string $note = '' ): PageView
    {
        if( $this->keepPages ) {
            $this->createdPages->put( $id, $this->slimPages ? $this->slim( $page ) : '' );
        }

        $this->tally->add( $status );
        $this->info( '  ' . $status->label() . ": {$title} (/{$slug}){$note}" );

        return $page;
    }


    /**
     * Returns the page imported for the source record with its IDs, language and URL only.
     *
     * The tree node is loaded again if the page is used as parent of another imported page.
     *
     * @param int|string $id ID of the source record
     */
    protected function createdPage( int|string $id ): ?PageView
    {
        return ( $packed = $this->createdPages->get( $id ) ) ? $this->slimPage( $packed ) : null;
    }


    /**
     * Returns the URL path of the page imported for the source record.
     *
     * @param int|string $id ID of the source record
     */
    protected function createdPath( int|string $id ): ?string
    {
        return ( $packed = $this->createdPages->get( $id ) ) ? $this->unpack( $packed )['path'] : null;
    }


    /**
     * Returns the IDs, language and URL of the page packed into a string to keep many imported pages in memory.
     *
     * @return string Packed page attributes, NULL values as "\0"
     */
    protected function slim( PageView $page ): string
    {
        $attributes = $page->getAttributes();
        $values = array_map( fn( $key ) => isset( $attributes[$key] ) ? (string) $attributes[$key] : "\0", static::SLIM_KEYS );

        return implode( "\x1F", $values );
    }


    /**
     * Returns the page with the attributes kept by slim().
     *
     * The tree node is loaded again if the page is used as parent of another imported page.
     *
     * @param string $packed Page attributes packed by slim()
     */
    protected function slimPage( string $packed ): PageView
    {
        return ( new PageView() )->newFromBuilder( $this->unpack( $packed ) );
    }


    /**
     * Returns the page attributes packed by slim().
     *
     * @param string $packed Page attributes packed by slim()
     * @return array<string, string|null> Page attributes
     */
    protected function unpack( string $packed ): array
    {
        $values = array_map( fn( $value ) => $value === "\0" ? null : $value, explode( "\x1F", $packed ) );

        return array_combine( static::SLIM_KEYS, $values );
    }


    /**
     * Gets or creates the root page.
     *
     * @param string|null $domain Domain of the root page or NULL for the root page of any domain
     */
    protected function getOrCreateRoot( ?string $domain = null ): PageView
    {
        $page = Pages::root( $domain );
        $this->newRoot = !$page;

        if( $page ) {
            $this->info( "Using existing root page: {$page->name}" );
        } else {
            $page = $this->createPage( $this->pageData( 'Home', '/', 1, 'root' ), Elements::merge() );
            $this->info( 'Created root page.' );
        }

        // records without URL must not overwrite the root page
        $this->usedPages[$page->id ?? ''] = true;
        return $page;
    }


    /**
     * Returns the data of an imported page.
     *
     * @param string $title Page name and title
     * @param string $slug URL path of the page
     * @param int $status Page status (0 = disabled, 1 = enabled)
     * @param string $tag Page tag
     * @return array<string, mixed> Page data
     */
    protected function pageData( string $title, string $slug, int $status, string $tag = 'page' ): array
    {
        return [
            'name' => $title,
            'title' => $title,
            'path' => trim( $slug, '/' ),
            'tag' => $tag,
            'domain' => $this->domain,
            'lang' => $this->lang,
            'status' => $status,
            'editor' => $this->editor,
        ];
    }


    /**
     * Resolves a source file path to a full URL using the file base URL.
     */
    protected function resolveFile( string $path ): string
    {
        return Files::url( $path, $this->fileBase );
    }


    /**
     * Shows the numbers of imported, updated and unchanged pages.
     *
     * @param int $total Number of source records
     * @param string $label Plural name of the source records
     */
    protected function totals( int $total, string $label ): void
    {
        $this->info( 'Import complete. ' . $this->tally->summary( $label, $total ) );
    }


    /**
     * Shows the numbers of imported, updated and unchanged pages of additional records if there are any.
     *
     * @param Tally $tally Counts of the additional records
     * @param string $label Plural name of the additional records, e.g. "menu links"
     */
    protected function subtotals( Tally $tally, string $label ): void
    {
        if( $tally->total() ) {
            $this->info( ucfirst( $label ) . ': ' . $tally->summary( $label ) );
        }
    }


    /**
     * Returns the first date which isn't empty, e.g. to fall back to the local date if the UTC one is missing.
     *
     * @param string|null ...$dates Dates in the format "YYYY-MM-DD HH:MM:SS"
     * @return string|null First date or NULL if all are empty, including MySQL's zero date
     */
    protected function date( ?string ...$dates ): ?string
    {
        foreach( $dates as $date )
        {
            if( $date && $date !== self::ZERO_DATE ) {
                return $date;
            }
        }

        return null;
    }


    /**
     * Records the failed import of the record and shows the error.
     *
     * @param string $label Label of the imported record in the error message
     * @param \Exception $e Reason of the failure
     */
    protected function failedImport( string $label, \Exception $e ): void
    {
        $this->error( "  Failed to import {$label}: " . $e->getMessage() );
        $this->failed++;

        if( count( $this->failures ) < static::FAILURES_SIZE ) {
            $this->failures[] = "{$label}: " . $e->getMessage();
        }
    }


    /**
     * Executes the callback within a transaction and passes the exception to the error handler on failure.
     *
     * Files created by the failed callback are rolled back too, so they are forgotten and created again
     * when they are used by another record. The same applies to the pages saved by the callback. After every batch of records, the import is committed.
     *
     * @template T
     * @param \Closure(): T $fn Callback
     * @param \Closure(\Exception): void $failed Error handler
     * @return T|null Result of the callback or NULL on failure
     */
    protected function guard( \Closure $fn, \Closure $failed ): mixed
    {
        // both only get new entries during the callback, so the ones added by it are at the end
        $files = $this->createdFiles->count();
        $used = count( $this->usedPages );

        try {
            return DB::connection()->transaction( $fn );
        } catch( \Exception $e ) {
            // the record isn't rolled back by Laravel and the database may have rolled back the whole batch
            if( $this->causedByConcurrencyError( $e ) ) {
                $this->abortBatch();
                throw $e;
            }

            // removing only the added entries avoids copying the large lists on every failure
            while( $this->createdFiles->count() > $files ) {
                $this->createdFiles->pop();
            }

            self::truncate( $this->usedPages, $used );
            // the node may be a page created by the rolled back callback
            $this->parentNode = null;
            $failed( $e );
        } finally {
            $this->commitBatch();
        }

        return null;
    }


    /**
     * Removes the entries added to the end of the list after it had the given number of entries.
     *
     * @param array<array-key, mixed> $list List whose last entries are removed
     * @param int $count Number of entries which are kept
     */
    protected static function truncate( array &$list, int $count ): void
    {
        while( count( $list ) > $count ) {
            array_pop( $list );
        }
    }


    /**
     * Executes the import within a transaction and reports failures.
     *
     * @template T
     * @param string $label Label of the imported record in the error message
     * @param \Closure(): T $fn Import function
     * @return T|null Result of the import function or NULL on failure
     */
    protected function transaction( string $label, \Closure $fn ): mixed
    {
        return $this->guard( $fn, fn( \Exception $e ) => $this->failedImport( $label, $e ) );
    }


    /**
     * Tests if the translation was changed in Pagible since the last import and warns that it's skipped.
     *
     * @param PageView|null $variant Existing variant of the page in the language
     * @param string $title Title of the translation
     * @param string $lang Language code of the translation
     */
    protected function skipsTranslation( ?PageView $variant, string $title, string $lang ): bool
    {
        if( $variant && $variant->editor !== $this->editor )
        {
            $this->warn( "  Skipped translation: {$title} [{$lang}] (changed in Pagible since the last import)" );
            return true;
        }

        return false;
    }


    /**
     * Fetches the variants of the pages in all languages including trashed ones at once, so variants() doesn't fetch them one by one.
     *
     * The previously prefetched variants are discarded unless they are kept.
     *
     * @param array<int, string> $pageIds IDs of the pages
     * @param bool $keep TRUE keeps the previously prefetched variants and only fetches the ones of the other pages
     */
    protected function prefetchVariants( array $pageIds, bool $keep = false ): void
    {
        $pageIds = array_values( array_unique( $pageIds ) );

        if( !$keep ) {
            $this->prefetchedVariants = [];
        }

        $pageIds = array_values( array_filter( $pageIds, fn( $id ) => !isset( $this->prefetchedVariants[$id] ) ) );

        foreach( array_chunk( $pageIds, DB::IN_SIZE ) as $chunk )
        {
            $this->prefetchedVariants += array_fill_keys( $chunk, [] );

            foreach( PageView::withTrashed()->allVariants( true )->whereKey( $chunk )->get() as $variant )
            {
                if( $variant instanceof PageView ) {
                    $this->prefetchedVariants[(string) $variant->id][(string) $variant->lang] = $variant;
                }
            }
        }

        // the variants contain the full pages in all languages, so only a limited number is kept
        $this->prefetchedVariants = Database::limit( $this->prefetchedVariants, DB::IN_SIZE, count( $pageIds ) );
    }


    /**
     * Returns the variants of the page in all languages including trashed ones at once.
     *
     * Variants prefetched by prefetchVariants() are used once because they are outdated after the translations have been saved.
     *
     * @return array<string, PageView> Variants by language code
     */
    protected function variants( PageView $page ): array
    {
        if( isset( $this->prefetchedVariants[$id = (string) $page->id] ) )
        {
            $variants = $this->prefetchedVariants[$id];
            unset( $this->prefetchedVariants[$id] );

            return $variants;
        }

        $variants = [];

        foreach( PageView::withTrashed()->allVariants( true )->whereKey( $page->id )->get() as $variant )
        {
            if( $variant instanceof PageView ) {
                $variants[(string) $variant->lang] = $variant;
            }
        }

        return $variants;
    }


    /**
     * Saves the translation of the page and warns if its URL is already used by another page.
     *
     * @param array{lang: string, domain: string} $language Language code and domain of the translation
     * @param array<string, mixed> $pageData Page data from pageData()
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>} $content Content elements, file IDs, shared element IDs and meta data
     * @param PageView|false|null $existing Variant in the language including trashed ones if already loaded, NULL if there's none or FALSE to load it
     * @return PageView|null Saved variant or NULL if its URL is already used
     */
    protected function saveVariant( PageView $page, array $language, string $slug, array $pageData, array $content, PageView|false|null $existing = false ): ?PageView
    {
        // a page prefetched for the URL before is outdated
        unset( $this->prefetchedPages[trim( $slug, '/' )] );

        // the source variant, its content and hashes are used for all translations of the same page version
        if( ( $this->variantSource['for'] ?? null ) !== $page || $this->variantSource['latest'] !== $page->latest_id ) {
            $this->variantSource = ['for' => $page, 'latest' => $page->latest_id] + Variants::source( $page );
        }

        if( !( $variant = Variants::save( $page, $language, $slug, $pageData, $content, $this->editor, $existing, $this->variantSource ) ) ) {
            $this->warn( "  Skipped translation: {$pageData['title']} (/{$slug}) [{$language['lang']}] (URL is used by another page)" );
        }

        return $variant;
    }


    /**
     * Returns the value of a command option as string or an empty string if the command has no such option.
     */
    protected function stringOption( string $name ): string
    {
        $value = $this->hasOption( $name ) ? $this->option( $name ) : null;
        return is_scalar( $value ) ? (string) $value : '';
    }
}
