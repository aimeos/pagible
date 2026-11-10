<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Translation;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\Jobs\TranslatePage;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Bus;


/**
 * Queues page translations as batches and reports their progress.
 */
final class Pages
{
    /**
     * Page IDs per query, below the placeholder limits.
     */
    private const IDS = 500;

    /**
     * Jobs added to the batch at once, below the placeholder limits.
     */
    private const JOBS = 500;


    /**
     * Queues the needed translations of the pages as new batch.
     *
     * Only stale and (if the user may add variants) missing variants are translated. At most
     * as many translations are queued as the rate limit allows within the retry time.
     *
     * @param array<int, string> $ids Page UUIDs, translated in the order of the page tree
     * @param array<int, string> $langs Language codes of the variants, duplicates are ignored
     * @param Authenticatable|null $user User who starts the translation or NULL inside Permission::system()
     * @return array{id: string, total: int, done: int, failed: int} Batch ID and progress
     * @throws PermissionException If a language isn't writable by the user
     * @throws Exception If a language isn't configured, too many translations are needed or none of the pages exists
     */
    public static function dispatch( array $ids, array $langs, ?Authenticatable $user ) : array
    {
        $langs = self::languages( $langs, $user );
        // other languages only update existing variants
        $adds = Permission::allowed( 'variant:add', $user ) ? array_filter( $langs, fn( $lang ) => Utils::isLocale( $lang ) ) : [];
        $pairs = self::pending( $ids, $langs, $adds );

        if( count( $pairs ) > ( $max = Limit::capacity( TranslatePage::RETRY ) ) ) {
            throw new Exception( __( 'No more than :max translations may be queued at once', ['max' => $max] ) );
        }

        $batch = self::enqueue( $pairs, $user );
        return ['id' => $batch['id']] + self::status( $batch );
    }


    /**
     * Returns the progress of a translation batch.
     *
     * @param string $id Batch ID
     * @return array{total: int, done: int, failed: int}|null Progress or NULL if the batch is unknown
     */
    public static function progress( string $id ) : ?array
    {
        // batches are shared by all tenants
        $batch = Bus::findBatch( $id );

        if( !$batch || $batch->name !== self::name() ) {
            return null;
        }

        return [
            'total' => $batch->totalJobs,
            'done' => $batch->totalJobs - $batch->pendingJobs,
            'failed' => $batch->failedJobs,
        ];
    }


    /**
     * Dispatches a new batch routed like its jobs.
     *
     * @param array<int, TranslatePage> $jobs Translation jobs
     * @return Batch Dispatched batch
     */
    private static function batch( array $jobs ) : Batch
    {
        return Bus::batch( $jobs )->name( self::name() )->allowFailures()
            ->onConnection( Utils::queue() )->onQueue( Utils::queueName() )->dispatch();
    }


    /**
     * Queues the translations as new batch.
     *
     * Already queued translations aren't counted, so the batch progress can complete.
     *
     * @param array<int, array{0: string, 1: string}> $pairs List of page ID and language pairs
     * @param Authenticatable|null $user User who starts the translation
     * @return array{id: string, total: int} Batch ID and number of queued translations
     */
    private static function enqueue( array $pairs, ?Authenticatable $user ) : array
    {
        $tenant = (string) Tenancy::value();
        $batch = null;
        $total = 0;

        // locked per chunk, so an aborted request strands one chunk at most
        foreach( array_chunk( $pairs, self::JOBS ) as $chunk )
        {
            $jobs = TranslatePage::jobs( $chunk, $tenant, $user, $total );

            try
            {
                if( $batch ) {
                    $batch->add( $jobs );
                } elseif( $jobs ) {
                    $batch = self::batch( $jobs );
                }
            }
            catch( \Throwable $e )
            {
                TranslatePage::unlock( $jobs );
                throw $e;
            }

            $total += count( $jobs );
        }

        $batch ??= self::batch( [] );

        return ['id' => $batch->id, 'total' => $total];
    }


    /**
     * Returns the unconfigured or invalid language codes without existing variants.
     *
     * @param array<int, string> $langs Language codes
     * @return array<int, string> Invalid language codes
     */
    private static function invalid( array $langs ) : array
    {
        $locales = Utils::locales();
        $invalid = $locales
            ? array_diff( $langs, $locales )
            : array_filter( $langs, fn( $lang ) => !Utils::isValidLang( $lang ) );

        if( !$invalid ) {
            return [];
        }

        $used = PageVariant::whereIn( 'lang', $invalid )->distinct()->pluck( 'lang' )->all();
        return array_values( array_diff( $invalid, $used ) );
    }


    /**
     * Returns the unique and valid language codes the user may write.
     *
     * Unconfigured languages of existing variants can be used to update these variants.
     *
     * @param array<int, string> $langs Language codes
     * @param Authenticatable|null $user User who starts the translation
     * @return array<int, string> Unique language codes
     * @throws PermissionException If a language isn't writable by the user
     * @throws Exception If a language isn't configured
     */
    private static function languages( array $langs, ?Authenticatable $user ) : array
    {
        $langs = array_values( array_unique( $langs ) );

        foreach( $langs as $lang ) {
            Permission::checkLang( $lang, $user );
        }

        if( $invalid = self::invalid( $langs ) )
        {
            $msg = trans_choice( 'Invalid language code ":codes"|Invalid language codes ":codes"', count( $invalid ), ['codes' => implode( '", "', $invalid )] );
            throw new Exception( $msg );
        }

        return $langs;
    }


    /**
     * Returns the batch name of the current tenant, hashed to fit the name column.
     *
     * @return string Batch name
     */
    private static function name() : string
    {
        // changing it breaks the progress of already queued batches
        return Tenancy::key( 'cms-translate' );
    }


    /**
     * Returns the pages and languages whose variants are missing or stale.
     *
     * @param array<int, string> $ids Page UUIDs
     * @param array<int, string> $langs Language codes of the variants
     * @param array<int, string> $adds Language codes whose missing variants are included
     * @return array<int, array{0: string, 1: string}> List of page ID and language pairs in the order of the page tree
     * @throws Exception If none of the pages exists
     */
    private static function pending( array $ids, array $langs, array $adds ) : array
    {
        $found = false;
        $addable = array_flip( $adds );
        $pairs = [];

        foreach( array_chunk( array_values( array_unique( $ids ) ), self::IDS ) as $chunk )
        {
            $pages = Page::whereIn( 'id', $chunk )->toBase()->get( ['id', 'source', NestedSet::LFT] );
            $variants = self::variants( $chunk, $langs );
            $found = $found || $pages->isNotEmpty();

            foreach( $pages as $page )
            {
                foreach( $langs as $lang )
                {
                    if( $lang === $page->source ) {
                        continue;
                    }

                    // stale, untrashed variants or missing ones which may be added
                    $variant = $variants[$page->id][$lang] ?? null;
                    $needed = $variant ? !$variant->deleted_at && $variant->stale : isset( $addable[$lang] );

                    if( $needed ) {
                        $pairs[(int) $page->{NestedSet::LFT}][] = [(string) $page->id, $lang];
                    }
                }
            }
        }

        if( $ids && !$found ) {
            throw new Exception( __( 'Page not found' ) );
        }

        ksort( $pairs );

        return array_merge( ...array_values( $pairs ) );
    }


    /**
     * Returns the progress of a dispatched batch, unstored batches report all translations as pending.
     *
     * @param array{id: string, total: int} $batch Batch ID and number of queued translations
     * @return array{total: int, done: int, failed: int} Progress of the batch
     */
    private static function status( array $batch ) : array
    {
        return self::progress( $batch['id'] ) ?? ['total' => $batch['total'], 'done' => 0, 'failed' => 0];
    }


    /**
     * Returns the existing variants of the pages including trashed ones.
     *
     * @param array<int, string> $ids Page UUIDs
     * @param array<int, string> $langs Language codes of the variants
     * @return array<string, array<string, \stdClass>> Variant rows by page ID and language
     */
    private static function variants( array $ids, array $langs ) : array
    {
        $map = [];
        $rows = PageVariant::withTrashed()
            ->whereIn( 'page_id', $ids )
            ->whereIn( 'lang', $langs )
            ->toBase()
            ->get( ['page_id', 'lang', 'stale', 'deleted_at'] );

        foreach( $rows as $row ) {
            $map[$row->page_id][$row->lang] = $row;
        }

        return $map;
    }
}
